<?php

use App\Classes\Schema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

/**
 * Members are stored on the shared "nexopos_users" table (a member is a user
 * holding the "nexopos.store.customer" role). This migration adds:
 *
 * - "owner_store_id": the store that opened the member card, used only for
 *   statistics and performance attribution, never for permissions;
 * - "deleted_at": enables the soft delete of members.
 */
return new class extends Migration
{
    public function up(): void
    {
        if ( ! Schema::hasTable( 'nexopos_users' ) ) {
            return;
        }

        if ( ! Schema::hasColumn( 'nexopos_users', 'owner_store_id' ) ) {
            Schema::table( 'nexopos_users', function ( Blueprint $table ) {
                $table->integer( 'owner_store_id' )->unsigned()->nullable();
                $table->index( 'owner_store_id' );
            } );
        }

        if ( ! Schema::hasColumn( 'nexopos_users', 'deleted_at' ) ) {
            Schema::table( 'nexopos_users', function ( Blueprint $table ) {
                $table->timestamp( 'deleted_at' )->nullable();
                $table->index( 'deleted_at' );
            } );
        }
    }

    public function down(): void
    {
        if ( ! Schema::hasTable( 'nexopos_users' ) ) {
            return;
        }

        Schema::table( 'nexopos_users', function ( Blueprint $table ) {
            if ( Schema::hasColumn( 'nexopos_users', 'owner_store_id' ) ) {
                $table->dropIndex( [ 'owner_store_id' ] );
                $table->dropColumn( 'owner_store_id' );
            }

            if ( Schema::hasColumn( 'nexopos_users', 'deleted_at' ) ) {
                $table->dropIndex( [ 'deleted_at' ] );
                $table->dropColumn( 'deleted_at' );
            }
        } );
    }
};
