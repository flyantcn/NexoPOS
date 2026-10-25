<?php

namespace Modules\NsMultiStore\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Restrict every query of a store scoped model to the store currently used.
 *
 * The store is resolved from the container, therefore:
 * - on a dashboard request, the store resolved by the "ResolveCurrentStore"
 *   middleware is used;
 * - on console commands and scheduled tasks, the value is null and no filter
 *   is applied so that global maintenance operations keep working.
 */
class StoreScope implements Scope
{
    public function apply( Builder $builder, Model $model ): void
    {
        $storeId = app( 'current_store_id' );

        if ( $storeId !== null ) {
            $builder->where( $model->getTable() . '.store_id', $storeId );
        }
    }
}
