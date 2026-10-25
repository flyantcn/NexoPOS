<?php

namespace Modules\NsMultiStore\Traits;

use Modules\NsMultiStore\Scopes\StoreScope;

/**
 * Marks a model as owned by a store.
 *
 * Applying the trait:
 * - adds the "StoreScope" global scope, so every read is limited to the
 *   current store;
 * - assigns the current store to newly created records that don't define
 *   one explicitly.
 */
trait StoreScoped
{
    protected static function bootStoreScoped(): void
    {
        static::addGlobalScope( new StoreScope );

        static::creating( function ( $model ) {
            if ( empty( $model->store_id ) ) {
                $storeId = app( 'current_store_id' );

                if ( $storeId !== null ) {
                    $model->store_id = $storeId;
                }
            }
        } );
    }
}
