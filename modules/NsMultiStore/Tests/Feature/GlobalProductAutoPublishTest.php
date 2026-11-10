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

        // modules:migrate 在 Linux 因 ucwords bug 可能报 "not pending"，表已外部建好时忽略
        try {
            Artisan::call( 'modules:migrate', [ 'moduleNamespace' => 'NsMultiStore' ] );
        } catch ( \Throwable $e ) {
            // 忽略
        }

        ns()->registerGatePermissions();

        $admin = User::whereHas( 'roles', fn ( $query ) => $query->where( 'namespace', Role::ADMIN ) )->first();
        $this->actingAs( $admin );

        // 与 StoreCreateTest 保持一致：dashboard 视图依赖 $user->attribute->theme
        if ( $admin && ! $admin->attribute ) {
            \App\Models\UserAttribute::forceCreate( [
                'user_id'  => $admin->id,
                'theme'    => 'light',
                'language' => 'en',
            ] );
            $admin->refresh();
        }        // 与 StoreCreateTest 保持一致：dashboard 视图依赖 $user->attribute->theme
        if ( $admin && ! $admin->attribute ) {
            \App\Models\UserAttribute::forceCreate( [
                'user_id'  => $admin->id,
                'theme'    => 'light',
                'language' => 'en',
            ] );
            $admin->refresh();
        }

        // 确保 admin 有 UserAttribute（dashboard 视图依赖 $user->attribute->theme）
        if ( ! $admin->attribute ) {
            \App\Models\UserAttribute::create( [
                'user_id'  => $admin->id,
                'theme'    => 'light',
                'language' => 'en',
            ] );
        }

        // factory 依赖：UnitGroup / Unit / TaxGroup
        if ( \App\Models\UnitGroup::count() === 0 ) {
            \App\Models\UnitGroup::factory()->create();
        }
        if ( \App\Models\Unit::count() === 0 ) {
            \App\Models\Unit::factory()->create( [
                'group_id'  => \App\Models\UnitGroup::first()->id,
                'base_unit' => true,
            ] );
        }
        if ( \App\Models\TaxGroup::count() === 0 ) {
            \App\Models\TaxGroup::factory()->create();
        }

        // 测试隔离：清空店铺和上架记录，避免前面测试累积污染断言
        \Modules\NsMultiStore\Models\StoreProduct::query()->delete();
        \Modules\NsMultiStore\Models\Store::query()->delete();
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
        // Product 模型未定义 fillable/guarded，走 forceCreate 绕过 mass assignment 保护
        // 这样 'store_id' 会真正写入，且触发 Product::created 事件
        $product = \App\Models\Product::forceCreate(
            \App\Models\Product::factory()->raw( [
                'store_id' => $storeId,
            ] )
        );

        return $product->id;
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

        $this->assertDatabaseMissing( 'nexopos_store_products', [
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

        \App\Models\Product::find( $productId )->forceFill([ 'name' => 'Updated Name' ])->save();

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

        $this->assertDatabaseHas( 'nexopos_products', [ 'id' => $productId ] );
        $this->assertCount( 0, StoreProduct::where( 'product_id', $productId )->get() );
    }
}

