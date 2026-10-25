<?php

namespace Modules\NsMultiStore\Tests\Feature;

use App\Models\Register;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\NsMultiStore\Models\Store;
use Modules\NsMultiStore\Models\UserStoreRelation;
use Modules\NsMultiStore\Services\StoreService;
use Tests\TestCase;

class DataBackfillTest extends TestCase
{
    private int $userId;

    private string $migrationFile;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call( 'modules:migrate', [ 'moduleNamespace' => 'NsMultiStore' ] );

        ns()->registerGatePermissions();

        $this->userId = User::whereHas( 'roles', fn ( $query ) => $query->where( 'namespace', Role::ADMIN ) )->first()->id;

        app( StoreService::class )->defineCurrentStore( null );

        $this->migrationFile = base_path( 'modules/NsMultiStore/Migrations/2026_10_24_000200_backfill_existing_data_with_store_id.php' );

        DB::table( 'nexopos_registers' )->delete();
        UserStoreRelation::query()->delete();
        Store::query()->delete();
    }

    /**
     * Create a legacy register whose "store_id" is null, exactly like a row
     * created before the multi store module was installed.
     */
    private function makeLegacyRegister(): Register
    {
        $register = new Register;
        $register->name = 'Legacy Register';
        $register->author_id = $this->userId;
        $register->save();

        return $register;
    }

    /**
     * Run the backfill migration in isolation.
     */
    private function runBackfill(): void
    {
        $migration = require $this->migrationFile;

        $migration->up();
    }

    /**
     * Legacy rows are assigned to the main store so they stay visible once
     * the store scope becomes active.
     */
    public function test_legacy_rows_are_backfilled_to_the_main_store(): void
    {
        $mainStore = Store::factory()->create( [ 'status' => Store::STATUS_ACTIVE ] );

        $register = $this->makeLegacyRegister();

        $this->assertNull( $register->store_id );

        $this->runBackfill();

        $this->assertSame( $mainStore->id, (int) $register->fresh()->store_id );
    }

    /**
     * The main store is the first active store, not any other store.
     */
    public function test_the_main_store_is_the_first_active_store(): void
    {
        $firstStore = Store::factory()->create( [ 'status' => Store::STATUS_ACTIVE ] );
        Store::factory()->create( [ 'status' => Store::STATUS_ACTIVE ] );

        $register = $this->makeLegacyRegister();

        $this->runBackfill();

        $this->assertSame( $firstStore->id, (int) $register->fresh()->store_id );
    }

    /**
     * When no store exists yet, the legacy rows keep a null store id so they
     * remain globally visible.
     */
    public function test_legacy_rows_are_left_untouched_without_a_store(): void
    {
        $register = $this->makeLegacyRegister();

        $this->runBackfill();

        $this->assertNull( $register->fresh()->store_id );
    }
}
