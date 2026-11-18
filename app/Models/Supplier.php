<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    /** @use HasFactory<\Database\Factories\SupplierFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'address',
        'contact_person',
        'alt_phone',
        'region',
        'supplier_type',
        'tax_id',
        'registration_number',
        'payment_terms_days',
        'lead_time_days',
        'rating',
        'reliability_score',
        'status',
        'first_purchase_at',
        'last_purchase_at',
        'metadata',
    ];
    protected $table = 'suppliers';

    protected function casts(): array
    {
        return [
            'rating' => 'decimal:1',
            'first_purchase_at' => 'datetime',
            'last_purchase_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function stocks()
    {
        return $this->hasMany(Stock::class);
    }

    /** The catalog rows linking this supplier to the products they sell, with prices. */
    public function supplierProducts()
    {
        return $this->hasMany(SupplierProduct::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'supplier_product')
            ->withPivot(['unit_cost', 'supplier_sku', 'units_per_box', 'is_preferred', 'is_active'])
            ->withTimestamps();
    }
}
