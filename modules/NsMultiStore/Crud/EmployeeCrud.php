<?php

namespace Modules\NsMultiStore\Crud;

use App\Classes\CrudTable;
use App\Models\Role;
use App\Models\User;
use App\Services\CrudEntry;
use App\Services\CrudService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Read only CRUD used as a search source by the "search-select" fields
 * of the module. Employees are retrieved from the API so that every user
 * of the application doesn't have to be loaded on the form.
 */
class EmployeeCrud extends CrudService
{
    /**
     * Define the autoload status.
     */
    const AUTOLOAD = true;

    /**
     * Define the identifier.
     */
    const IDENTIFIER = 'ns.multistore-employees';

    /**
     * Define the base table.
     */
    protected $table = 'nexopos_users';

    /**
     * Define the namespace.
     */
    protected $namespace = self::IDENTIFIER;

    /**
     * Model used.
     */
    protected $model = User::class;

    /**
     * The employees are only searched, they're never managed from here.
     */
    protected $permissions = [
        'create' => false,
        'read' => 'nexopos.read.multistore-users-stores',
        'update' => false,
        'delete' => false,
    ];

    public function getLabels(): array
    {
        $description = __m( 'Searchable list of the employees of the application.', 'NsMultiStore' );

        return CrudTable::labels(
            list_title: __m( 'Employees List', 'NsMultiStore' ),
            list_description: $description,
            no_entry: __m( 'No employee found', 'NsMultiStore' ),
            create_new: __m( 'Employees', 'NsMultiStore' ),
            create_title: __m( 'Employees', 'NsMultiStore' ),
            create_description: $description,
            edit_title: __m( 'Employees', 'NsMultiStore' ),
            edit_description: $description,
            back_to_list: __m( 'Return to Employees', 'NsMultiStore' ),
        );
    }

    public function getColumns(): array
    {
        return CrudTable::columns(
            CrudTable::column(
                label: __m( 'Employee', 'NsMultiStore' ),
                identifier: 'username'
            ),
            CrudTable::column(
                label: __m( 'Email', 'NsMultiStore' ),
                identifier: 'email'
            )
        );
    }

    /**
     * Only the users having a dashboard role are employees. The customers
     * of the application share the same table, they must be excluded.
     */
    public function hook( $query ): void
    {
        $query->whereExists( function ( $query ) {
            $query->select( DB::raw( 1 ) )
                ->from( 'nexopos_users_roles_relations' )
                ->join( 'nexopos_roles', 'nexopos_roles.id', '=', 'nexopos_users_roles_relations.role_id' )
                ->whereColumn( 'nexopos_users_roles_relations.user_id', 'nexopos_users.id' )
                ->whereNotIn( 'nexopos_roles.namespace', [ Role::STORECUSTOMER ] );
        } );
    }

    /**
     * Add the label used by the search select to identify an employee.
     */
    public function setActions( CrudEntry $entry ): CrudEntry
    {
        $entry->display_name = self::employeeLabel( $entry );

        return $entry;
    }

    /**
     * A single resource isn't built from the entries, therefore the label
     * must be added as well. It's what displays the employee selected
     * while an existing binding is edited.
     */
    public function findEntry( int|string $id ): ?Model
    {
        $entry = parent::findEntry( $id );

        if ( $entry instanceof User ) {
            $entry->display_name = self::employeeLabel( $entry );
        }

        return $entry;
    }

    /**
     * Employees are displayed with their full name and their username,
     * as their sole id isn't meaningful for an administrator.
     */
    public static function employeeLabel( mixed $employee ): string
    {
        $fullName = trim( ( $employee->first_name ?? '' ) . ' ' . ( $employee->last_name ?? '' ) );

        return $fullName !== '' ? $fullName . ' (' . $employee->username . ')' : $employee->username;
    }
}
