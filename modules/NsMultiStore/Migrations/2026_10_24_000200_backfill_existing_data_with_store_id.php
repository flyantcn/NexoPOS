<?php

use App\Classes\Schema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The tables that hold store isolated data and may contain legacy rows
     * created before the "store_id" column was introduced.
     *
     * @var array<int, string>
     */
    private array $tables = [
        'nexopos_orders',
        'nexopos_orders_products',
        'nexopos_products_unit_quantities',
        'nexopos_registers',
        'nexopos_registers_history',
        'nexopos_transactions',
        'nexopos_procurements',
        'nexopos_products_histories',
    ];

    /**
     * Run the migrations.
     *
     * Legacy rows that predate the multi store module have a "null" store id.
     * They are assigned to the main store so they stay visible once the store
     * scope becomes active. Without this, switching to any store would hide
     * every legacy record.
     */
    public function up(): void
    {
        $mainStoreId = $this->resolveMainStoreId();

        if ( $mainStoreId === null ) {
            return;
        }

        foreach ( $this->tables as $table ) {
            if ( ! Schema::hasColumn( $table, 'store_id' ) ) {
                continue;
            }

            DB::table( $table )
                ->whereNull( 'store_id' )
                ->update( [ 'store_id' => $mainStoreId ] );
        }
    }

    /**
     * Reverse the migrations.
     *
     * Note: this only approximates the rollback. Rows created after the
     * backfill and explicitly assigned to the main store are also reset,
     * because the migration cannot distinguish them from the backfilled ones.
     */
    public function down(): void
    {
        $mainStoreId = $this->resolveMainStoreId();

        if ( $mainStoreId === null ) {
            return;
        }

        foreach ( $this->tables as $table ) {
            if ( ! Schema::hasColumn( $table, 'store_id' ) ) {
                continue;
            }

            DB::table( $table )
                ->where( 'store_id', $mainStoreId )
                ->update( [ 'store_id' => null ] );
        }
    }

    /**
     * Resolve the store the legacy data belongs to: the first active store,
     * otherwise the first store ever created.
     */
    private function resolveMainStoreId(): ?int
    {
        if ( ! Schema::hasTable( 'nsmultistore_stores' ) ) {
            return null;
        }

        $store = DB::table( 'nsmultistore_stores' )
            ->where( 'status', 'active' )
            ->orderBy( 'id' )
            ->first();

        if ( $store === null ) {
            $store = DB::table( 'nsmultistore_stores' )
                ->orderBy( 'id' )
                ->first();
        }

        if ( $store === null ) {
            return null;
        }

        return (int) $store->id;
    }
};
