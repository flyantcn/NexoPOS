<?php

use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Modules\NsMultiStore\Http\Controllers\ProductIsolationController;

Route::prefix( 'ns-multistore' )->middleware( [
    SubstituteBindings::class,
] )->group( function (): void {
    Route::prefix( 'products' )->middleware( [ 'auth:sanctum', 'ns.store' ] )->group( function (): void {
        Route::get( '/', [ ProductIsolationController::class, 'index' ] )->name( ns()->routeName( 'ns.multistore.products.index' ) );
        Route::get( '/global-pool', [ ProductIsolationController::class, 'globalPool' ] )->name( ns()->routeName( 'ns.multistore.products.global-pool' ) );
        Route::post( '/global', [ ProductIsolationController::class, 'storeGlobal' ] )->name( ns()->routeName( 'ns.multistore.products.store-global' ) );
        Route::post( '/local', [ ProductIsolationController::class, 'storeLocal' ] )->name( ns()->routeName( 'ns.multistore.products.store-local' ) );
        Route::post( '/{product}/publish', [ ProductIsolationController::class, 'publish' ] )->name( ns()->routeName( 'ns.multistore.products.publish' ) );
        Route::post( '/{product}/unpublish', [ ProductIsolationController::class, 'unpublish' ] )->name( ns()->routeName( 'ns.multistore.products.unpublish' ) );
        Route::patch( '/{product}/deactivate', [ ProductIsolationController::class, 'deactivate' ] )->name( ns()->routeName( 'ns.multistore.products.deactivate' ) );
        Route::post( '/{product}/inventory', [ ProductIsolationController::class, 'adjustInventory' ] )->name( ns()->routeName( 'ns.multistore.products.inventory' ) );
    } );
} );