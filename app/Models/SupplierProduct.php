<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierProduct extends Model
{
    protected $table = 'supplier_product';

    protected $fillable = [
        'supplier_id',
        'product_id',
        'unit_cost',
        'supplier_sku',
        'units_per_box',
        'min_order_qty',
        'lead_time_days',
        'is_preferred',
        'is_active',
        'last_purchase_price',
        'last_purchased_at',
        'metadata',
    ];

    protected $casts = [
        'unit_cost' => 'decimal:2',
        'last_purchase_price' => 'decimal:2',
        'is_preferred' => 'boolean',
        'is_active' => 'boolean',
        'last_purchased_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
