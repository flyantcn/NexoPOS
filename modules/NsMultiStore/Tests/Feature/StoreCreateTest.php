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

        // modules:migrate 在 Linux 因 ucwords bug 可能报 "not pending"，表已外部建好时忽略
        try {
            Artisan::call( 'modules:migrate', [ 'moduleNamespace' => 'NsMultiStore' ] );
        } catch ( \Throwable $e ) {
            // 忽略
        }

        ns()->registerGatePermissions();

        Store::whereIn( 'code', [ 'BJ001', 'SH001' ] )->delete();

        $user = User::whereHas( 'roles', function ( $query ) {
            $query->where( 'namespace', Role::ADMIN );
        } )->first();

        // dashboard 视图依赖 $user->attribute->theme，缺这条会 500
        // UserAttribute 的 $fillable 只有 language，必须 forceCreate 才能写 user_id/theme
        if ( $user && ! $user->attribute ) {
            \App\Models\UserAttribute::forceCreate( [
                'user_id'  => $user->id,
                'theme'    => 'light',
                'language' => 'en',
            ] );
            $user->refresh();
        }

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
