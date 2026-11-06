<?php

namespace Modules\NsMultiStore\Http\Controllers;

use App\Exceptions\NotAllowedException;
use App\Models\Product;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\NsMultiStore\Models\Store;
use Modules\NsMultiStore\Services\ProductIsolationService;
use Modules\NsMultiStore\Services\StoreService;

class ProductIsolationController extends \App\Http\Controllers\Controller
{
    public function __construct(
        protected ProductIsolationService $productService,
        protected StoreService $storeService
    ) {
        $this->middleware( 'auth:sanctum' );
        $this->middleware( 'ns.store' );
    }

    /**
     * 当前店铺可见商品列表
     */
    public function index(): JsonResponse
    {
        $storeId = $this->storeService->getCurrentStoreId();

        if ( ! $storeId ) {
            return response()->json( [ 'data' => [] ] );
        }

        $products = $this->productService->listForStore( $storeId )
            ->with( [ 'category', 'unitGroup' ] )
            ->get()
            ->map( function ( $product ) use ( $storeId ) {
                $isGlobal = is_null( $product->store_id );
                $storeProduct = null;

                if ( $isGlobal ) {
                    $storeProduct = \Modules\NsMultiStore\Models\StoreProduct::where( 'store_id', $storeId )
                        ->where( 'product_id', $product->id )
                        ->first();
                }

                return [
                    'id'             => $product->id,
                    'name'           => $product->name,
                    'sku'            => $product->sku,
                    'scope'          => $isGlobal ? 'global' : 'local',
                    'store_id'       => $product->store_id,
                    'prices'         => [
                        'default'   => (float) $product->price,
                        'effective' => $storeProduct?->price_override ?? $storeProduct?->sale_price_override ?? (float) $product->price,
                    ],
                    'store_quantity' => $storeProduct?->quantity ?? 0,
                    'is_active'      => $product->status === Product::STATUS_AVAILABLE,
                    'is_published'   => $isGlobal ? (bool) $storeProduct?->is_active : true,
                ];
            } );

        return response()->json( [ 'data' => $products ] );
    }

    /**
     * 后台管理：全局商品池
     */
    public function globalPool(): JsonResponse
    {
        $this->assertSuperAdmin();

        $products = $this->productService->listGlobalPool()
            ->with( [ 'category', 'unitGroup' ] )
            ->get()
            ->map( fn ( $p ) => [
                'id'       => $p->id,
                'name'     => $p->name,
                'sku'      => $p->sku,
                'is_active'=> $p->status === Product::STATUS_AVAILABLE,
            ] );

        return response()->json( [ 'data' => $products ] );
    }

    /**
     * 后台管理：新建全局商品
     */
    public function storeGlobal( Request $request ): JsonResponse
    {
        $this->assertSuperAdmin();

        $data = $request->validate( [
            'name'        => 'required|string|max:255',
            'sku'         => 'required|string|max:255|unique:nexopos_products,sku',
            'category_id' => 'nullable|integer|exists:nexopos_categories,id',
            'unit_group'  => 'nullable|integer|exists:nexopos_unit_groups,id',
            'price'       => 'required|numeric|min:0',
            'cost'        => 'nullable|numeric|min:0',
            'stock_management' => 'nullable|in:enabled,disabled',
            'status'      => 'nullable|in:available,unavailable',
            'description' => 'nullable|string',
        ] );

        $data[ 'author_id' ] = Auth::id();
        $product = $this->productService->createGlobalProduct( $data );

        return response()->json( [ 'data' => $product ], 201 );
    }

    /**
     * 后台管理：新建门店本地商品
     */
    public function storeLocal( Request $request ): JsonResponse
    {
        $storeId = $this->storeService->getCurrentStoreId();

        if ( ! $storeId ) {
            throw new NotAllowedException( __m( '请先选择店铺', 'NsMultiStore' ) );
        }

        $data = $request->validate( [
            'name'        => 'required|string|max:255',
            'sku'         => 'required|string|max:255|unique:nexopos_products,sku',
            'category_id' => 'nullable|integer|exists:nexopos_categories,id',
            'unit_group'  => 'nullable|integer|exists:nexopos_unit_groups,id',
            'price'       => 'required|numeric|min:0',
            'cost'        => 'nullable|numeric|min:0',
            'stock_management' => 'nullable|in:enabled,disabled',
            'status'      => 'nullable|in:available,unavailable',
            'description' => 'nullable|string',
        ] );

        $data[ 'author_id' ] = Auth::id();
        $product = $this->productService->createStoreLocalProduct( $storeId, $data );

        return response()->json( [ 'data' => $product ], 201 );
    }

    /**
     * 本店上架全局商品
     */
    public function publish( Request $request, Product $product ): JsonResponse
    {
        $storeId = $this->storeService->getCurrentStoreId();

        if ( ! $storeId ) {
            throw new NotAllowedException( __m( '请先选择店铺', 'NsMultiStore' ) );
        }

        $storeProduct = $this->productService->publishToStore( $storeId, $product->id );

        return response()->json( [ 'data' => $storeProduct ] );
    }

    /**
     * 本店下架
     */
    public function unpublish( Request $request, Product $product ): JsonResponse
    {
        $storeId = $this->storeService->getCurrentStoreId();

        if ( ! $storeId ) {
            throw new NotAllowedException( __m( '请先选择店铺', 'NsMultiStore' ) );
        }

        $this->productService->unpublishFromStore( $storeId, $product->id );

        return response()->json( [ 'message' => __m( '已下架', 'NsMultiStore' ) ] );
    }

    /**
     * 全局软下架（仅 super-admin）
     */
    public function deactivate( Request $request, Product $product ): JsonResponse
    {
        $this->assertSuperAdmin();
        $this->productService->deactivateGlobalProduct( $product->id );

        return response()->json( [ 'message' => __m( '全局商品已下架', 'NsMultiStore' ) ] );
    }

    /**
     * 库存调整
     */
    public function adjustInventory( Request $request, Product $product ): JsonResponse
    {
        $storeId = $this->storeService->getCurrentStoreId();

        if ( ! $storeId ) {
            throw new NotAllowedException( __m( '请先选择店铺', 'NsMultiStore' ) );
        }

        $data = $request->validate( [
            'delta'      => 'required|numeric',
            'operation'  => 'required|string|in:added,removed,adjustment',
        ] );

        $this->productService->syncStoreInventory(
            $storeId,
            $product->id,
            (float) $data[ 'delta' ],
            $data[ 'operation' ]
        );

        return response()->json( [ 'message' => __m( '库存已调整', 'NsMultiStore' ) ] );
    }

    protected function assertSuperAdmin(): void
    {
        $user = Auth::user();

        if ( ! $user || ! $this->storeService->hasAccessToAllStores( $user ) ) {
            throw new NotAllowedException( __m( '仅超级管理员可操作', 'NsMultiStore' ) );
        }
    }
}