<?php

use App\Classes\Schema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    /**
     * The tables that must be isolated by store.
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
     */
    public function up(): void
    {
        foreach ( $this->tables as $table ) {
            if ( ! Schema::hasColumn( $table, 'store_id' ) ) {
                Schema::table( $table, function ( Blueprint $table ) {
                    $table->integer( 'store_id' )->unsigned()->nullable();
                    $table->index( 'store_id' );
                } );
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ( $this->tables as $table ) {
            if ( Schema::hasColumn( $table, 'store_id' ) ) {
                Schema::table( $table, function ( Blueprint $table ) {
                    $table->dropIndex( [ 'store_id' ] );
                    $table->dropColumn( 'store_id' );
                } );
            }
        }
    }
};
