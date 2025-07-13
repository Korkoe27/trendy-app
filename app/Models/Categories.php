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

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
