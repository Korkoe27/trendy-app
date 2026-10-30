<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A scheduled VIP price day (or range of days). starts_on / ends_on are plain Y-m-d strings,
 * so they compare correctly in SQL on every database.
 */
class PriceEvent extends Model
{
    public const STATUS_UPCOMING = 'upcoming';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ENDED = 'ended';

    public const STATUS_CANCELLED = 'cancelled';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'percent' => 'decimal:2',
            'cancelled_at' => 'datetime',
        ];
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'price_event_products')->withPivot('custom_price');
    }

    /** Events that have not been cancelled. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('cancelled_at');
    }

    /** @param  string  $today  business date, Y-m-d */
    public function statusOn(string $today): string
    {
        return match (true) {
            $this->cancelled_at !== null => self::STATUS_CANCELLED,
            $this->ends_on < $today => self::STATUS_ENDED,
            $this->starts_on > $today => self::STATUS_UPCOMING,
            default => self::STATUS_ACTIVE,
        };
    }

    /** A custom-price event carries an exact price per product instead of one percentage. */
    public function isCustom(): bool
    {
        return $this->percent === null;
    }

    /** "+20%" or "-10%", or "Custom prices" when each product has its own price. */
    public function percentLabel(): string
    {
        if ($this->isCustom()) {
            return 'Custom prices';
        }

        $percent = (float) $this->percent;

        return ($percent > 0 ? '+' : '-').rtrim(rtrim(number_format(abs($percent), 2, '.', ''), '0'), '.').'%';
    }

    public function isIncrease(): bool
    {
        return (float) $this->percent > 0;
    }
}
