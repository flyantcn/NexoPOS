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
        Schema::createIfMissing( 'nsmultistore_users_stores_relations', function ( Blueprint $table ) {
            $table->id();
            $table->integer( 'user_id' )->unsigned();
            $table->unsignedBigInteger( 'store_id' );
            $table->string( 'role_namespace' )->nullable();
            $table->integer( 'author_id' )->unsigned()->nullable();
            $table->string( 'uuid' )->nullable();
            $table->timestamps();

            $table->unique( [ 'user_id', 'store_id' ] );
            $table->index( 'store_id' );

            /**
             * The store is constrained as the module owns both tables. The user
             * isn't constrained as NexoPOS deletes the related records of a
             * user from the application.
             */
            $table->foreign( 'store_id' )
                ->references( 'id' )
                ->on( 'nsmultistore_stores' );
        } );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists( 'nsmultistore_users_stores_relations' );
    }
};
