<?php

namespace Modules\NsMultiStore\Filters;

use App\Classes\CrudTable;
use App\Crud\CustomerCrud;
use App\Events\CrudHookEvent;

/**
 * Extends the core customers CRUD with the multi store membership data.
 *
 * Members are globally shared, the "owner_store_id" only records the store
 * that opened the member card. This filter exposes it as a human readable
 * "Opening Store" column on the customers list without touching the core
 * CRUD class.
 */
class CustomerCrudFilter
{
    /**
     * Add the "Opening Store" column to the customers list.
     *
     * @param  array $columns
     * @return array
     */
    public static function addOpeningStoreColumn( array $columns ): array
    {
        $columns[ 'ownerStore_name' ] = CrudTable::column(
            label: __m( 'Opening Store', 'NsMultiStore' ),
            identifier: 'ownerStore_name',
        );

        return $columns;
    }

    /**
     * Join the stores table and select the store name so the
     * "ownerStore_name" column is populated.
     *
     * @return void
     */
    public static function joinOpeningStore( CrudHookEvent $event ): void
    {
        if ( ! $event->crud instanceof CustomerCrud ) {
            return;
        }

        $event->query->leftJoin( 'nsmultistore_stores as ownerStore', 'ownerStore.id', '=', 'nexopos_users.owner_store_id' );
        $event->query->addSelect( 'ownerStore.name as ownerStore_name' );
    }
}
