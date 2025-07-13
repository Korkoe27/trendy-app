<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FoodSales extends Model
{
    /** @use HasFactory<\Database\Factories\FoodSalesFactory> */
    use HasFactory;

    protected $fillable = [
        "product_id",
        "created_by",
        "quantity_sold"
    ];

    protected $table = 'food_sales';


    public function product(){

        //add condition to check and confirm if product is a food product
        return $this->belongsTo(Product::class, 'product_id');
    }
}
