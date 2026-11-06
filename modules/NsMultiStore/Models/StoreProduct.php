<?php

namespace Modules\NsMultiStore\Models;

use App\Models\NsModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\NsMultiStore\Traits\StoreScoped;

/**
 * @property int         $id
 * @property int         $store_id
 * @property int         $product_id
 * @property bool        $is_active
 * @property float       $quantity
 * @property float       $low_stock_quantity
 * @property bool        $stock_alert_enabled
 * @property float|null  $price_override
 * @property float|null  $sale_price_override
 * @property int|null    $author_id
 * @property array|null  $metadata
 */
class StoreProduct extends NsModel
{
    use HasFactory, StoreScoped;

    protected $table = 'nexopos_store_products';

    protected $guarded = [ 'id' ];

    protected $casts = [
        'is_active'          => 'boolean',
        'quantity'           => 'decimal:5',
        'low_stock_quantity' => 'decimal:5',
        'stock_alert_enabled' => 'boolean',
        'price_override'     => 'decimal:5',
        'sale_price_override' => 'decimal:5',
        'metadata'           => 'json',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo( Store::class, 'store_id' );
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo( \App\Models\Product::class, 'product_id' );
    }

    public function scopeActive( Builder $q ): Builder
    {
        return $q->where( 'is_active', true );
    }
}