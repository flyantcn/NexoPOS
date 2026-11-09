<?php

namespace Modules\NsMultiStore\Tests\Feature;

use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use App\Services\ProductService;
use Modules\NsMultiStore\Models\Store;
use Modules\NsMultiStore\Models\StoreProduct;
use Tests\TestCase;

class GlobalProductAutoPublishTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call( 'modules:migrate', [ 'moduleNamespace' => 'NsMultiStore' ] );
        ns()->registerGatePermissions();

        $admin = User::whereHas( 'roles', fn ( $query ) => $query->where( 'namespace', Role::ADMIN ) )->first();
        $this->actingAs( $admin );
    }

    /**
     * 创建一个测试所需的商品分类。
     */
    private function makeCategory(): int
    {
        return ProductCategory::factory()->create()->id;
    }

    /**
     * 调用 ProductService::create() 创建一个全局商品（store_id = null）。
     */
    private function createGlobalProduct(?int $storeId = null): int
    {
        $productService = app( ProductService::class );

        $result = $productService->create( [
            'category_id'        => $this->makeCategory(),
            'product_type'       => 'product',
            'name'               => 'Test Global Product ' . fake()->unique()->word(),
            'store_id'           => $storeId,
            'status'             => 'available',
            'stock_management'   => 'enabled',
            'price'              => 100.00,
            'cost'               => 80.00,
        ] );

        return $result[ 'data' ][ 'product' ]->id;
    }

    /**
     * 正向：创建全局商品后，所有活跃店铺都应有 nexopos_store_products.is_active = 1 记录。
     */
    public function test_global_product_is_auto_published_to_all_active_stores(): void
    {
        Store::factory()->count( 3 )->create( [ 'status' => Store::STATUS_ACTIVE ] );

        $productId = $this->createGlobalProduct( null );

        $activeStoreIds = Store::where( 'status', Store::STATUS_ACTIVE )->pluck( 'id' )->all();

        $this->assertCount( count( $activeStoreIds ), StoreProduct::where( 'product_id', $productId )->get() );

        foreach ( $activeStoreIds as $storeId ) {
	    $this->assertTrue(
    		StoreProduct::where( 'store_id', $storeId )
        	    ->where( 'product_id', $productId )
        	    ->where( 'is_active', true )
        	    ->exists()
	    );
        }
    }

    /**
     * 负向1：创建本地商品（store_id = N）不应触发上架。
     */
    public function test_local_product_is_not_auto_published(): void
    {
        $store = Store::factory()->create( [ 'status' => Store::STATUS_ACTIVE ] );
        Store::factory()->create( [ 'status' => Store::STATUS_ACTIVE ] );

        $productId = $this->createGlobalProduct( $store->id );

        $this->assertDatabaseMissing( 'ns_nexopos_store_products', [
            'product_id' => $productId,
        ] );
    }

    /**
     * 负向2：更新全局商品不应触发新的上架记录（updated 不触发 created）。
     */
    public function test_updating_global_product_does_not_republish(): void
    {
        Store::factory()->count( 2 )->create( [ 'status' => Store::STATUS_ACTIVE ] );

        $productId = $this->createGlobalProduct( null );

        \App\Models\Product::find( $productId )->update( [ 'name' => 'Updated Name' ] );

        $activeStoreIds = Store::where( 'status', Store::STATUS_ACTIVE )->pluck( 'id' )->all();

        // 上架记录数应保持不变，不重复插入
        $this->assertCount( count( $activeStoreIds ), StoreProduct::where( 'product_id', $productId )->get() );
    }

    /**
     * 边界：没有活跃店铺时创建全局商品不应报错。
     */
    public function test_global_product_without_active_stores_does_not_error(): void
    {
        // 不创建任何店铺
        $productId = $this->createGlobalProduct( null );

        $this->assertDatabaseHas( 'ns_nexopos_products', [ 'id' => $productId ] );
        $this->assertCount( 0, StoreProduct::where( 'product_id', $productId )->get() );
    }
}
