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
        Schema::createIfMissing( 'nsmultistore_stores', function ( Blueprint $table ) {
            $table->id();
            $table->string( 'name' );
            $table->string( 'code' )->unique();
            $table->enum( 'type', [ 'single', 'chain', 'franchise' ] )->default( 'single' );
            $table->unsignedBigInteger( 'parent_id' )->nullable();
            $table->enum( 'status', [ 'active', 'disabled' ] )->default( 'active' );
            $table->text( 'description' )->nullable();
            $table->integer( 'author_id' )->unsigned()->nullable();
            $table->string( 'uuid' )->nullable();
            $table->timestamps();

            $table->index( 'type' );
            $table->index( 'status' );
            $table->index( 'parent_id' );

            /**
             * Only the stores hierarchy is enforced by the database. The author
             * isn't constrained as NexoPOS handles the deletion of the related
             * records from the application.
             */
            $table->foreign( 'parent_id' )
                ->references( 'id' )
                ->on( 'nsmultistore_stores' );
        } );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists( 'nsmultistore_stores' );
    }
};
