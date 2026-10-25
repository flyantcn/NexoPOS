<?php

namespace Modules\NsMultiStore\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

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
    public function apply( Builder $builder, Model $model ): void
    {
        $storeIds = app( 'visible_store_ids' );

        if ( ! empty( $storeIds ) ) {
            $builder->whereIn( $model->getTable() . '.store_id', $storeIds );
        }
    }
}
