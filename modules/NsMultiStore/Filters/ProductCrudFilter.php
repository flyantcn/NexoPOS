<?php

namespace Modules\NsMultiStore\Filters;

use App\Crud\ProductCrud;
use App\Events\CrudHookEvent;
use App\Models\Product;
use Modules\NsMultiStore\Models\StoreProduct;
use Modules\NsMultiStore\Services\StoreService;

/**
 * Extends the core products CRUD with the multi store isolation.
 *
 * - Local products (store_id = N): visible only to that store (and chain siblings)
 * - Global products (store_id IS NULL): visible only if published to the store (nexopos_store_products.is_active = true)
 */
class ProductCrudFilter
{
    public function __construct(
        protected StoreService $storeService
    ) {
    }

    /**
     * Join and filter the products query for the current store.
     */
    public function joinAndFilterProducts(CrudHookEvent $event): void
    {
        if (!$event->crud instanceof ProductCrud) {
            return;
        }

        $storeId = app('current_store_id');
        if ($storeId === null) {
            return; // No store context (e.g., console command), show all
        }

        $visibleStoreIds = $this->storeService->computeVisibleStoreIds(
            \Modules\NsMultiStore\Models\Store::findOrFail($storeId)
        );

        $query = $event->query;

        // Filter products:
        // 1. Local products belonging to visible stores
        // 2. Global products (store_id IS NULL) that are published to current store
        $query->where(function ($q) use ($visibleStoreIds, $storeId) {
            // Local products
            $q->whereIn('nexopos_products.store_id', $visibleStoreIds)
                ->where('nexopos_products.status', Product::STATUS_AVAILABLE);

            // Global products published to current store
            $q->orWhere(function ($sub) use ($storeId) {
                $sub->whereNull('nexopos_products.store_id')
                    ->where('nexopos_products.status', Product::STATUS_AVAILABLE)
                    ->whereExists(function ($exists) use ($storeId) {
                        $exists->from('nexopos_store_products')
                            ->whereColumn('nexopos_store_products.product_id', 'nexopos_products.id')
                            ->where('nexopos_store_products.store_id', $storeId)
                            ->where('nexopos_store_products.is_active', true);
                    });
            });
        });
    }
}