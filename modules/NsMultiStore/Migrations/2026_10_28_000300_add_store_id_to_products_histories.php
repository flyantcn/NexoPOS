<?php

use App\Classes\Schema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if ( ! Schema::hasColumn( 'nexopos_products_histories', 'store_id' ) ) {
            Schema::table( 'nexopos_products_histories', function ( Blueprint $table ) {
                $table->unsignedBigInteger( 'store_id' )->nullable()->after( 'product_id' );
                $table->index( 'store_id' );
            } );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if ( Schema::hasColumn( 'nexopos_products_histories', 'store_id' ) ) {
            Schema::table( 'nexopos_products_histories', function ( Blueprint $table ) {
                $table->dropIndex( [ 'store_id' ] );
                $table->dropColumn( 'store_id' );
            } );
        }
    }
};