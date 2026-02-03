<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Stock extends Model
{
    /** @use HasFactory<\Database\Factories\StockFactory> */
    use HasFactory;

    // protected $fillable = [
    //     'product_id',
    //     'added_units',
    //     'closing_units',

    // ];

    protected $guarded = [];

    protected $table = 'stocks';

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function sales(){
        return $this->hasMany(DailySales::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
