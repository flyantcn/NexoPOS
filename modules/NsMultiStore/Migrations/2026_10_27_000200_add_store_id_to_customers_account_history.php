<?php

use App\Classes\Schema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

/**
 * Records the store where a wallet operation (credit, debit, refund, payment)
 * happened. Used to validate refunds ("a store can only refund the amount it
 * credited") and to build headquarter reports grouped by store.
 */
return new class extends Migration
{
    public function up(): void
    {
        if ( ! Schema::hasTable( 'nexopos_customers_account_history' ) ) {
            return;
        }

        if ( ! Schema::hasColumn( 'nexopos_customers_account_history', 'store_id' ) ) {
            Schema::table( 'nexopos_customers_account_history', function ( Blueprint $table ) {
                $table->integer( 'store_id' )->unsigned()->nullable();
                $table->index( 'store_id' );
            } );
        }
    }

    public function down(): void
    {
        if ( ! Schema::hasTable( 'nexopos_customers_account_history' ) ) {
            return;
        }

        Schema::table( 'nexopos_customers_account_history', function ( Blueprint $table ) {
            if ( Schema::hasColumn( 'nexopos_customers_account_history', 'store_id' ) ) {
                $table->dropIndex( [ 'store_id' ] );
                $table->dropColumn( 'store_id' );
            }
        } );
    }
};
