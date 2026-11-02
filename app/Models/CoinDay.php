<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A business day whose coin requests were closed by hand (see CoinRequestService::closeDay). */
class CoinDay extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'business_date' => 'date:Y-m-d',
            'closed_at' => 'datetime',
            'closed_amount' => 'decimal:2',
        ];
    }

    public function isClosed(): bool
    {
        return $this->closed_at !== null;
    }
}
