<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CoinSales extends Model
{
    /** @use HasFactory<\Database\Factories\CoinSalesFactory> */
    use HasFactory;
    protected $fillable =[
        'product_id',
        'closing_coins'
    ];

    protected $table = 'coin_sales';

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    

}
