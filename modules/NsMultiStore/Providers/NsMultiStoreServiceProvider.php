<?php

namespace Modules\NsMultiStore\Providers;

use App\Classes\Hook;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Kernel as HttpFoundationKernel;
use Illuminate\Support\ServiceProvider;
use Modules\NsMultiStore\Filters\DashboardMenusFilter;
use Modules\NsMultiStore\Middleware\ResolveCurrentStore;
use Modules\NsMultiStore\Services\StoreService;
use Modules\NsMultiStore\Services\StoreSwitcher;

class NsMultiStoreServiceProvider extends ServiceProvider
{
    /**
     * Register the module services.
     */
    public function register(): void
    {
        $this->app->singleton( StoreService::class );

        /**
         * The store used for the current request. Both values remain "null"
         * until the "ResolveCurrentStore" middleware resolves the store.
         */
        $this->app->bind( 'current_store_id', fn ( $app ) => $app->make( StoreService::class )->getCurrentStoreId() );
        $this->app->bind( 'current_store', fn ( $app ) => $app->make( StoreService::class )->getCurrentStore() );
        $this->app->bind( 'visible_store_ids', fn ( $app ) => $app->make( StoreService::class )->getVisibleStoreIds() );

        Hook::addFilter( 'ns-dashboard-menus', [ DashboardMenusFilter::class, 'register' ], 30 );
        Hook::addFilter( 'ns-dashboard-header-tools', [ StoreSwitcher::class, 'render' ], 10 );
    }

    /**
     * Boot the module services.
     */
    public function boot(): void
    {
        /**
         * The current store is resolved after the session has started,
         * therefore the middleware is appended to both groups used by
         * the dashboard and by the API.
         */
        $kernel = $this->app->make( HttpKernel::class );

        if ( $kernel instanceof HttpFoundationKernel ) {
            $kernel->appendMiddlewareToGroup( 'web', ResolveCurrentStore::class );
            $kernel->appendMiddlewareToGroup( 'api', ResolveCurrentStore::class );
        }
    }
}
