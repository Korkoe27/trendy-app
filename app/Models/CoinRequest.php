<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Coins taken from the bar for a game (snooker). requested -> given; a waiting request can be
 * cancelled, a given one voided (with a reason). Only given requests count at end of day.
 */
class CoinRequest extends Model
{
    public const STATUS_REQUESTED = 'requested';

    public const STATUS_GIVEN = 'given';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_VOIDED = 'voided';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'business_date' => 'date:Y-m-d',
            'unit_price' => 'decimal:2',
            'total' => 'decimal:2',
            'requested_at' => 'datetime',
            'given_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function isWaiting(): bool
    {
        return $this->status === self::STATUS_REQUESTED;
    }

    public function isGiven(): bool
    {
        return $this->status === self::STATUS_GIVEN;
    }
}
