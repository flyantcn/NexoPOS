<?php

namespace Modules\NsMultiStore\Services;

use App\Exceptions\NotEnoughPermissionException;
use App\Models\Role;
use App\Models\User;
use App\Services\Helper;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Modules\NsMultiStore\Models\Store;
use Modules\NsMultiStore\Models\UserStoreRelation;

class StoreService
{
    /**
     * The session key that stores the store currently used.
     */
    const SESSION_KEY = 'ns_multistore_current_store_id';

    /**
     * The store used by the current request.
     */
    protected ?Store $currentStore = null;

    /**
     * Whether the stores table is available. It's cached to avoid
     * a schema lookup on every request.
     */
    protected ?bool $storesAreAvailable = null;

    /**
     * Determine if the stores can be resolved for the current request.
     */
    public function storesAreAvailable(): bool
    {
        if ( $this->storesAreAvailable === null ) {
            $this->storesAreAvailable = Helper::installed() && Schema::hasTable( 'nsmultistore_stores' );
        }

        return $this->storesAreAvailable;
    }

    /**
     * Whether an employee can access every store, regardless of the stores
     * he is explicitly bound to.
     *
     * Only the main administrator (the headquarter) can access every store.
     * A store administrator (store manager) is limited to the stores he is
     * bound to, so the role × store matrix stored on the
     * "nsmultistore_users_stores_relations" table is respected.
     */
    public function hasAccessToAllStores( User $user ): bool
    {
        return $user->roles()
            ->where( 'namespace', Role::ADMIN )
            ->count() > 0;
    }

    /**
     * Returns all the stores an employee is allowed to use.
     *
     * @return Collection<int, Store>
     */
    public function getUserStores( User $user ): Collection
    {
        if ( ! $this->storesAreAvailable() ) {
            return new Collection;
        }

        if ( $this->hasAccessToAllStores( $user ) ) {
            return Store::query()
                ->where( 'status', Store::STATUS_ACTIVE )
                ->get();
        }

        return Store::query()
            ->where( 'status', Store::STATUS_ACTIVE )
            ->whereHas( 'users', fn ( $query ) => $query->where( 'nexopos_users.id', $user->id ) )
            ->get();
    }

    /**
     * Determine if an employee can use a specific store.
     */
    public function userHasAccess( User $user, Store $store ): bool
    {
        if ( $store->status !== Store::STATUS_ACTIVE ) {
            return false;
        }

        if ( $this->hasAccessToAllStores( $user ) ) {
            return true;
        }

        return $store->users()
            ->where( 'nexopos_users.id', $user->id )
            ->exists();
    }

    /**
     * Returns the role namespace an employee holds on a specific store,
     * as defined by the role × store matrix ("role_namespace" column of the
     * "nsmultistore_users_stores_relations" table).
     *
     * Returns null when the employee isn't bound to the store.
     */
    public function getRoleOnStore( User $user, Store $store ): ?string
    {
        if ( ! $this->storesAreAvailable() ) {
            return null;
        }

        return UserStoreRelation::query()
            ->where( 'user_id', $user->id )
            ->where( 'store_id', $store->id )
            ->value( 'role_namespace' );
    }

    /**
     * Returns every binding of an employee, indexed by the store id, with
     * the role he holds on each store.
     *
     * @return Collection<int, UserStoreRelation>
     */
    public function getUserRoleBindings( User $user ): Collection
    {
        if ( ! $this->storesAreAvailable() ) {
            return new Collection;
        }

        return UserStoreRelation::query()
            ->where( 'user_id', $user->id )
            ->get()
            ->keyBy( 'store_id' );
    }

    /**
     * Whether an employee holds one of the given roles on a specific store.
     *
     * @param string|array<string> $roleNamespaces
     */
    public function hasRoleOnStore( User $user, Store $store, string|array $roleNamespaces ): bool
    {
        $roleOnStore = $this->getRoleOnStore( $user, $store );

        return $roleOnStore !== null && in_array( $roleOnStore, (array) $roleNamespaces, true );
    }

    /**
     * Whether an employee can manage a specific store: he is either the main
     * administrator (headquarter) or holds an administrator/store-administrator
     * role on that store, as defined by the role × store matrix.
     */
    public function canManageStore( User $user, Store $store ): bool
    {
        if ( $this->hasAccessToAllStores( $user ) ) {
            return true;
        }

        return $this->hasRoleOnStore( $user, $store, [ Role::ADMIN, Role::STOREADMIN ] );
    }

    /**
     * Throws a permission error when an employee isn't allowed to use a
     * specific store. Centralises the access check so the permission chain
     * stays consistent across every entry point.
     */
    public function assertUserHasAccess( User $user, Store $store ): void
    {
        if ( ! $this->userHasAccess( $user, $store ) ) {
            throw new NotEnoughPermissionException(
                __m( 'You are not allowed to use this store.', 'NsMultiStore' )
            );
        }
    }

    /**
     * Throws a permission error when an employee isn't allowed to manage a
     * specific store.
     */
    public function assertCanManageStore( User $user, Store $store ): void
    {
        if ( ! $this->canManageStore( $user, $store ) ) {
            throw new NotEnoughPermissionException(
                __m( 'You are not allowed to manage this store.', 'NsMultiStore' )
            );
        }
    }

    /**
     * Resolve the store that must be used for the current request. The store
     * stored on the session is used as long as the user is allowed to use it,
     * otherwise the first available store is returned.
     */
    public function resolveCurrentStore( ?User $user, ?int $storeId = null ): ?Store
    {
        if ( ! $user instanceof User || ! $this->storesAreAvailable() ) {
            return null;
        }

        $stores = $this->getUserStores( $user );

        if ( $storeId !== null ) {
            $store = $stores->firstWhere( 'id', $storeId );

            if ( $store instanceof Store ) {
                return $store;
            }
        }

        return $stores->first();
    }

    /**
     * Define the store used by the current request.
     */
    public function defineCurrentStore( ?Store $store ): void
    {
        $this->currentStore = $store;
    }

    /**
     * Persist the store used for the next requests.
     */
    public function switchToStore( Request $request, Store $store ): void
    {
        if ( $request->hasSession() ) {
            $request->session()->put( self::SESSION_KEY, $store->id );
        }

        $this->defineCurrentStore( $store );
    }

    /**
     * Returns the store resolved for the current request.
     */
    public function getCurrentStore(): ?Store
    {
        return $this->currentStore;
    }

    /**
     * Returns the id of the store resolved for the current request.
     */
    public function getCurrentStoreId(): ?int
    {
        return $this->currentStore?->id;
    }
}
