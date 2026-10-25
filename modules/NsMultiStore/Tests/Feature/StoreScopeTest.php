<?php

namespace Modules\NsMultiStore\Tests\Feature;

use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\NsMultiStore\Models\Store;
use Modules\NsMultiStore\Services\StoreService;
use Tests\TestCase;

class StoreScopeTest extends TestCase
{
    private int $userId;

    /**
     * The store scope keeps its state on the StoreService singleton, it must
     * be reset between tests.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call( 'modules:migrate', [ 'moduleNamespace' => 'NsMultiStore' ] );

        ns()->registerGatePermissions();

        $this->userId = User::whereHas( 'roles', fn ( $query ) => $query->where( 'namespace', Role::ADMIN ) )->first()->id;

        app( StoreService::class )->defineCurrentStore( null );

        DB::table( 'nexopos_orders' )->delete();
    }

    /**
     * Create an order owned by the current store.
     */
    private function makeOrder(): Order
    {
        $order = new Order;
        $order->code = 'TEST-' . Str::random( 12 );
        $order->type = Order::TYPE_TAKEAWAY;
        $order->payment_status = Order::PAYMENT_PAID;
        $order->customer_id = $this->userId;
        $order->author_id = $this->userId;
        $order->total = 0;
        $order->tendered = 0;
        $order->change = 0;
        $order->save();

        return $order;
    }

    /**
     * The 8 core tables are isolated by a "store_id" column.
     */
    public function test_the_core_tables_have_a_store_id_column(): void
    {
        foreach ( [
            'nexopos_orders',
            'nexopos_orders_products',
            'nexopos_products_unit_quantities',
            'nexopos_registers',
            'nexopos_registers_history',
            'nexopos_transactions',
            'nexopos_procurements',
            'nexopos_products_histories',
        ] as $table ) {
            $this->assertTrue( Schema::hasColumn( $table, 'store_id' ), "Missing store_id on $table" );
        }
    }

    /**
     * A new record is automatically assigned to the current store.
     */
    public function test_a_new_order_is_assigned_to_the_current_store(): void
    {
        $store = Store::factory()->create();

        app( StoreService::class )->defineCurrentStore( $store );

        $order = $this->makeOrder();

        $this->assertEquals( $store->id, $order->store_id );
    }

    /**
     * An explicitly provided store isn't overwritten by the current one.
     */
    public function test_an_explicit_store_is_not_overwritten(): void
    {
        $currentStore = Store::factory()->create();
        $explicitStore = Store::factory()->create();

        app( StoreService::class )->defineCurrentStore( $currentStore );

        $order = $this->makeOrder();

        $order->store_id = $explicitStore->id;
        $order->save();

        $this->assertEquals( $explicitStore->id, $order->store_id );
    }

    /**
     * Only the records of the current store are returned.
     */
    public function test_orders_are_filtered_by_the_current_store(): void
    {
        $firstStore = Store::factory()->create();
        $secondStore = Store::factory()->create();

        app( StoreService::class )->defineCurrentStore( $firstStore );
        $firstOrder = $this->makeOrder();

        app( StoreService::class )->defineCurrentStore( $secondStore );
        $secondOrder = $this->makeOrder();

        app( StoreService::class )->defineCurrentStore( $firstStore );

        $ids = Order::query()->pluck( 'id' )->toArray();

        $this->assertContains( $firstOrder->id, $ids );
        $this->assertNotContains( $secondOrder->id, $ids );

        app( StoreService::class )->defineCurrentStore( $secondStore );

        $ids = Order::query()->pluck( 'id' )->toArray();

        $this->assertContains( $secondOrder->id, $ids );
        $this->assertNotContains( $firstOrder->id, $ids );
    }

    /**
     * Without a current store, no filter is applied. This is the behaviour
     * expected from console commands and scheduled tasks.
     */
    public function test_no_filter_is_applied_without_a_current_store(): void
    {
        $firstStore = Store::factory()->create();
        $secondStore = Store::factory()->create();

        $before = Order::query()->count();

        app( StoreService::class )->defineCurrentStore( $firstStore );
        $this->makeOrder();

        app( StoreService::class )->defineCurrentStore( $secondStore );
        $this->makeOrder();

        app( StoreService::class )->defineCurrentStore( null );

        $this->assertEquals( $before + 2, Order::query()->count() );
    }

    /**
     * A chain store sees the records of the other chain stores of its brand.
     */
    public function test_a_chain_store_sees_the_records_of_its_sibling_stores(): void
    {
        $headquarter = Store::factory()->create( [ 'type' => Store::TYPE_CHAIN, 'status' => Store::STATUS_ACTIVE ] );
        $chain = Store::factory()->create( [ 'type' => Store::TYPE_CHAIN, 'parent_id' => $headquarter->id, 'status' => Store::STATUS_ACTIVE ] );
        $sibling = Store::factory()->create( [ 'type' => Store::TYPE_CHAIN, 'parent_id' => $headquarter->id, 'status' => Store::STATUS_ACTIVE ] );

        app( StoreService::class )->defineCurrentStore( $sibling );
        $siblingOrder = $this->makeOrder();

        app( StoreService::class )->defineCurrentStore( $chain );

        $ids = Order::query()->pluck( 'id' )->toArray();

        $this->assertContains( $siblingOrder->id, $ids );
    }
}
