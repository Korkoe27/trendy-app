<?php

namespace App\Models;

use App\Support\Phone;
use Illuminate\Database\Eloquent\Model;

/** A customer phone number on the block list: no orders can be placed for it. */
class BlockedNumber extends Model
{
    protected $guarded = [];

    /** Matches however the number is typed (024 123 4567, +233 24 123 4567 ...). */
    public static function isBlocked(?string $phone): bool
    {
        $normalized = Phone::normalize($phone);

        return $normalized !== null && static::where('phone', $normalized)->exists();
    }
}
