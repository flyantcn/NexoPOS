<?php

namespace Modules\NsMultiStore\Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Modules\NsMultiStore\Models\Store;
use Modules\NsMultiStore\Services\StoreService;
use Modules\NsMultiStore\Services\StoreSwitcher;
use Modules\NsMultiStore\Tests\Traits\WithEmployees;
use Tests\TestCase;

class StoreSwitcherTest extends TestCase
{
    use WithEmployees;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call( 'modules:migrate', [ 'moduleNamespace' => 'NsMultiStore' ] );

        ns()->registerGatePermissions();

        $this->cleanMultiStoreData();

        app( StoreService::class )->defineCurrentStore( null );
    }

    /**
     * A user having access to several stores sees the switcher.
     */
    public function test_the_switcher_is_rendered_for_a_user_with_multiple_stores(): void
    {
        $firstStore = Store::factory()->create();
        $secondStore = Store::factory()->create();

        $employee = $this->createEmployee( [ $firstStore, $secondStore ] );

        $this->actingAs( $employee );

        app( StoreService::class )->defineCurrentStore( $firstStore );

        $html = StoreSwitcher::render( '' );

        $this->assertStringContainsString( 'ns-store-switcher', $html );
        $this->assertStringContainsString( $firstStore->name, $html );
        $this->assertStringContainsString( $secondStore->name, $html );
        $this->assertStringContainsString( 'data-store-id="' . $firstStore->id . '"', $html );
    }

    /**
     * A user bound to a single store doesn't need a switcher.
     */
    public function test_the_switcher_is_hidden_for_a_single_store_user(): void
    {
        $store = Store::factory()->create();

        $employee = $this->createEmployee( [ $store ] );

        $this->actingAs( $employee );

        app( StoreService::class )->defineCurrentStore( $store );

        $this->assertSame( '', StoreSwitcher::render( '' ) );
    }

    /**
     * Guests don't see the switcher at all.
     */
    public function test_the_switcher_is_hidden_for_guests(): void
    {
        $this->assertSame( '', StoreSwitcher::render( '' ) );
    }

    /**
     * The switcher is displayed on the dashboard header through the
     * "ns-dashboard-header-tools" filter.
     */
    public function test_the_switcher_is_displayed_on_the_dashboard(): void
    {
        Store::factory()->create();
        Store::factory()->create();

        $administrator = User::whereHas( 'roles', fn ( $query ) => $query->where( 'namespace', Role::ADMIN ) )->first();

        $this->actingAs( $administrator );

        $this->get( ns()->route( 'ns.dashboard.home' ) )
            ->assertStatus( 200 )
            ->assertSee( 'ns-store-switcher', false );
    }
}
