<?php

namespace Modules\NsMultiStore\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\NsMultiStore\Models\Store;
use Modules\NsMultiStore\Models\StoreProduct;

/**
 * 补齐历史全局商品到各店铺目录（幂等）。
 *
 * 将 store_id IS NULL 的全局商品，为所有已存在的店铺创建 nexopos_store_products 记录。
 * 重复执行不会产生副作用。
 */
class SyncGlobalProductsCommand extends Command
{
    protected $signature = 'ns-multistore:sync-global-products
                            {--store= : 指定店铺 ID，默认同步所有店铺}
                            {--dry-run : 仅显示将要同步的数量，不写入}';

    protected $description = '同步全局商品到各店铺目录（幂等）';

    public function handle(): int
    {
        $storeId = $this->option( 'store' );
        $dryRun  = $this->option( 'dry-run' );

        $query = Store::where( 'status', Store::STATUS_ACTIVE );

        if ( $storeId ) {
            $query->where( 'id', $storeId );
        }

        $storeIds = $query->pluck( 'id' )->all();

        if ( empty( $storeIds ) ) {
            $this->warn( '没有找到活跃店铺' );

            return self::FAILURE;
        }

        $globalProducts = Product::whereNull( 'store_id' )->pluck( 'id' )->all();

        if ( empty( $globalProducts ) ) {
            $this->info( '没有全局商品需要同步' );

            return self::SUCCESS;
        }

        $this->info( "找到 " . count( $globalProducts ) . " 个全局商品，" . count( $storeIds ) . " 个店铺" );

        $toCreate = 0;
        $existing = 0;

        foreach ( $storeIds as $sid ) {
            foreach ( $globalProducts as $pid ) {
                $exists = DB::table( 'nexopos_store_products' )
                    ->where( 'store_id', $sid )
                    ->where( 'product_id', $pid )
                    ->exists();

                if ( $exists ) {
                    $existing++;
                    continue;
                }

                if ( $dryRun ) {
                    $toCreate++;
                    continue;
                }

                DB::table( 'nexopos_store_products' )->insert( [
                    'store_id'       => $sid,
                    'product_id'     => $pid,
                    'is_active'      => true,
                    'quantity'       => 0,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ] );
                $toCreate++;
            }
        }

        if ( $dryRun ) {
            $this->info( "[Dry-run] 将新增 {$toCreate} 条记录，已存在 {$existing} 条" );
        } else {
            $this->info( "同步完成：新增 {$toCreate} 条，已存在 {$existing} 条" );
        }

        return self::SUCCESS;
    }
}