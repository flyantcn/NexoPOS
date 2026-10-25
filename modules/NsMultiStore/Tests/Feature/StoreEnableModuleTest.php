<?php

namespace Modules\NsMultiStore\Tests\Feature;

use App\Models\Permission;
use App\Services\ModulesService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StoreEnableModuleTest extends TestCase
{
    /**
     * The module must be discovered, compliant with PSR-4 and
     * must be able to run its migrations.
     */
    public function test_module_is_discovered_and_migrated(): void
    {
        /**
         * @var ModulesService $modulesService
         */
        $modulesService = app()->make( ModulesService::class );

        $module = $modulesService->get( 'NsMultiStore' );

        $this->assertIsArray( $module, 'The module "NsMultiStore" was not discovered.' );
        $this->assertTrue( $module[ 'psr-4-compliance' ], 'The module "NsMultiStore" is not PSR-4 compliant.' );

        Artisan::call( 'modules:migrate', [ 'moduleNamespace' => 'NsMultiStore' ] );

        $this->assertTrue(
            Schema::hasTable( 'nsmultistore_stores' ),
            'The table "nsmultistore_stores" was not created.'
        );

        collect( [
            'nexopos.create.multistore-stores',
            'nexopos.read.multistore-stores',
            'nexopos.update.multistore-stores',
            'nexopos.delete.multistore-stores',
        ] )->each( function ( $namespace ) {
            $this->assertInstanceOf(
                Permission::class,
                Permission::namespace( $namespace ),
                sprintf( 'The permission "%s" was not created.', $namespace )
            );
        } );
    }
}
