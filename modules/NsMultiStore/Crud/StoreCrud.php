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
use Modules\NsMultiStore\Models\Store;
use TorMorten\Eventy\Facades\Events as Hook;

class StoreCrud extends CrudService
{
    /**
     * Define the autoload status.
     */
    const AUTOLOAD = true;

    /**
     * Define the identifier.
     */
    const IDENTIFIER = 'ns.multistore.stores';

    /**
     * Define the base table.
     */
    protected $table = 'nsmultistore_stores';

    /**
     * Define the namespace.
     */
    protected $namespace = 'ns.multistore.stores';

    /**
     * Model used.
     */
    protected $model = Store::class;

    /**
     * Define the permissions used by the CRUD.
     */
    protected $permissions = [
        'create' => 'nexopos.create.multistore-stores',
        'read' => 'nexopos.read.multistore-stores',
        'update' => 'nexopos.update.multistore-stores',
        'delete' => 'nexopos.delete.multistore-stores',
    ];

    /**
     * Adding relation.
     */
    public $relations = [
        'leftJoin' => [
            [ User::class, 'author' ],
            [ Store::class, 'parent' ],
        ],
    ];

    public $pick = [
        'author' => [ 'username' ],
        'parent' => [ 'name' ],
    ];

    /**
     * Return the labels used for the CRUD instance.
     */
    public function getLabels(): array
    {
        return CrudTable::labels(
            list_title: __m( 'Stores List', 'NsMultiStore' ),
            list_description: __m( 'Display all the stores registered on the system.', 'NsMultiStore' ),
            no_entry: __m( 'No stores has been registered', 'NsMultiStore' ),
            create_new: __m( 'Add a new store', 'NsMultiStore' ),
            create_title: __m( 'Create a new store', 'NsMultiStore' ),
            create_description: __m( 'Register a new store and save it.', 'NsMultiStore' ),
            edit_title: __m( 'Edit store', 'NsMultiStore' ),
            edit_description: __m( 'Modify the details of an existing store.', 'NsMultiStore' ),
            back_to_list: __m( 'Return to Stores', 'NsMultiStore' ),
        );
    }

    /**
     * Define the columns of the CRUD table.
     */
    public function getColumns(): array
    {
        return CrudTable::columns(
            CrudTable::column(
                label: __m( 'Name', 'NsMultiStore' ),
                identifier: 'name',
                width: '200px'
            ),
            CrudTable::column(
                label: __m( 'Code', 'NsMultiStore' ),
                identifier: 'code',
                width: '120px'
            ),
            CrudTable::column(
                label: __m( 'Type', 'NsMultiStore' ),
                identifier: 'type',
                width: '120px'
            ),
            CrudTable::column(
                label: __m( 'Status', 'NsMultiStore' ),
                identifier: 'status',
                width: '120px'
            ),
            CrudTable::column(
                label: __m( 'Headquarter', 'NsMultiStore' ),
                identifier: 'parent_name',
                width: '160px'
            ),
            CrudTable::column(
                label: __m( 'Author', 'NsMultiStore' ),
                identifier: 'author_username',
                width: '160px'
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
        $entry->type = Store::getTypes()[ $entry->type ] ?? $entry->type;
        $entry->status = Store::getStatuses()[ $entry->status ] ?? $entry->status;

        $entry->action(
            identifier: 'edit',
            label: __m( 'Edit', 'NsMultiStore' ),
            type: 'GOTO',
            url: ns()->url( '/dashboard/multistore/stores/edit/' . $entry->id )
        );

        $entry->action(
            identifier: 'delete',
            label: __m( 'Delete', 'NsMultiStore' ),
            type: 'DELETE',
            url: ns()->url( '/api/crud/' . self::IDENTIFIER . '/' . $entry->id ),
            confirm: [
                'message' => __m( 'Would you like to delete this store?', 'NsMultiStore' ),
            ]
        );

        return $entry;
    }

    /**
     * Define the form used to create and edit a store.
     */
    public function getForm( $entry = null ): array
    {
        $storesQuery = Store::query();

        if ( $entry instanceof Store ) {
            $storesQuery->where( 'id', '!=', $entry->id );
        }

        return CrudForm::form(
            main: FormInput::text(
                label: __m( 'Name', 'NsMultiStore' ),
                name: 'name',
                description: __m( 'Provide the commercial name used to identify the store.', 'NsMultiStore' ),
                validation: 'required',
                value: $entry->name ?? '',
            ),
            tabs: CrudForm::tabs(
                CrudForm::tab(
                    label: __m( 'General', 'NsMultiStore' ),
                    identifier: 'general',
                    fields: CrudForm::fields(
                        FormInput::text(
                            label: __m( 'Code', 'NsMultiStore' ),
                            name: 'code',
                            description: __m( 'Unique short reference of the store, such as a city code. It cannot be changed silently by other stores.', 'NsMultiStore' ),
                            validation: 'required|unique:' . Hook::filter( 'ns-table-name', 'nsmultistore_stores' ) . ',code' . ( $entry !== null ? ',' . $entry->id : '' ),
                            value: $entry->code ?? '',
                        ),
                        FormInput::select(
                            label: __m( 'Type', 'NsMultiStore' ),
                            name: 'type',
                            description: __m( 'Single stores are isolated, chain stores share their data and franchise stores only report totals to their headquarter.', 'NsMultiStore' ),
                            validation: 'required',
                            options: Helper::kvToJsOptions( Store::getTypes() ),
                            value: $entry->type ?? Store::TYPE_SINGLE,
                        ),
                        FormInput::select(
                            label: __m( 'Status', 'NsMultiStore' ),
                            name: 'status',
                            description: __m( 'Disabled stores are excluded from the store switcher and cannot be used for selling.', 'NsMultiStore' ),
                            validation: 'required',
                            options: Helper::kvToJsOptions( Store::getStatuses() ),
                            value: $entry->status ?? Store::STATUS_ACTIVE,
                        ),
                        FormInput::searchSelect(
                            label: __m( 'Headquarter', 'NsMultiStore' ),
                            name: 'parent_id',
                            description: __m( 'Only required for franchise stores. Defines the store that receives the aggregated reports.', 'NsMultiStore' ),
                            options: Helper::toJsOptions( $storesQuery->get(), [ 'id', 'name' ] ),
                            value: $entry->parent_id ?? '',
                        ),
                        FormInput::textarea(
                            label: __m( 'Description', 'NsMultiStore' ),
                            name: 'description',
                            description: __m( 'Provide a short description about the store.', 'NsMultiStore' ),
                            value: $entry->description ?? '',
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

        return $inputs;
    }

    /**
     * Define the links used by the CRUD.
     */
    public function getLinks(): array
    {
        return [
            'list' => ns()->url( '/dashboard/multistore/stores' ),
            'create' => ns()->url( '/dashboard/multistore/stores/create' ),
            'edit' => ns()->url( '/dashboard/multistore/stores/edit/' ),
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
                'label' => __m( 'Delete Selected Stores', 'NsMultiStore' ),
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

                if ( $entity instanceof Store ) {
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
