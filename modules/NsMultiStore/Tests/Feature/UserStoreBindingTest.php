<?php

namespace Modules\NsMultiStore\Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Modules\NsMultiStore\Crud\EmployeeCrud;
use Modules\NsMultiStore\Models\Store;
use Modules\NsMultiStore\Models\UserStoreRelation;
use Modules\NsMultiStore\Tests\Traits\WithEmployees;
use Tests\TestCase;
use Tests\Traits\WithCrud;

class UserStoreBindingTest extends TestCase
{
    use WithCrud;
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

        $user = User::whereHas( 'roles', function ( $query ) {
            $query->where( 'namespace', Role::ADMIN );
        } )->first();

        $this->actingAs( $user );
        Sanctum::actingAs( $user, [ '*' ] );
    }

    /**
     * An employee must be bound to a store using the CRUD API.
     */
    public function test_an_employee_can_be_bound_to_a_store(): void
    {
        $store = Store::factory()->create();
        $employee = $this->createEmployee();

        $response = $this->submitRequest( 'ns.multistore.users-stores', [
            'user_id' => $employee->id,
            'general' => [
                'store_id' => $store->id,
                'role_namespace' => Role::STORECASHIER,
            ],
        ] );

        $response->assertStatus( 200 );
        $response->assertJson( [ 'status' => 'success' ] );

        $this->assertDatabaseHas( 'nsmultistore_users_stores_relations', [
            'user_id' => $employee->id,
            'store_id' => $store->id,
            'role_namespace' => Role::STORECASHIER,
        ] );
    }

    /**
     * The same employee cannot be bound twice to the same store.
     */
    public function test_an_employee_cannot_be_bound_twice_to_the_same_store(): void
    {
        $store = Store::factory()->create();
        $employee = $this->createEmployee();

        UserStoreRelation::create( [
            'user_id' => $employee->id,
            'store_id' => $store->id,
            'role_namespace' => Role::STORECASHIER,
        ] );

        $response = $this->submitRequest( 'ns.multistore.users-stores', [
            'user_id' => $employee->id,
            'general' => [
                'store_id' => $store->id,
                'role_namespace' => Role::STORECASHIER,
            ],
        ] );

        $response->assertStatus( 422 );

        $this->assertEquals( 1, UserStoreRelation::where( 'user_id', $employee->id )->count() );
    }

    /**
     * An administrator might type an username instead of selecting it from
     * the search results, the id is resolved from the username then.
     */
    public function test_an_employee_can_be_bound_using_his_username(): void
    {
        $store = Store::factory()->create();
        $employee = $this->createEmployee();

        $response = $this->submitRequest( 'ns.multistore.users-stores', [
            'user_id' => $employee->username,
            'general' => [
                'store_id' => $store->id,
                'role_namespace' => Role::STORECASHIER,
            ],
        ] );

        $response->assertStatus( 200 );

        $this->assertDatabaseHas( 'nsmultistore_users_stores_relations', [
            'user_id' => $employee->id,
            'store_id' => $store->id,
        ] );
    }

    /**
     * An unknown employee must fail with a validation error instead of
     * reaching the database with a non integer value.
     */
    public function test_binding_an_unknown_employee_fails_gracefully(): void
    {
        $store = Store::factory()->create();

        $response = $this->submitRequest( 'ns.multistore.users-stores', [
            'user_id' => 'ghost-user',
            'general' => [
                'store_id' => $store->id,
                'role_namespace' => Role::STORECASHIER,
            ],
        ] );

        $response->assertStatus( 422 );
    }

    /**
     * The employees are searched from the CRUD API, so that the whole user
     * list isn't loaded on the binding form.
     */
    public function test_employees_can_be_searched_from_the_crud_api(): void
    {
        $employee = $this->createEmployee();

        $response = $this->json( 'GET', 'api/crud/' . EmployeeCrud::IDENTIFIER . '?search=' . $employee->username );

        $response->assertStatus( 200 );
        $response->assertJsonPath( 'data.0.id', $employee->id );

        /**
         * The label displayed is built from the full name and the username.
         */
        $this->assertStringContainsString( $employee->username, $response->json( 'data.0.display_name' ) );
    }

    /**
     * Customers share the users table, they must not be offered as employees.
     */
    public function test_customers_are_not_offered_as_employees(): void
    {
        $employee = $this->createEmployee();
        $customer = $this->createEmployee( roleNamespace: Role::STORECUSTOMER );

        /**
         * Both users must match the same search to make sure the customer
         * is filtered out by his role and not by the search.
         */
        $employee->username = 'searchable-employee';
        $employee->save();

        $customer->username = 'searchable-customer';
        $customer->save();

        $response = $this->json( 'GET', 'api/crud/' . EmployeeCrud::IDENTIFIER . '?search=searchable' );

        $response->assertStatus( 200 );

        $employeesId = collect( $response->json( 'data' ) )->pluck( 'id' )->toArray();

        $this->assertContains( $employee->id, $employeesId );
        $this->assertNotContains( $customer->id, $employeesId );
    }

    /**
     * While an existing binding is edited, the selected employee is fetched
     * alone, therefore it must expose the same label than the search.
     */
    public function test_a_single_employee_exposes_the_label_used_by_the_form(): void
    {
        $employee = $this->createEmployee();

        $response = $this->json( 'GET', 'api/crud/' . EmployeeCrud::IDENTIFIER . '/' . $employee->id );

        $response->assertStatus( 200 );
        $response->assertJsonPath( 'id', $employee->id );
        $this->assertNotEmpty( $response->json( 'display_name' ) );
        $this->assertStringContainsString( $employee->username, $response->json( 'display_name' ) );
    }

    /**
     * The employee stores list page must be reachable for a user having
     * the read permission.
     */
    public function test_the_employee_stores_list_page_is_reachable(): void
    {
        $response = $this->get( '/dashboard/multistore/users-stores' );

        $response->assertStatus( 200 );
        $response->assertSee( __m( 'Employee Stores List', 'NsMultiStore' ) );
    }
}
