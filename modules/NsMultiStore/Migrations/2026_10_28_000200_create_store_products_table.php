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
        if ( ! Schema::hasTable( 'nexopos_store_products' ) ) {
            Schema::create( 'nexopos_store_products', function ( Blueprint $table ) {
                $table->id();
                $table->unsignedBigInteger( 'store_id' );
                $table->unsignedBigInteger( 'product_id' );
                $table->boolean( 'is_active' )->default( true );
                $table->decimal( 'quantity', 18, 5 )->default( 0 );
                $table->decimal( 'low_stock_quantity', 18, 5 )->default( 0 );
                $table->boolean( 'stock_alert_enabled' )->default( false );
                $table->decimal( 'price_override', 18, 5 )->nullable();
                $table->decimal( 'sale_price_override', 18, 5 )->nullable();
                $table->unsignedBigInteger( 'author_id' )->nullable();
                $table->json( 'metadata' )->nullable();
                $table->timestamps();
                $table->unique( [ 'store_id', 'product_id' ] );
                $table->index( [ 'store_id', 'is_active' ] );
                $table->index( 'product_id' );
            } );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists( 'nexopos_store_products' );
    }
};