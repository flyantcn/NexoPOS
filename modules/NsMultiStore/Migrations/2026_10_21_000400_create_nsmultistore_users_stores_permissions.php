<?php

use App\Classes\Schema;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * The permissions required to bind an employee to a store.
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

        $namespaces = [];

        foreach ( $this->permissions as $namespace => $name ) {
            $permission = Permission::namespace( $namespace );

            if ( ! $permission instanceof Permission ) {
                $permission = new Permission;

                /**
                 * A permission name must remain unique. If another permission
                 * already uses that name, we scope it with its namespace.
                 */
                if ( Permission::where( 'name', $name )->exists() ) {
                    $name = $name . ' [' . $namespace . ']';
                }
            }

            $permission->name = $name;
            $permission->namespace = $namespace;
            $permission->description = __m( 'Allow the user to bind employees to the stores of the multi store module.', 'NsMultiStore' );
            $permission->save();

            $namespaces[] = $namespace;
        }

        /**
         * New permissions are granted to the administrator by default.
         */
        $admin = Role::namespace( 'admin' );

        if ( $admin instanceof Role ) {
            $admin->addPermissions( $namespaces );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if ( ! Schema::hasTable( 'nexopos_permissions' ) ) {
            return;
        }

        foreach ( array_keys( $this->permissions ) as $namespace ) {
            $permission = Permission::namespace( $namespace );

            if ( $permission instanceof Permission ) {
                $permission->removeFromRoles();
                $permission->delete();
            }
        }
    }
};
