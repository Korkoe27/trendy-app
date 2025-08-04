<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailySalesSummary extends Model
{
    /** @use HasFactory<\Database\Factories\DailySalesSummaryFactory> */
    use HasFactory;

    protected $guarded = [];
    protected $table = 'daily_sales_summaries';


    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
