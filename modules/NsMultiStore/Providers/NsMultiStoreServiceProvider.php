<?php

namespace Modules\NsMultiStore\Providers;

use App\Classes\Hook;
use App\Events\CrudHookEvent;
use App\Models\Customer;
use App\Models\CustomerAccountHistory;
use App\Models\Product;
use App\Services\Helper;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Kernel as HttpFoundationKernel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use Modules\NsMultiStore\Console\Commands\SyncGlobalProductsCommand;
use Modules\NsMultiStore\Filters\CustomerCrudFilter;
use Modules\NsMultiStore\Filters\DashboardMenusFilter;
use Modules\NsMultiStore\Filters\ProductCrudFilter;
use Modules\NsMultiStore\Middleware\ResolveCurrentStore;
use Modules\NsMultiStore\Models\Store;
use Modules\NsMultiStore\Models\StoreProduct;
use Modules\NsMultiStore\Scopes\StoreScope;
use Modules\NsMultiStore\Services\MemberService;
use Modules\NsMultiStore\Services\ProductIsolationService;
use Modules\NsMultiStore\Services\StoreService;
use Modules\NsMultiStore\Services\StoreSwitcher;

class NsMultiStoreServiceProvider extends ServiceProvider
{
    /**
     * Prevent duplicate hook registration across multiple register() calls.
     */
    private static bool $hooksRegistered = false;

    /**
     * Register the module services.
     */
    public function register(): void
    {
        if ( self::$hooksRegistered ) {
            return;
        }
        self::$hooksRegistered = true;
        $this->app->singleton( StoreService::class );
        $this->app->singleton( ProductIsolationService::class );
        $this->app->singleton( StoreScope::class );

        /**
         * The store used for the current request. Both values remain "null"
         * until the "ResolveCurrentStore" middleware resolves the store.
         */
        $this->app->bind( 'current_store_id', fn ( $app ) => $app->make( StoreService::class )->getCurrentStoreId() );
        $this->app->bind( 'current_store', fn ( $app ) => $app->make( StoreService::class )->getCurrentStore() );
        $this->app->bind( 'visible_store_ids', fn ( $app ) => $app->make( StoreService::class )->getVisibleStoreIds() );

        Hook::addFilter( 'ns-dashboard-menus', [ DashboardMenusFilter::class, 'register' ], 30 );
        Hook::addFilter( 'ns-dashboard-header-tools', [ StoreSwitcher::class, 'render' ], 10 );

        /**
         * The customers CRUD is a core resource, its "Opening Store" column
         * is injected through the CRUD hooks instead of editing the core class.
         */
        Hook::addFilter( 'App\Crud\CustomerCrud@getColumns', [ CustomerCrudFilter::class, 'addOpeningStoreColumn' ], 10 );
        Event::listen( CrudHookEvent::class, [ CustomerCrudFilter::class, 'joinOpeningStore' ] );

        /**
         * The products CRUD needs store isolation: local products + published global products.
         */
        Event::listen( CrudHookEvent::class, [ ProductCrudFilter::class, 'joinAndFilterProducts' ] );

        /**
         * After product creation: if global product, create store_products records for all stores.
         * Note: filterPostInputs is handled in ProductCrud::filterPostInputs() to run before Hook::filter.
         * afterPost receives: ($unfilteredInputs, $entry, $filteredInputs) as 3 separate args
         */
        Hook::addAction( 'App\Crud\ProductCrud@afterPost', function ( $unfilteredInputs, Product $product, $filteredInputs ) {
            $isGlobal = ! empty( $unfilteredInputs[ 'is_global_product' ] ?? false );

            if ( $isGlobal && is_null( $product->store_id ) ) {
                $storeIds = Store::where( 'status', Store::STATUS_ACTIVE )->pluck( 'id' )->all();
                $authorId = auth()->id();

                foreach ( $storeIds as $storeId ) {
                    StoreProduct::firstOrCreate(
                        [ 'store_id' => $storeId, 'product_id' => $product->id ],
                        [
                            'is_active' => true,
                            'quantity'  => 0,
                            'author_id' => $authorId,
                        ]
                    );
                }
            }
        }, 10, 3 );

        /**
         * After product update: sync store_products when global/local status changes.
         * afterPut receives: ($unfilteredInputs, $entry, $filteredInputs) as 3 separate args
         */
        Hook::addAction( 'App\Crud\ProductCrud@afterPut', function ( $unfilteredInputs, Product $product, $filteredInputs ) {
            $wasGlobal = is_null( $product->getOriginal( 'store_id' ) );
            $isGlobalNow = is_null( $product->store_id );

            // Only act if global status changed
            if ( $wasGlobal === $isGlobalNow ) {
                return;
            }

            // Local -> Global: create store_products for all active stores
            if ( $isGlobalNow ) {
                $storeIds = Store::where( 'status', Store::STATUS_ACTIVE )->pluck( 'id' )->all();
                $authorId = auth()->id();

                foreach ( $storeIds as $storeId ) {
                    StoreProduct::firstOrCreate(
                        [ 'store_id' => $storeId, 'product_id' => $product->id ],
                        [
                            'is_active' => true,
                            'quantity'  => 0,
                            'author_id' => $authorId,
                        ]
                    );
                }
            }
            // Global -> Local: delete store_products records (product now belongs to one store)
            else {
                StoreProduct::where( 'product_id', $product->id )->delete();
            }
        }, 10, 3 );

        /**
         * Inject "is_global_product" field into product form for Chain HQ users.
         * Only visible to users who can manage global products (Chain HQ only).
         * Single stores and franchises can only create local products.
         */
        Hook::addFilter( 'ns-products-crud-form', function ( $form, $entry = null ) {
            $currentStore = app( 'current_store' );
            $canManageGlobal = $currentStore && $currentStore->canManageGlobalProducts();

            if ( ! $canManageGlobal ) {
                return $form; // Only Chain HQ can create global products
            }

            // Add the field at the beginning of identification tab
            $form[ 'variations' ][0][ 'tabs' ][ 'identification' ][ 'fields' ] = array_merge( [
                [
                    'type' => 'switch',
                    'name' => 'is_global_product',
                    'label' => __m( 'Global Product', 'NsMultiStore' ),
                    'description' => __m( 'Create as global product (visible to all stores after publishing). If off, product belongs to current store only.', 'NsMultiStore' ),
                    'options' => Helper::boolToOptions(
                        true: __m( 'Yes', 'NsMultiStore' ),
                        false: __m( 'No', 'NsMultiStore' ),
                    ),
                    'value' => $entry && is_null( $entry->store_id ) ? true : false,
                    'validation' => 'boolean',
                ],
            ], $form[ 'variations' ][0][ 'tabs' ][ 'identification' ][ 'fields' ] );

            return $form;
        }, 20 );

        /**
         * Remove is_global_product from fillable fields to prevent saving to database.
         * This field is a form helper only, not a model attribute.
         */
        Hook::addFilter( 'App\Crud\ProductCrud@getFillable', function ( $fillable ) {
            return array_filter( $fillable, fn ( $field ) => $field !== 'is_global_product' );
        }, 10 );
    }

    /**
     * Boot the module services.
     */
    public function boot(): void
    {
	
	$this->loadMigrationsFrom( __DIR__ . '/../Migrations' );
	
        /**
         * Member business rules are applied through model events, so every
         * entry point (dashboard CRUD, POS, API) keeps the same behaviour.
         */
        Customer::creating( fn ( Customer $customer ) => $this->app->make( MemberService::class )->assertCanCreate( $customer ) );
        Customer::updating( fn ( Customer $customer ) => $this->app->make( MemberService::class )->assertCanUpdate( $customer ) );
        CustomerAccountHistory::creating( fn ( CustomerAccountHistory $history ) => $this->app->make( MemberService::class )->prepareHistory( $history ) );

        // Register StoreScope for Product model
        Product::addGlobalScope( app( StoreScope::class ) );

        /**
         * 全局商品创建后，自动为所有活跃店铺插入 nexopos_store_products 上架记录。
         * 不侵入核心 ProductService/ProductsController，兜底历史孤儿商品可跑
         * ns-multistore:sync-global-products。
         */
        Product::created( function ( Product $product ): void {
            if ( $product->store_id !== null ) {
                return;
            }

            try {
                $storeIds = Store::where( 'status', Store::STATUS_ACTIVE )->pluck( 'id' )->all();

                foreach ( $storeIds as $storeId ) {
                    StoreProduct::firstOrCreate(
                        [ 'store_id' => $storeId, 'product_id' => $product->id ],
                        [
                            'is_active'  => true,
                            'quantity'   => 0,
                            'author_id'  => Auth::id(),
                        ]
                    );
                }
            } catch ( \Throwable $e ) {
                Log::error(
                    'NsMultiStore: failed to auto-publish global product',
                    [
                        'product_id' => $product->id,
                        'error'      => $e->getMessage(),
                    ]
                );
            }
        } );

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

        // Load API routes
        Route::middleware( 'api' )
            ->prefix( 'api' )
            ->group( __DIR__ . '/../Routes/api.php' );

        // Register console commands
        if ( $this->app->runningInConsole() ) {
            $this->commands( [
                SyncGlobalProductsCommand::class,
            ] );
        }
    }
}
