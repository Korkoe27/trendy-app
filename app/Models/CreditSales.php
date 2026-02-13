<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditSales extends Model
{
    protected $guarded = [];
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeUnpaid($query)
    {
        return $query->where('status', 'unpaid');
    }

    protected $casts = [
        'credit_date' => 'date',
        'payment_date' => 'date',
    ];

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function dailySale()
    {
        return $this->belongsTo(DailySales::class);
    }

        public function getRemainingBalanceAttribute()
    {
        return $this->credit_amount - $this->amount_paid;
    }
}
