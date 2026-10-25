<?php

use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Modules\NsMultiStore\Http\Controllers\StoreController;
use Modules\NsMultiStore\Http\Controllers\UserStoreController;

Route::middleware( [
    SubstituteBindings::class,
] )->group( function (): void {
    Route::get( '/dashboard/multistore/stores', [ StoreController::class, 'listStores' ] )
        ->name( ns()->routeName( 'ns.multistore.stores.list' ) );

    Route::get( '/dashboard/multistore/stores/create', [ StoreController::class, 'createStore' ] )
        ->name( ns()->routeName( 'ns.multistore.stores.create' ) );

    Route::get( '/dashboard/multistore/stores/edit/{store}', [ StoreController::class, 'editStore' ] )
        ->name( ns()->routeName( 'ns.multistore.stores.edit' ) );

    Route::post( '/dashboard/multistore/stores/switch/{store}', [ StoreController::class, 'switchStore' ] )
        ->name( ns()->routeName( 'ns.multistore.stores.switch' ) );

    Route::get( '/dashboard/multistore/users-stores', [ UserStoreController::class, 'listUserStores' ] )
        ->name( ns()->routeName( 'ns.multistore.users-stores.list' ) );

    Route::get( '/dashboard/multistore/users-stores/create', [ UserStoreController::class, 'createUserStore' ] )
        ->name( ns()->routeName( 'ns.multistore.users-stores.create' ) );

    Route::get( '/dashboard/multistore/users-stores/edit/{relation}', [ UserStoreController::class, 'editUserStore' ] )
        ->name( ns()->routeName( 'ns.multistore.users-stores.edit' ) );
} );
