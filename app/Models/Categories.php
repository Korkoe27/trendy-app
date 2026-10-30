<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Categories extends Model
{
    /** @use HasFactory<\Database\Factories\CategoriesFactory> */
    use HasFactory;
    protected $fillable=[
        "name",
        'pricing_model'
    ];

    protected $table = 'categories';

    /**
     * Ids of the food categories: named "food" (any capitalisation) or priced per plate.
     * Food is sold through orders and counted as one "Food total" at end of day, not unit by unit.
     *
     * @return array<int, int>
     */
    public static function foodIds(): array
    {
        return static::query()
            ->where(fn ($q) => $q->whereRaw('LOWER(TRIM(name)) = ?', ['food'])->orWhere('pricing_model', 'per_plate'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'category_id');
    }
}
