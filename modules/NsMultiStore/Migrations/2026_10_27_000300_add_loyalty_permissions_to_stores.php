<?php

use App\Classes\Schema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

/**
 * Adds the loyalty management flags to the stores table. The data itself is
 * fully shared between stores; only those administrative flags differ.
 */
return new class extends Migration
{
    public function up(): void
    {
        if ( ! Schema::hasTable( 'nsmultistore_stores' ) ) {
            return;
        }

        Schema::table( 'nsmultistore_stores', function ( Blueprint $table ) {
            if ( ! Schema::hasColumn( 'nsmultistore_stores', 'can_configure_loyalty_rules' ) ) {
                $table->boolean( 'can_configure_loyalty_rules' )->default( false );
            }

            if ( ! Schema::hasColumn( 'nsmultistore_stores', 'can_create_local_campaign' ) ) {
                $table->boolean( 'can_create_local_campaign' )->default( false );
            }

            if ( ! Schema::hasColumn( 'nsmultistore_stores', 'can_view_all_members' ) ) {
                $table->boolean( 'can_view_all_members' )->default( true );
            }
        } );
    }

    public function down(): void
    {
        if ( ! Schema::hasTable( 'nsmultistore_stores' ) ) {
            return;
        }

        Schema::table( 'nsmultistore_stores', function ( Blueprint $table ) {
            foreach ( [
                'can_configure_loyalty_rules',
                'can_create_local_campaign',
                'can_view_all_members',
            ] as $column ) {
                if ( Schema::hasColumn( 'nsmultistore_stores', $column ) ) {
                    $table->dropColumn( $column );
                }
            }
        } );
    }
};
