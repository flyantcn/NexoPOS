<?php

namespace Modules\NsMultiStore\Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Modules\NsMultiStore\Models\Store;
use Tests\TestCase;
use Tests\Traits\WithCrud;

class StoreCreateTest extends TestCase
{
    use WithCrud;

    /**
     * Each test owns its activation and migrations so it can run
     * without relying on another test file.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call( 'modules:migrate', [ 'moduleNamespace' => 'NsMultiStore' ] );

        /**
         * Gates are registered while the application boots. Since the module
         * permissions are created after that, they must be registered again
         * for the current process.
         */
        ns()->registerGatePermissions();

        /**
         * The stores created by a previous run must not conflict with
         * the codes used by this test.
         */
        Store::whereIn( 'code', [ 'BJ001', 'SH001' ] )->delete();

        $user = User::whereHas( 'roles', function ( $query ) {
            $query->where( 'namespace', Role::ADMIN );
        } )->first();

        $this->actingAs( $user );
        Sanctum::actingAs( $user, [ '*' ] );
    }

    /**
     * A store must be created using the CRUD API.
     */
    public function test_a_store_can_be_created(): void
    {
        $response = $this->submitRequest( 'ns.multistore.stores', [
            'name' => 'Beijing Main Store',
            'general' => [
                'code' => 'BJ001',
                'type' => Store::TYPE_CHAIN,
                'status' => Store::STATUS_ACTIVE,
            ],
        ] );

        $response->assertStatus( 200 );
        $response->assertJson( [ 'status' => 'success' ] );

        $this->assertDatabaseHas( 'nsmultistore_stores', [
            'code' => 'BJ001',
            'name' => 'Beijing Main Store',
            'type' => Store::TYPE_CHAIN,
        ] );
    }

    /**
     * A store code is unique across all the stores.
     */
    public function test_a_store_code_must_be_unique(): void
    {
        Store::factory()->create( [ 'code' => 'SH001' ] );

        $response = $this->submitRequest( 'ns.multistore.stores', [
            'name' => 'Shanghai Secondary Store',
            'general' => [
                'code' => 'SH001',
                'type' => Store::TYPE_CHAIN,
                'status' => Store::STATUS_ACTIVE,
            ],
        ] );

        $response->assertStatus( 422 );

        $this->assertEquals( 1, Store::where( 'code', 'SH001' )->count() );
    }

    /**
     * The store list page must be reachable for a user having
     * the read permission.
     */
    public function test_the_stores_list_page_is_reachable(): void
    {
        $response = $this->get( '/dashboard/multistore/stores' );

        $response->assertStatus( 200 );
        $response->assertSee( __m( 'Stores List', 'NsMultiStore' ) );
    }
}
