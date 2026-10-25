<?php

namespace Modules\NsMultiStore\Tests\Feature;

use App\Exceptions\NotEnoughPermissionException;
use App\Models\Role;
use Illuminate\Support\Facades\Artisan;
use Modules\NsMultiStore\Models\Store;
use Modules\NsMultiStore\Services\StoreService;
use Modules\NsMultiStore\Tests\Traits\WithEmployees;
use Tests\TestCase;

class StoreSwitchTest extends TestCase
{
    use WithEmployees;

    /**
     * Each test owns its activation and migrations so it can run
     * without relying on another test file.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call( 'modules:migrate', [ 'moduleNamespace' => 'NsMultiStore' ] );

        ns()->registerGatePermissions();

        $this->cleanMultiStoreData();
    }

    /**
     * The store of an employee must be resolved as soon as he
     * browses the dashboard.
     */
    public function test_the_current_store_is_automatically_resolved(): void
    {
        $firstStore = Store::factory()->create();
        $secondStore = Store::factory()->create();

        $employee = $this->createEmployee( [ $firstStore, $secondStore ] );

        $this->actingAs( $employee );

        $this->get( ns()->route( 'ns.dashboard.home' ) )->assertStatus( 200 );

        $this->assertEquals( $firstStore->id, session( StoreService::SESSION_KEY ) );
        $this->assertEquals( $firstStore->id, app( 'current_store_id' ) );
    }

    /**
     * An employee must be able to switch to another store he is bound to.
     */
    public function test_the_current_store_can_be_switched(): void
    {
        $firstStore = Store::factory()->create();
        $secondStore = Store::factory()->create();

        $employee = $this->createEmployee( [ $firstStore, $secondStore ] );

        $this->actingAs( $employee );

        $this->get( ns()->route( 'ns.dashboard.home' ) )->assertStatus( 200 );
        $this->assertEquals( $firstStore->id, session( StoreService::SESSION_KEY ) );

        $this->withHeader( 'X-CSRF-TOKEN', csrf_token() )
            ->post( ns()->route( 'ns.multistore.stores.switch', [ 'store' => $secondStore->id ] ) )
            ->assertStatus( 302 );

        $this->assertEquals( $secondStore->id, session( StoreService::SESSION_KEY ) );

        /**
         * The store chosen on the session must be reused.
         */
        $this->get( ns()->route( 'ns.dashboard.home' ) )->assertStatus( 200 );
        $this->assertEquals( $secondStore->id, app( 'current_store_id' ) );
    }

    /**
     * An employee cannot switch to a store he isn't bound to.
     */
    public function test_an_employee_cannot_switch_to_a_store_he_is_not_bound_to(): void
    {
        $store = Store::factory()->create();
        $otherStore = Store::factory()->create();

        $employee = $this->createEmployee( [ $store ] );

        $this->actingAs( $employee );

        $this->startSession();

        $this->withHeader( 'X-CSRF-TOKEN', csrf_token() )
            ->post( ns()->route( 'ns.multistore.stores.switch', [ 'store' => $otherStore->id ] ) )
            ->assertStatus( 403 );

        /**
         * The employee keeps using the store he is bound to.
         */
        $this->assertEquals( $store->id, session( StoreService::SESSION_KEY ) );
    }

    /**
     * A disabled store is never used, even when it's stored on the session.
     */
    public function test_a_disabled_store_is_never_used(): void
    {
        $disabledStore = Store::factory()->disabled()->create();

        $employee = $this->createEmployee( [ $disabledStore ] );

        $this->actingAs( $employee );

        $this->get( ns()->route( 'ns.dashboard.home' ) )->assertStatus( 200 );

        $this->assertNull( session( StoreService::SESSION_KEY ) );
        $this->assertNull( app( 'current_store_id' ) );
    }

    /**
     * An administrator isn't limited to the stores he is bound to.
     */
    public function test_an_administrator_can_use_every_store(): void
    {
        $store = Store::factory()->create();

        $administrator = Role::namespace( Role::ADMIN )->users->first();

        $this->actingAs( $administrator );

        /**
         * @var StoreService $storeService
         */
        $storeService = app()->make( StoreService::class );

        $this->assertTrue( $storeService->userHasAccess( $administrator, $store ) );

        $this->startSession();

        $this->withHeader( 'X-CSRF-TOKEN', csrf_token() )
            ->post( ns()->route( 'ns.multistore.stores.switch', [ 'store' => $store->id ] ) )
            ->assertStatus( 302 );

        $this->assertEquals( $store->id, session( StoreService::SESSION_KEY ) );
    }

    /**
     * The stores of an employee are limited to the ones he is bound to.
     */
    public function test_the_stores_of_an_employee_are_limited_to_his_bindings(): void
    {
        $store = Store::factory()->create();
        Store::factory()->create();

        $employee = $this->createEmployee( [ $store ] );

        /**
         * @var StoreService $storeService
         */
        $storeService = app()->make( StoreService::class );

        $stores = $storeService->getUserStores( $employee );

        $this->assertCount( 1, $stores );
        $this->assertEquals( $store->id, $stores->first()->id );
    }

    /**
     * A store administrator (store manager) is limited to the stores he is
     * bound to, unlike the main administrator who can use every store.
     */
    public function test_a_store_administrator_is_limited_to_his_bindings(): void
    {
        $store = Store::factory()->create();
        $otherStore = Store::factory()->create();

        $storeAdmin = $this->createEmployee( [ $store ], Role::STOREADMIN );

        /**
         * @var StoreService $storeService
         */
        $storeService = app()->make( StoreService::class );

        $this->assertTrue( $storeService->userHasAccess( $storeAdmin, $store ) );
        $this->assertFalse( $storeService->userHasAccess( $storeAdmin, $otherStore ) );

        $stores = $storeService->getUserStores( $storeAdmin );

        $this->assertCount( 1, $stores );
        $this->assertEquals( $store->id, $stores->first()->id );
    }

    /**
     * Only the main administrator (the headquarter) can access every store.
     * A store administrator (store manager) is not granted that ability.
     */
    public function test_only_the_main_administrator_can_access_all_stores(): void
    {
        $administrator = Role::namespace( Role::ADMIN )->users->first();
        $storeAdmin = $this->createEmployee( [], Role::STOREADMIN );

        /**
         * @var StoreService $storeService
         */
        $storeService = app()->make( StoreService::class );

        $this->assertTrue( $storeService->hasAccessToAllStores( $administrator ) );
        $this->assertFalse( $storeService->hasAccessToAllStores( $storeAdmin ) );
    }

    /**
     * The role of an employee on a store is read from the role × store
     * matrix, not from his global role.
     */
    public function test_the_role_on_a_store_is_resolved_from_the_binding(): void
    {
        $store = Store::factory()->create();
        $storeAdmin = $this->createEmployee( [ $store ], Role::STOREADMIN );

        /**
         * @var StoreService $storeService
         */
        $storeService = app()->make( StoreService::class );

        $this->assertSame( Role::STOREADMIN, $storeService->getRoleOnStore( $storeAdmin, $store ) );
        $this->assertNull( $storeService->getRoleOnStore( $storeAdmin, Store::factory()->create() ) );
    }

    /**
     * "hasRoleOnStore" only checks the role held on the given store.
     */
    public function test_has_role_on_store_matches_the_binding_role(): void
    {
        $store = Store::factory()->create();
        $cashier = $this->createEmployee( [ $store ], Role::STORECASHIER );

        /**
         * @var StoreService $storeService
         */
        $storeService = app()->make( StoreService::class );

        $this->assertTrue( $storeService->hasRoleOnStore( $cashier, $store, Role::STORECASHIER ) );
        $this->assertFalse( $storeService->hasRoleOnStore( $cashier, $store, Role::STOREADMIN ) );
        $this->assertTrue( $storeService->hasRoleOnStore( $cashier, $store, [ Role::STOREADMIN, Role::STORECASHIER ] ) );
    }

    /**
     * "getUserRoleBindings" returns the whole role × store matrix of an
     * employee, indexed by the store id.
     */
    public function test_get_user_role_bindings_returns_the_bindings_keyed_by_store(): void
    {
        $firstStore = Store::factory()->create();
        $secondStore = Store::factory()->create();

        $employee = $this->createEmployee( [ $firstStore, $secondStore ], Role::STORECASHIER );

        /**
         * @var StoreService $storeService
         */
        $storeService = app()->make( StoreService::class );

        $bindings = $storeService->getUserRoleBindings( $employee );

        $this->assertCount( 2, $bindings );
        $this->assertSame( Role::STORECASHIER, $bindings->get( $firstStore->id )->role_namespace );
        $this->assertSame( Role::STORECASHIER, $bindings->get( $secondStore->id )->role_namespace );
    }

    /**
     * "canManageStore" applies the role × store matrix: the administrator
     * manages every store, a store administrator only the stores he is bound
     * to, and a cashier none.
     */
    public function test_can_manage_store_respects_the_role_store_matrix(): void
    {
        $store = Store::factory()->create();
        $otherStore = Store::factory()->create();

        $administrator = Role::namespace( Role::ADMIN )->users->first();
        $storeAdmin = $this->createEmployee( [ $store ], Role::STOREADMIN );
        $cashier = $this->createEmployee( [ $store ], Role::STORECASHIER );

        /**
         * @var StoreService $storeService
         */
        $storeService = app()->make( StoreService::class );

        $this->assertTrue( $storeService->canManageStore( $administrator, $store ) );
        $this->assertTrue( $storeService->canManageStore( $administrator, $otherStore ) );

        $this->assertTrue( $storeService->canManageStore( $storeAdmin, $store ) );
        $this->assertFalse( $storeService->canManageStore( $storeAdmin, $otherStore ) );

        $this->assertFalse( $storeService->canManageStore( $cashier, $store ) );
    }

    /**
     * The assertion helpers of the permission chain throw when the role ×
     * store matrix doesn't grant the requested ability.
     */
    public function test_assert_can_manage_store_throws_for_a_cashier(): void
    {
        $store = Store::factory()->create();
        $cashier = $this->createEmployee( [ $store ], Role::STORECASHIER );

        /**
         * @var StoreService $storeService
         */
        $storeService = app()->make( StoreService::class );

        $this->expectException( NotEnoughPermissionException::class );

        $storeService->assertCanManageStore( $cashier, $store );
    }

    /**
     * The migration grants the employee binding permissions to the store
     * administrator so he can manage the employees of the stores he manages.
     */
    public function test_the_store_administrator_has_the_binding_permissions(): void
    {
        $storeAdminRole = Role::namespace( Role::STOREADMIN );

        $this->assertNotNull( $storeAdminRole, 'The store administrator role is missing.' );

        $namespaces = $storeAdminRole->permissions->pluck( 'namespace' );

        foreach ( [
            'nexopos.create.multistore-users-stores',
            'nexopos.read.multistore-users-stores',
            'nexopos.update.multistore-users-stores',
            'nexopos.delete.multistore-users-stores',
        ] as $namespace ) {
            $this->assertTrue(
                $namespaces->contains( $namespace ),
                sprintf( 'The store administrator should have the "%s" permission.', $namespace )
            );
        }
    }
}
