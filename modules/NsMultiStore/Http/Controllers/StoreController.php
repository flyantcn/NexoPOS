<?php

namespace Modules\NsMultiStore\Http\Controllers;

use App\Exceptions\NotEnoughPermissionException;
use App\Http\Controllers\DashboardController;
use App\Models\User;
use Modules\NsMultiStore\Crud\StoreCrud;
use Modules\NsMultiStore\Models\Store;
use Modules\NsMultiStore\Services\StoreService;

class StoreController extends DashboardController
{
    /**
     * Display the list of all the stores.
     */
    public function listStores()
    {
        ns()->restrict( [ 'nexopos.read.multistore-stores' ] );

        return StoreCrud::table();
    }

    /**
     * Display the form to create a new store.
     */
    public function createStore()
    {
        ns()->restrict( [ 'nexopos.create.multistore-stores' ] );

        return StoreCrud::form();
    }

    /**
     * Display the form to edit an existing store.
     */
    public function editStore( Store $store )
    {
        ns()->restrict( [ 'nexopos.update.multistore-stores' ] );

        return StoreCrud::form( $store );
    }

    /**
     * Define the store used by the current user for the next requests.
     */
    public function switchStore( Store $store )
    {
        $user = request()->user();

        if ( ! $user instanceof User ) {
            throw new NotEnoughPermissionException(
                __m( 'You must be logged in to switch of store.', 'NsMultiStore' )
            );
        }

        /**
         * @var StoreService $storeService
         */
        $storeService = app()->make( StoreService::class );

        $storeService->assertUserHasAccess( $user, $store );

        $storeService->switchToStore( request(), $store );

        return redirect()->back();
    }
}
