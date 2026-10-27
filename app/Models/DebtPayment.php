<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DebtPayment extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $table = 'debt_payments';

    protected $casts = [
        'period_month' => 'date',
        'paid_at' => 'date',
        'voided_at' => 'datetime',
        'amount' => 'decimal:2',
        'outstanding_before' => 'decimal:2',
        'outstanding_after' => 'decimal:2',
    ];

    public function scopeActive($query)
    {
        return $query->whereNull('voided_at');
    }

    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }
}
