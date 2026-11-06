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
        if ( ! Schema::hasColumn( 'nexopos_products', 'store_id' ) ) {
            Schema::table( 'nexopos_products', function ( Blueprint $table ) {
                $table->unsignedBigInteger( 'store_id' )->nullable()->after( 'author_id' );
                $table->index( 'store_id' );
            } );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if ( Schema::hasColumn( 'nexopos_products', 'store_id' ) ) {
            Schema::table( 'nexopos_products', function ( Blueprint $table ) {
                $table->dropIndex( [ 'store_id' ] );
                $table->dropColumn( 'store_id' );
            } );
        }
    }
};