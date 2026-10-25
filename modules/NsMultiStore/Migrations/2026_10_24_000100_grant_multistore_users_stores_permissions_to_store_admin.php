<?php

use App\Classes\Schema;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * The permissions a store administrator needs to manage the employees of
     * the stores he manages. The store scope is enforced at runtime through
     * the role × store matrix, so the global permissions only open the door.
     *
     * @var array<string, string>
     */
    private array $permissions = [
        'nexopos.create.multistore-users-stores' => 'Create Employee Stores',
        'nexopos.read.multistore-users-stores' => 'Read Employee Stores',
        'nexopos.update.multistore-users-stores' => 'Update Employee Stores',
        'nexopos.delete.multistore-users-stores' => 'Delete Employee Stores',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if ( ! Schema::hasTable( 'nexopos_permissions' ) || ! Schema::hasTable( 'nexopos_roles' ) ) {
            return;
        }

        $storeAdmin = Role::namespace( Role::STOREADMIN );

        if ( ! $storeAdmin instanceof Role ) {
            return;
        }

        /**
         * Only grant the permissions that have already been created by the
         * earlier migration, so a partial state never throws.
         */
        $namespaces = array_filter(
            array_keys( $this->permissions ),
            fn( $namespace ) => Permission::namespace( $namespace ) instanceof Permission
        );

        if ( ! empty( $namespaces ) ) {
            $storeAdmin->addPermissions( $namespaces );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if ( ! Schema::hasTable( 'nexopos_permissions' ) || ! Schema::hasTable( 'nexopos_roles' ) ) {
            return;
        }

        $storeAdmin = Role::namespace( Role::STOREADMIN );

        if ( ! $storeAdmin instanceof Role ) {
            return;
        }

        foreach ( array_keys( $this->permissions ) as $namespace ) {
            if ( Permission::namespace( $namespace ) instanceof Permission ) {
                $storeAdmin->removePermissions( $namespace );
            }
        }
    }
};
