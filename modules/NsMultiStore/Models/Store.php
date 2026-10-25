<?php

namespace Modules\NsMultiStore\Models;

use App\Models\NsModel;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\NsMultiStore\Database\Factories\StoreFactory;

/**
 * @property int      $id
 * @property string   $name
 * @property string   $code
 * @property string   $type
 * @property int|null $parent_id
 * @property string   $status
 * @property string   $description
 * @property int      $author_id
 * @property string   $uuid
 * @property Carbon   $updated_at
 */
class Store extends NsModel
{
    use HasFactory;

    /**
     * An independent shop that doesn't report to any headquarter.
     */
    const TYPE_SINGLE = 'single';

    /**
     * A store that shares its data with the other stores of the brand.
     */
    const TYPE_CHAIN = 'chain';

    /**
     * A store that is fully isolated from the other stores.
     */
    const TYPE_FRANCHISE = 'franchise';

    const STATUS_ACTIVE = 'active';

    const STATUS_DISABLED = 'disabled';

    protected $table = 'nsmultistore_stores';

    protected $fillable = [
        'name',
        'code',
        'type',
        'parent_id',
        'status',
        'description',
        'author_id',
        'uuid',
    ];

    protected $casts = [
        'parent_id' => 'integer',
        'author_id' => 'integer',
    ];

    protected static function newFactory(): Factory
    {
        return StoreFactory::new();
    }

    /**
     * Returns all the store types supported by the module.
     *
     * @return array<string, string>
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_SINGLE => __m( 'Single Store', 'NsMultiStore' ),
            self::TYPE_CHAIN => __m( 'Chain Store', 'NsMultiStore' ),
            self::TYPE_FRANCHISE => __m( 'Franchise Store', 'NsMultiStore' ),
        ];
    }

    /**
     * Returns all the statuses supported by a store.
     *
     * @return array<string, string>
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_ACTIVE => __m( 'Active', 'NsMultiStore' ),
            self::STATUS_DISABLED => __m( 'Disabled', 'NsMultiStore' ),
        ];
    }

    public function author()
    {
        return $this->belongsTo( User::class, 'author_id' );
    }

    /**
     * The headquarter a franchise store reports to.
     */
    public function parent()
    {
        return $this->belongsTo( self::class, 'parent_id' );
    }

    /**
     * All the stores that report to the current store.
     */
    public function children()
    {
        return $this->hasMany( self::class, 'parent_id' );
    }

    /**
     * All the employees that are allowed to use the store.
     */
    public function users()
    {
        return $this->belongsToMany( User::class, 'nsmultistore_users_stores_relations', 'store_id', 'user_id' )
            ->withPivot( 'role_namespace' )
            ->withTimestamps();
    }
}
