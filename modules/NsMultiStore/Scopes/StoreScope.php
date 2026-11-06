<?php

namespace Modules\NsMultiStore\Scopes;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Modules\NsMultiStore\Services\StoreService;

/**
 * Restrict every query of a store scoped model to the stores visible for the
 * current request.
 *
 * The visible stores are resolved from the container, therefore:
 * - on a dashboard request, the stores computed by the "ResolveCurrentStore"
 *   middleware are used. A single or franchise store only sees itself, while
 *   a chain store also sees the other chain stores of its brand so stock can
 *   be shared across them;
 * - on console commands and scheduled tasks, the list is empty and no filter
 *   is applied so that global maintenance operations keep working.
 */
class StoreScope implements Scope
{
    public function __construct(
        protected StoreService $storeService
    ) {
    }

    public function apply( Builder $builder, Model $model ): void
    {
        $storeIds = app()->bound( 'visible_store_ids' ) ? app( 'visible_store_ids' ) : [];

        if ( ! empty( $storeIds ) ) {
            // 商品表特殊处理：
            // - 本店/连锁店本地商品 (store_id IN visible_store_ids)
            // - 全局商品池 (store_id IS NULL) 必须已上架到当前店
            if ( $model->getTable() === 'nexopos_products' ) {
                $currentStoreId = app( 'current_store_id' );

                $builder->where( function ( $q ) use ( $storeIds, $currentStoreId ) {
                    // 本店/连锁店本地商品
                    $q->whereIn( 'store_id', $storeIds )
                        ->where( 'status', Product::STATUS_AVAILABLE );

                    // 全局商品池且已上架到当前店
                    if ( $currentStoreId !== null ) {
                        $q->orWhere( function ( $sub ) use ( $currentStoreId ) {
                            $sub->whereNull( 'store_id' )
                                ->where( 'status', Product::STATUS_AVAILABLE )
                                ->whereExists( function ( $exists ) use ( $currentStoreId ) {
                                    $exists->from( 'nexopos_store_products' )
                                        ->whereColumn( 'nexopos_store_products.product_id', 'nexopos_products.id' )
                                        ->where( 'nexopos_store_products.store_id', $currentStoreId )
                                        ->where( 'nexopos_store_products.is_active', true );
                                } );
                        } );
                    }
                } );
            } else {
                $builder->whereIn( $model->getTable() . '.store_id', $storeIds );
            }
        }
    }
}
