<?php

namespace Modules\NsMultiStore\Services;

use App\Exceptions\NotAllowedException;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\NsMultiStore\Models\Store;
use Modules\NsMultiStore\Models\StoreProduct;

/**
 * 商品隔离业务逻辑服务。
 *
 * - 全局商品池 (store_id IS NULL)：所有店铺共享定义，需上架后可见
 * - 门店本地商品 (store_id = N)：仅该店可见
 * - 上架记录：nexopos_store_products，记录门店上架的全局商品
 * - 库存：门店实际库存落在 nexopos_store_products.quantity
 * - 流水：nexopos_products_histories 必须带 store_id
 */
class ProductIsolationService
{
    public function __construct(
        protected StoreService $storeService
    ) {
    }

    /**
     * 创建全局商品，事务内自动为所有店铺插入 nexopos_store_products（is_active = true）
     */
    public function createGlobalProduct( array $data ): Product
    {
        return DB::transaction( function () use ( $data ) {
            // store_id 留空表示全局商品池
            $product = Product::create( array_merge( $data, [
                'store_id' => null,
            ] ) );

            // 为所有店铺插入上架记录
            $storeIds = Store::where( 'status', Store::STATUS_ACTIVE )->pluck( 'id' )->all();

            foreach ( $storeIds as $storeId ) {
                StoreProduct::firstOrCreate(
                    [ 'store_id' => $storeId, 'product_id' => $product->id ],
                    [
                        'is_active' => true,
                        'quantity'  => 0,
                        'author_id' => $data[ 'author_id' ] ?? Auth::id(),
                    ]
                );
            }

            return $product;
        } );
    }

    /**
     * 创建门店本地商品，store_id = $storeId
     */
    public function createStoreLocalProduct( int $storeId, array $data ): Product
    {
        $store = Store::findOrFail( $storeId );
        $this->storeService->assertCanManageStore( Auth::user(), $store );

        return Product::create( array_merge( $data, [
            'store_id' => $storeId,
        ] ) );
    }

    /**
     * 返回该店可见的商品（全局+本地，且全局商品必须已上架且 status=available）
     */
    public function listForStore( int $storeId ): Builder
    {
        $visibleStoreIds = $this->storeService->computeVisibleStoreIds( Store::findOrFail( $storeId ) );

        return Product::query()
            ->where( function ( $q ) use ( $visibleStoreIds, $storeId ) {
                // 本店/连锁店本地商品
                $q->whereIn( 'store_id', $visibleStoreIds )
                    ->where( 'status', Product::STATUS_AVAILABLE )
                    // 全局商品池（store_id IS NULL）且已上架到该店
                    ->orWhere( function ( $q2 ) use ( $storeId ) {
                        $q2->whereNull( 'store_id' )
                            ->where( 'status', Product::STATUS_AVAILABLE )
                            ->whereExists( function ( $sub ) use ( $storeId ) {
                                $sub->from( 'nexopos_store_products' )
                                    ->whereColumn( 'nexopos_store_products.product_id', 'nexopos_products.id' )
                                    ->where( 'nexopos_store_products.store_id', $storeId )
                                    ->where( 'nexopos_store_products.is_active', true );
                            } );
                    } );
            } );
    }

    /**
     * 后台管理端：只返回 store_id IS NULL 的全局商品
     */
    public function listGlobalPool(): Builder
    {
        return Product::query()->whereNull( 'store_id' );
    }

    /**
     * 门店上架全局商品（Upsert，is_active = true）
     */
    public function publishToStore( int $storeId, int $productId ): StoreProduct
    {
        $store = Store::findOrFail( $storeId );
        $this->storeService->assertCanManageStore( Auth::user(), $store );

        $product = Product::findOrFail( $productId );

        if ( ! is_null( $product->store_id ) ) {
            throw new NotAllowedException( __m( '只能上架全局商品池商品', 'NsMultiStore' ) );
        }

        return StoreProduct::updateOrCreate(
            [ 'store_id' => $storeId, 'product_id' => $productId ],
            [
                'is_active' => true,
                'author_id' => Auth::id(),
            ]
        );
    }

    /**
     * 门店下架（is_active = false）
     */
    public function unpublishFromStore( int $storeId, int $productId ): void
    {
        $store = Store::findOrFail( $storeId );
        $this->storeService->assertCanManageStore( Auth::user(), $store );

        StoreProduct::where( 'store_id', $storeId )
            ->where( 'product_id', $productId )
            ->update( [ 'is_active' => false ] );
    }

    /**
     * 全局软下架（status = unavailable，不删记录）
     */
    public function deactivateGlobalProduct( int $productId ): void
    {
        $product = Product::findOrFail( $productId );

        if ( ! is_null( $product->store_id ) ) {
            throw new NotAllowedException( __m( '只能下架全局商品池商品', 'NsMultiStore' ) );
        }

        $product->update( [ 'status' => Product::STATUS_UNAVAILABLE ] );
    }

    /**
     * 库存调整：写 nexopos_store_products.quantity + 追加 nexopos_products_histories（带 store_id）
     */
    public function syncStoreInventory( int $storeId, int $productId, float $delta, string $operation ): void
    {
        $store = Store::findOrFail( $storeId );
        $this->storeService->assertCanManageStore( Auth::user(), $store );

        DB::transaction( function () use ( $storeId, $productId, $delta, $operation ) {
            $storeProduct = StoreProduct::firstOrCreate(
                [ 'store_id' => $storeId, 'product_id' => $productId ],
                [ 'quantity' => 0 ]
            );

            $before = $storeProduct->quantity;
            $after  = $before + $delta;

            if ( $after < 0 ) {
                throw new NotAllowedException( __m( '库存不足', 'NsMultiStore' ) );
            }

            $storeProduct->update( [ 'quantity' => $after ] );

            // 追加库存流水
            $product = \App\Models\Product::find( $productId );
            $unitId = $product?->default_purchase_unit_id ?? 1;

            \App\Models\ProductHistory::create( [
                'product_id'          => $productId,
                'store_id'            => $storeId,
                'operation_type'      => $operation,
                'unit_id'             => $unitId,
                'before_quantity'     => $before,
                'quantity'            => $delta,
                'after_quantity'      => $after,
                'unit_price'          => 0,
                'total_price'         => 0,
                'description'         => "库存{$operation}: {$delta}",
                'author_id'           => Auth::id(),
            ] );
        } );
    }
}