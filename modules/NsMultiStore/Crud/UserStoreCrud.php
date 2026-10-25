<?php

namespace Modules\NsMultiStore\Crud;

use App\Classes\CrudForm;
use App\Classes\CrudTable;
use App\Classes\FormInput;
use App\Models\User;
use App\Services\CrudEntry;
use App\Services\CrudService;
use App\Services\Helper;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\NsMultiStore\Models\Store;
use Modules\NsMultiStore\Models\UserStoreRelation;
use Modules\NsMultiStore\Services\StoreService;
use TorMorten\Eventy\Facades\Events as Hook;

class UserStoreCrud extends CrudService
{
    /**
     * Define the autoload status.
     */
    const AUTOLOAD = true;

    /**
     * Define the identifier.
     */
    const IDENTIFIER = 'ns.multistore.users-stores';

    /**
     * Define the base table.
     */
    protected $table = 'nsmultistore_users_stores_relations';

    /**
     * Define the namespace.
     */
    protected $namespace = 'ns.multistore.users-stores';

    /**
     * Model used.
     */
    protected $model = UserStoreRelation::class;

    /**
     * Define the permissions used by the CRUD.
     */
    protected $permissions = [
        'create' => 'nexopos.create.multistore-users-stores',
        'read' => 'nexopos.read.multistore-users-stores',
        'update' => 'nexopos.update.multistore-users-stores',
        'delete' => 'nexopos.delete.multistore-users-stores',
    ];

    /**
     * Adding relation.
     */
    public $relations = [
        'leftJoin' => [
            [ User::class, 'user' ],
            [ Store::class, 'store' ],
        ],
    ];

    public $pick = [
        'user' => [ 'username' ],
        'store' => [ 'name' ],
    ];

    /**
     * Return the labels used for the CRUD instance.
     */
    public function getLabels(): array
    {
        return CrudTable::labels(
            list_title: __m( 'Employee Stores List', 'NsMultiStore' ),
            list_description: __m( 'Display all the employees bound to a store.', 'NsMultiStore' ),
            no_entry: __m( 'No employee has been bound to a store', 'NsMultiStore' ),
            create_new: __m( 'Bind an employee to a store', 'NsMultiStore' ),
            create_title: __m( 'Bind an employee to a store', 'NsMultiStore' ),
            create_description: __m( 'Choose the employee, the store and the role he has on it.', 'NsMultiStore' ),
            edit_title: __m( 'Edit the employee store', 'NsMultiStore' ),
            edit_description: __m( 'Modify the store or the role of an employee.', 'NsMultiStore' ),
            back_to_list: __m( 'Return to Employee Stores', 'NsMultiStore' ),
        );
    }

    /**
     * Define the columns of the CRUD table.
     */
    public function getColumns(): array
    {
        return CrudTable::columns(
            CrudTable::column(
                label: __m( 'Employee', 'NsMultiStore' ),
                identifier: 'user_username',
                width: '200px'
            ),
            CrudTable::column(
                label: __m( 'Store', 'NsMultiStore' ),
                identifier: 'store_name',
                width: '200px'
            ),
            CrudTable::column(
                label: __m( 'Role On Store', 'NsMultiStore' ),
                identifier: 'role_namespace',
                width: '200px'
            ),
            CrudTable::column(
                label: __m( 'Created At', 'NsMultiStore' ),
                identifier: 'created_at',
                width: '160px'
            ),
        );
    }

    /**
     * Define the row actions of the CRUD table.
     */
    public function setActions( CrudEntry $entry ): CrudEntry
    {
        $entry->role_namespace = UserStoreRelation::getRoleNamespaces()[ $entry->role_namespace ] ?? $entry->role_namespace;

        $entry->action(
            identifier: 'edit',
            label: __m( 'Edit', 'NsMultiStore' ),
            type: 'GOTO',
            url: ns()->url( '/dashboard/multistore/users-stores/edit/' . $entry->id )
        );

        $entry->action(
            identifier: 'delete',
            label: __m( 'Delete', 'NsMultiStore' ),
            type: 'DELETE',
            url: ns()->url( '/api/crud/' . self::IDENTIFIER . '/' . $entry->id ),
            confirm: [
                'message' => __m( 'Would you like to unbind this employee from the store?', 'NsMultiStore' ),
            ]
        );

        return $entry;
    }

    /**
     * Define the form used to bind an employee to a store.
     */
    public function getForm( $entry = null ): array
    {
        return CrudForm::form(
            main: FormInput::searchSelect(
                label: __m( 'Employee', 'NsMultiStore' ),
                name: 'user_id',
                description: __m( 'The employee that will be allowed to use the store.', 'NsMultiStore' ),
                validation: 'required',

                /**
                 * The employees are searched from the "EmployeeCrud" resource,
                 * so that the whole user list isn't loaded on the form. The
                 * label displayed combines the full name and the username
                 * while the id is what gets submitted.
                 */
                options: [
                    'search' => FormInput::searchSelectOptions(
                        identifier: EmployeeCrud::IDENTIFIER,
                        limit: 10,
                        moreLabel: __m( '+{count} more employees. Type to search.', 'NsMultiStore' ),
                        minLength: 1,
                    ),
                ],
                props: [
                    'optionAttributes' => [
                        'label' => 'display_name',
                        'value' => 'id',
                    ],
                ],
                value: $entry->user_id ?? '',
            ),
            tabs: CrudForm::tabs(
                CrudForm::tab(
                    label: __m( 'General', 'NsMultiStore' ),
                    identifier: 'general',
                    fields: CrudForm::fields(
                        FormInput::searchSelect(
                            label: __m( 'Store', 'NsMultiStore' ),
                            name: 'store_id',
                            description: __m( 'The store the employee is allowed to use.', 'NsMultiStore' ),
                            validation: 'required',
                            options: Helper::toJsOptions(
                                Store::where( 'status', Store::STATUS_ACTIVE )->get(),
                                [ 'id', 'name' ]
                            ),
                            value: $entry->store_id ?? '',
                        ),
                        FormInput::select(
                            label: __m( 'Role On Store', 'NsMultiStore' ),
                            name: 'role_namespace',
                            description: __m( 'The permission level the employee has while using the store.', 'NsMultiStore' ),
                            validation: 'required',
                            options: Helper::kvToJsOptions( UserStoreRelation::getRoleNamespaces() ),
                            value: $entry->role_namespace ?? '',
                        )
                    )
                )
            )
        );
    }

    /**
     * Filter POST input fields.
     *
     * @param  array $inputs
     * @return array
     */
    public function filterPostInputs( $inputs )
    {
        if ( empty( $inputs[ 'uuid' ] ) ) {
            $inputs[ 'uuid' ] = (string) Str::uuid();
        }

        /**
         * The search-select submits the id, but an administrator might type
         * an username directly. Both forms are accepted here so that the
         * form never reaches the database with a non integer value.
         */
        $inputs[ 'user_id' ] = $this->resolveUserId( $inputs[ 'user_id' ] ?? null );
        $inputs[ 'store_id' ] = $this->resolveStoreId( $inputs[ 'store_id' ] ?? null );

        $this->assertCanBindToStore( (int) $inputs[ 'store_id' ] );

        $query = UserStoreRelation::where( 'user_id', $inputs[ 'user_id' ] )
            ->where( 'store_id', $inputs[ 'store_id' ] );

        /**
         * While an entry is updated, its own relation must be ignored.
         */
        $entryId = $this->getEntryId();

        if ( $entryId !== null ) {
            $query->where( 'id', '!=', $entryId );
        }

        if ( $query->exists() ) {
            throw ValidationException::withMessages( [
                'user_id' => __m( 'This employee is already bound to that store.', 'NsMultiStore' ),
            ] );
        }

        return $inputs;
    }

    /**
     * A store administrator can only bind employees to the store he is
     * currently managing. The main administrator isn't limited.
     */
    protected function assertCanBindToStore( int $storeId ): void
    {
        $user = request()->user();

        if ( ! $user instanceof User ) {
            throw ValidationException::withMessages( [
                'store_id' => __m( 'You must be logged in to bind an employee to a store.', 'NsMultiStore' ),
            ] );
        }

        /**
         * @var StoreService $storeService
         */
        $storeService = app()->make( StoreService::class );

        if ( $storeService->hasAccessToAllStores( $user ) ) {
            return;
        }

        $currentStore = $storeService->getCurrentStore();

        if ( $currentStore instanceof Store && $storeService->canManageStore( $user, $currentStore ) && $currentStore->id === $storeId ) {
            return;
        }

        throw ValidationException::withMessages( [
            'store_id' => __m( 'You can only bind employees to a store you manage.', 'NsMultiStore' ),
        ] );
    }

    /**
     * Limit the bindings shown on the list to the store the employee is
     * currently managing. The main administrator keeps seeing every binding.
     */
    public function hook( $query ): void
    {
        $user = request()->user();

        if ( ! $user instanceof User ) {
            return;
        }

        /**
         * @var StoreService $storeService
         */
        $storeService = app()->make( StoreService::class );

        if ( $storeService->hasAccessToAllStores( $user ) ) {
            return;
        }

        $store = $storeService->getCurrentStore();

        $query->where(
            $this->hookTableName( $this->table ) . '.store_id',
            $store instanceof Store ? $store->id : 0
        );
    }

    /**
     * Returns the id of the entry being updated, if any.
     */
    protected function getEntryId()
    {
        return request()->input( 'id' ) ?: request()->route( 'id' );
    }

    /**
     * Resolve an employee from either his id or his username.
     */
    protected function resolveUserId( mixed $value ): int
    {
        $user = null;

        if ( filter_var( $value, FILTER_VALIDATE_INT ) !== false ) {
            $user = User::find( $value );
        }

        if ( ! $user instanceof User && is_string( $value ) && $value !== '' ) {
            $user = User::where( 'username', $value )->first();
        }

        if ( ! $user instanceof User ) {
            throw ValidationException::withMessages( [
                'user_id' => __m( 'Unable to find an employee with that identifier.', 'NsMultiStore' ),
            ] );
        }

        return $user->id;
    }

    /**
     * Resolve a store from its id.
     */
    protected function resolveStoreId( mixed $value ): int
    {
        $store = null;

        if ( filter_var( $value, FILTER_VALIDATE_INT ) !== false ) {
            $store = Store::find( $value );
        }

        if ( ! $store instanceof Store ) {
            throw ValidationException::withMessages( [
                'store_id' => __m( 'Unable to find a store with that identifier.', 'NsMultiStore' ),
            ] );
        }

        return $store->id;
    }

    /**
     * Define the links used by the CRUD.
     */
    public function getLinks(): array
    {
        return [
            'list' => ns()->url( '/dashboard/multistore/users-stores' ),
            'create' => ns()->url( '/dashboard/multistore/users-stores/create' ),
            'edit' => ns()->url( '/dashboard/multistore/users-stores/edit/' ),
            'post' => ns()->url( '/api/crud/' . self::IDENTIFIER ),
            'put' => ns()->url( '/api/crud/' . self::IDENTIFIER . '/{id}' ),
        ];
    }

    /**
     * Get the bulk actions available for the CRUD.
     */
    public function getBulkActions(): array
    {
        return Hook::filter( $this->namespace . '-bulk', [
            [
                'label' => __m( 'Delete Selected Employee Stores', 'NsMultiStore' ),
                'identifier' => 'delete_selected',
                'url' => ns()->route( 'ns.api.crud-bulk-actions', [
                    'namespace' => $this->namespace,
                ] ),
            ],
        ] );
    }

    /**
     * Handle the bulk actions of the CRUD.
     *
     * @return array
     */
    public function bulkAction( Request $request )
    {
        if ( $request->input( 'action' ) === 'delete_selected' ) {
            $this->allowedTo( 'delete' );

            $status = [
                'success' => 0,
                'error' => 0,
            ];

            foreach ( $request->input( 'entries' ) as $id ) {
                $entity = $this->model::find( $id );

                if ( $entity instanceof UserStoreRelation ) {
                    $entity->delete();
                    $status[ 'success' ]++;
                } else {
                    $status[ 'error' ]++;
                }
            }

            return $status;
        }

        return Hook::filter( $this->namespace . '-catch-action', false, $request );
    }
}
