<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** The summary of one business day's food orders, written when the end-of-day count is submitted. */
class FoodSales extends Model
{
    protected $table = 'food_sales';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'sales_date' => 'date:Y-m-d',
            'revenue' => 'decimal:2',
            'breakdown' => 'array',
            'recorded_at' => 'datetime',
        ];
    }
}
