<?php

namespace Modules\NsMultiStore\Tests\Feature;

use App\Exceptions\NotAllowedException;
use App\Models\Customer;
use App\Models\CustomerAccountHistory;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleRelation;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Modules\NsMultiStore\Models\Store;
use Modules\NsMultiStore\Services\StoreService;
use Modules\NsMultiStore\Tests\Traits\WithEmployees;
use Tests\TestCase;

/**
 * Members are globally shared across stores: the opening store
 * ("owner_store_id") is only kept for statistics and performance
 * attribution. This test suite locks the member business rules:
 *
 * - a member is assigned to the store that opens his card;
 * - a phone number is globally unique;
 * - a wallet operation records the store that performed it;
 * - a store can only refund the amount it credited itself;
 * - only the opening store (or the headquarter) can edit the phone;
 * - members are soft deleted.
 */
class MemberFeatureTest extends TestCase
{
    use WithEmployees;

    private int $adminId;

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

        /**
         * Remove members and wallet history created by previous tests so the
         * globally unique phone constraint doesn't leak across tests.
         */
        $storeCustomerRole = Role::namespace( Role::STORECUSTOMER );

        if ( $storeCustomerRole ) {
            $customerIds = DB::table( 'nexopos_users_roles_relations' )
                ->where( 'role_id', $storeCustomerRole->id )
                ->pluck( 'user_id' );

            if ( $customerIds->isNotEmpty() ) {
                DB::table( 'nexopos_customers_account_history' )->whereIn( 'customer_id', $customerIds )->delete();
                DB::table( 'nexopos_users_roles_relations' )->whereIn( 'user_id', $customerIds )->delete();
                DB::table( 'nexopos_users' )->whereIn( 'id', $customerIds )->delete();
            }
        }

        /**
         * The StoreService keeps the current store on the singleton, it must
         * be reset so a previous test doesn't leak its store.
         */
        app( StoreService::class )->defineCurrentStore( null );

        $this->adminId = User::whereHas( 'roles', fn ( $query ) => $query->where( 'namespace', Role::ADMIN ) )->first()->id;
    }

    /**
     * Create a member and assign him the "store customer" role so he becomes
     * visible through the Customer global scope.
     */
    protected function createMember( string $phone, ?Store $store = null ): Customer
    {
        if ( $store instanceof Store ) {
            app( StoreService::class )->defineCurrentStore( $store );
        }

        $username = 'member-' . Str::random( 10 );

        $customer = new Customer;
        $customer->username = $username;
        $customer->email = $username . '@example.com';
        $customer->password = Hash::make( 'password' );
        $customer->first_name = 'John';
        $customer->last_name = 'Doe';
        $customer->phone = $phone;
        $customer->author_id = $this->adminId;
        $customer->save();

        $role = Role::namespace( Role::STORECUSTOMER );

        $roleRelation = new UserRoleRelation;
        $roleRelation->user_id = $customer->id;
        $roleRelation->role_id = $role->id;
        $roleRelation->save();

        return $customer->fresh();
    }

    /**
     * Record a wallet operation on a member.
     */
    protected function addHistory( Customer $customer, string $operation, float $amount ): CustomerAccountHistory
    {
        $history = new CustomerAccountHistory;
        $history->customer_id = $customer->id;
        $history->operation = $operation;
        $history->amount = $amount;
        $history->author_id = $this->adminId;
        $history->save();

        return $history;
    }

    /**
     * Acceptance 1: a member gets the opening store assigned.
     */
    public function test_a_member_is_assigned_to_the_opening_store(): void
    {
        $store = Store::factory()->create();

        $customer = $this->createMember( '13800000001', $store );

        $this->assertSame( $store->id, $customer->owner_store_id );
    }

    /**
     * Acceptance 2: members are globally visible, no store isolation applies.
     */
    public function test_a_member_is_visible_from_another_store(): void
    {
        $storeA = Store::factory()->create();
        $storeB = Store::factory()->create();

        $customer = $this->createMember( '13800000001', $storeA );

        /**
         * Switch to another store and make sure the member is still reachable.
         */
        app( StoreService::class )->defineCurrentStore( $storeB );

        $found = Customer::where( 'phone', '13800000001' )->first();

        $this->assertNotNull( $found );
        $this->assertSame( $customer->id, $found->id );
        $this->assertSame( $storeA->id, $found->owner_store_id );
    }

    /**
     * Acceptance 9: a phone number is globally unique.
     */
    public function test_a_duplicate_phone_is_rejected(): void
    {
        $this->createMember( '13800000001' );

        $this->expectException( NotAllowedException::class );
        $this->expectExceptionMessage( __m( '该手机号已被使用，请直接关联已有会员', 'NsMultiStore' ) );

        $this->createMember( '13800000001' );
    }

    /**
     * Acceptance 3: a wallet credit records the store that performed it.
     */
    public function test_a_wallet_credit_records_the_store(): void
    {
        $storeB = Store::factory()->create();

        $customer = $this->createMember( '13800000001', Store::factory()->create() );

        app( StoreService::class )->defineCurrentStore( $storeB );

        $history = $this->addHistory( $customer, CustomerAccountHistory::OPERATION_ADD, 500 );

        $this->assertSame( $storeB->id, $history->store_id );
    }

    /**
     * Acceptance 4: a wallet debit records the store that performed it.
     */
    public function test_a_wallet_debit_records_the_store(): void
    {
        $storeA = Store::factory()->create();

        $customer = $this->createMember( '13800000001', $storeA );

        app( StoreService::class )->defineCurrentStore( $storeA );

        $history = $this->addHistory( $customer, CustomerAccountHistory::OPERATION_DEDUCT, 200 );

        $this->assertSame( $storeA->id, $history->store_id );
    }

    /**
     * Acceptance 5: a store can only refund the amount it credited itself.
     */
    public function test_a_refund_from_another_store_is_rejected(): void
    {
        $storeA = Store::factory()->create();
        $storeB = Store::factory()->create();

        $customer = $this->createMember( '13800000001', $storeA );

        app( StoreService::class )->defineCurrentStore( $storeA );
        $this->addHistory( $customer, CustomerAccountHistory::OPERATION_ADD, 500 );

        /**
         * Store B attempts to refund the credit made by store A.
         */
        app( StoreService::class )->defineCurrentStore( $storeB );

        $this->expectException( NotAllowedException::class );
        $this->expectExceptionMessage( __m( '只能退款本店铺充值的金额', 'NsMultiStore' ) );

        $this->addHistory( $customer, CustomerAccountHistory::OPERATION_REFUND, 500 );
    }

    /**
     * Acceptance 6: the opening store can refund its own credit.
     */
    public function test_the_opening_store_can_refund_its_own_credit(): void
    {
        $storeA = Store::factory()->create();

        $customer = $this->createMember( '13800000001', $storeA );

        app( StoreService::class )->defineCurrentStore( $storeA );
        $this->addHistory( $customer, CustomerAccountHistory::OPERATION_ADD, 500 );

        $history = $this->addHistory( $customer, CustomerAccountHistory::OPERATION_REFUND, 500 );

        $this->assertSame( $storeA->id, $history->store_id );
    }

    /**
     * Acceptance 7: a non owner store cannot edit the phone number.
     */
    public function test_a_non_owner_store_cannot_edit_the_phone(): void
    {
        $storeA = Store::factory()->create();
        $storeB = Store::factory()->create();

        $customer = $this->createMember( '13800000001', $storeA );

        $employee = $this->createEmployee( [ $storeB ], Role::STORECASHIER );

        $this->actingAs( $employee );
        app( StoreService::class )->defineCurrentStore( $storeB );

        $customer->phone = '13800000002';

        $this->expectException( NotAllowedException::class );
        $this->expectExceptionMessage( __m( '仅总部或开卡门店可修改手机号', 'NsMultiStore' ) );

        $customer->save();
    }

    /**
     * Acceptance 8: the opening store can edit the phone number.
     */
    public function test_the_opening_store_can_edit_the_phone(): void
    {
        $storeA = Store::factory()->create();

        $customer = $this->createMember( '13800000001', $storeA );

        $employee = $this->createEmployee( [ $storeA ], Role::STORECASHIER );

        $this->actingAs( $employee );
        app( StoreService::class )->defineCurrentStore( $storeA );

        $customer->phone = '13800000002';
        $customer->save();

        $this->assertSame( '13800000002', $customer->fresh()->phone );
    }

    /**
     * The headquarter (main administrator) can edit any phone number.
     */
    public function test_the_headquarter_can_edit_any_phone(): void
    {
        $storeA = Store::factory()->create();

        $customer = $this->createMember( '13800000001', $storeA );

        $administrator = Role::namespace( Role::ADMIN )->users->first();

        $this->actingAs( $administrator );
        app( StoreService::class )->defineCurrentStore( Store::factory()->create() );

        $customer->phone = '13800000002';
        $customer->save();

        $this->assertSame( '13800000002', $customer->fresh()->phone );
    }

    /**
     * Acceptance 10: a member is soft deleted and can still be fetched with
     * withTrashed().
     */
    public function test_a_member_is_soft_deleted(): void
    {
        $customer = $this->createMember( '13800000001' );

        $customer->delete();

        $this->assertSoftDeleted( 'nexopos_users', [ 'id' => $customer->id ] );

        $this->assertNull( Customer::find( $customer->id ) );
        $this->assertNotNull( Customer::withTrashed()->find( $customer->id ) );
    }

    /**
     * The customers list exposes the "Opening Store" column injected by the
     * module through the CRUD hooks.
     */
    public function test_the_customers_list_exposes_the_opening_store(): void
    {
        $store = Store::factory()->create( [ 'name' => 'Beijing Main Store' ] );

        $customer = $this->createMember( '13800000001', $store );

        $administrator = User::find( $this->adminId );

        $this->actingAs( $administrator );
        Sanctum::actingAs( $administrator, [ '*' ] );

        $response = $this->withSession( $this->app[ 'session' ]->all() )
            ->json( 'GET', 'api/crud/ns.customers?search=' . $customer->phone );

        $response->assertStatus( 200 );

        $entries = collect( $response->json( 'data' ) );

        $entry = $entries->firstWhere( 'id', $customer->id );

        $this->assertNotNull( $entry, 'The created customer was not found on the list.' );
        $this->assertSame( 'Beijing Main Store', $entry[ 'ownerStore_name' ] ?? null );
    }
}
