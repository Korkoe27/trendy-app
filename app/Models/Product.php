<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    /** @use HasFactory<\Database\Factories\ProductFactory> */
    use HasFactory;


    protected $fillable =[
        "name",
        "sku",
        "category_id",
        "cost_price",
        "selling_price",
        "units_per_box",
    ];

    protected $table = 'products';


    public function category()
    {
        return $this->belongsTo(Categories::class, 'category_id');
    }

    public function stocks(){
        return $this->hasMany(Stock::class);
    }

    public function coinSales(){

        //add condition to check if product is a game product like snooker
        return $this->hasMany(CoinSales::class);
    }

    public function foodSales(){
        //add condition to check if product is a food product like pizza
        return $this->hasMany(FoodSales::class);
    }
}
