<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailySales extends Model
{
    /** @use HasFactory<\Database\Factories\DailySalesFactory> */
    use HasFactory;

    protected $guarded = [];
    protected $table = 'daily_sales';

    public function stock(){
        return $this->belongsTo(Stock::class);
    }

    public function product(){
        return $this->belongsTo(Product::class);
    }
}
