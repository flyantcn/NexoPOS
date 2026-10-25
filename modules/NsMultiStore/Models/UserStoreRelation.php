<?php

namespace Modules\NsMultiStore\Models;

use App\Models\NsModel;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\NsMultiStore\Database\Factories\UserStoreRelationFactory;

/**
 * @property int         $id
 * @property int         $user_id
 * @property int         $store_id
 * @property string|null $role_namespace
 * @property string|null $uuid
 * @property Carbon      $updated_at
 */
class UserStoreRelation extends NsModel
{
    use HasFactory;

    protected $table = 'nsmultistore_users_stores_relations';

    protected $fillable = [
        'user_id',
        'store_id',
        'role_namespace',
        'uuid',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'store_id' => 'integer',
    ];

    protected static function newFactory(): Factory
    {
        return UserStoreRelationFactory::new();
    }

    /**
     * Returns the role namespaces an employee can have on a store.
     *
     * @return array<string, string>
     */
    public static function getRoleNamespaces(): array
    {
        return [
            Role::ADMIN => __m( 'Administrator', 'NsMultiStore' ),
            Role::STOREADMIN => __m( 'Store Administrator', 'NsMultiStore' ),
            Role::STORECASHIER => __m( 'Store Cashier', 'NsMultiStore' ),
            Role::USER => __m( 'User', 'NsMultiStore' ),
        ];
    }

    public function user()
    {
        return $this->belongsTo( User::class, 'user_id' );
    }

    public function store()
    {
        return $this->belongsTo( Store::class, 'store_id' );
    }

    /**
     * Scope a query to the relation of a given user and store.
     */
    public function scopeCombinaison( $query, User $user, Store $store )
    {
        return $query->where( 'user_id', $user->id )
            ->where( 'store_id', $store->id );
    }
}
