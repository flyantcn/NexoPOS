<?php

namespace Modules\NsMultiStore\Tests\Traits;

use App\Models\Role;
use App\Models\User;
use App\Models\UserAttribute;
use App\Models\UserRoleRelation;
use Illuminate\Support\Facades\Hash;
use Modules\NsMultiStore\Models\Store;
use Modules\NsMultiStore\Models\UserStoreRelation;

trait WithEmployees
{
    /**
     * Remove every store and every binding created by a previous test.
     */
    protected function cleanMultiStoreData(): void
    {
        UserStoreRelation::query()->delete();
        Store::query()->delete();
    }

    /**
     * Create an employee having a role and bind him to the provided stores.
     *
     * @param array<int, Store> $stores
     */
    protected function createEmployee( array $stores = [], string $roleNamespace = Role::STORECASHIER ): User
    {
        $user = new User;
        $user->username = 'employee-' . fake()->unique()->numberBetween( 1000, 9999 );
        $user->first_name = fake()->firstName();
        $user->last_name = fake()->lastName();
        $user->email = fake()->unique()->safeEmail();
        $user->password = Hash::make( 'password' );
        $user->active = true;
        $user->save();

        /**
         * The dashboard layout reads the attributes of the user, they must
         * exist even when the employee doesn't have any preference yet.
         */
        $attribute = new UserAttribute;
        $attribute->user_id = $user->id;
        $attribute->save();

        $roleRelation = new UserRoleRelation;
        $roleRelation->user_id = $user->id;
        $roleRelation->role_id = Role::namespace( $roleNamespace )->id;
        $roleRelation->save();

        foreach ( $stores as $store ) {
            UserStoreRelation::create( [
                'user_id' => $user->id,
                'store_id' => $store->id,
                'role_namespace' => $roleNamespace,
            ] );
        }

        return $user->fresh();
    }
}
