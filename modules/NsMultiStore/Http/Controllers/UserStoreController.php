<?php

namespace Modules\NsMultiStore\Http\Controllers;

use App\Exceptions\NotEnoughPermissionException;
use App\Http\Controllers\DashboardController;
use App\Models\User;
use Modules\NsMultiStore\Crud\UserStoreCrud;
use Modules\NsMultiStore\Models\Store;
use Modules\NsMultiStore\Models\UserStoreRelation;
use Modules\NsMultiStore\Services\StoreService;

class UserStoreController extends DashboardController
{
    /**
     * Display the list of the employees bound to a store.
     */
    public function listUserStores()
    {
        ns()->restrict( [ 'nexopos.read.multistore-users-stores' ] );

        $this->assertCanManageEmployeeBindings( $this->currentStore() );

        return UserStoreCrud::table();
    }

    /**
     * Display the form used to bind an employee to a store.
     */
    public function createUserStore()
    {
        ns()->restrict( [ 'nexopos.create.multistore-users-stores' ] );

        $this->assertCanManageEmployeeBindings( $this->currentStore() );

        return UserStoreCrud::form();
    }

    /**
     * Display the form used to edit an existing binding.
     */
    public function editUserStore( UserStoreRelation $relation )
    {
        ns()->restrict( [ 'nexopos.update.multistore-users-stores' ] );

        $this->assertCanManageEmployeeBindings( $relation->store );

        return UserStoreCrud::form( $relation );
    }

    /**
     * The main administrator manages every store. A store administrator is
     * limited to the store he manages, so the role × store matrix is applied
     * on top of the global permission check.
     */
    protected function assertCanManageEmployeeBindings( ?Store $store = null ): void
    {
        $user = request()->user();

        if ( ! $user instanceof User ) {
            throw new NotEnoughPermissionException(
                __m( 'You must be logged in to manage the employees of a store.', 'NsMultiStore' )
            );
        }

        /**
         * @var StoreService $storeService
         */
        $storeService = app()->make( StoreService::class );

        /**
         * The headquarter (main administrator) can manage every store.
         */
        if ( $storeService->hasAccessToAllStores( $user ) ) {
            return;
        }

        /**
         * A store administrator must target a store he can manage.
         */
        if ( $store instanceof Store && $storeService->canManageStore( $user, $store ) ) {
            return;
        }

        throw new NotEnoughPermissionException(
            __m( 'You are not allowed to manage the employees of this store.', 'NsMultiStore' )
        );
    }

    /**
     * Returns the store resolved for the current request, if any.
     */
    protected function currentStore(): ?Store
    {
        return app()->make( StoreService::class )->getCurrentStore();
    }
}
