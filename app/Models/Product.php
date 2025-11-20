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
        "barcode",
        "stock_limit",
        // "cost_price",    
        "selling_price",
        "units_per_box",
    ];

    protected $table = 'products';


    public function category()
    {
        return $this->belongsTo(Categories::class, 'category_id');
    }

    public function stocks(){
        return $this->hasMany(Stock::class)->orderBy('created_at', 'desc');
    }

    public function currentStock()
{
    return $this->hasOne(Stock::class)->latestOfMany('created_at');
}

    public function dailySales()
    {
        return $this->hasMany(DailySales::class);
    }

    public function dailySalesSummary(){
        return $this->hasMany(DailySalesSummary::class);
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
