<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    /** @use HasFactory<\Database\Factories\ExpenseFactory> */
    use HasFactory;

    protected $table = 'expenses';

    protected $fillable = [
        'reference',
        'amount',
        'description',
        'incurred_at',
        'payment_method',
        'paid_by',
        'category',
        'notes',
        'status',
        'supplier',
    ];
    protected $casts = [
        'incurred_at' => 'datetime',
        'amount' => 'decimal:2',
    ];
}
