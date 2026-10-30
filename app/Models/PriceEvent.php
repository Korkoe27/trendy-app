<?php

namespace App\Models;

use App\Support\SessionClock;
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

    /** The start time as HH:MM, or null when the event starts with the first session. */
    public function startTime(): ?string
    {
        return $this->starts_time ? substr((string) $this->starts_time, 0, 5) : null;
    }

    /** The end time as HH:MM, or null when the event runs to the end of the last session. */
    public function endTime(): ?string
    {
        return $this->ends_time ? substr((string) $this->ends_time, 0, 5) : null;
    }

    public function hasTimes(): bool
    {
        return $this->startTime() !== null || $this->endTime() !== null;
    }

    /**
     * The part of a business date this event covers, as [start, end) minutes into the session.
     *
     * @return array{0: int, 1: int}
     */
    public function windowOn(string $date): array
    {
        return SessionClock::window($date, (string) $this->starts_on, (string) $this->ends_on, $this->startTime(), $this->endTime());
    }

    /**
     * @param  string  $today  business date, Y-m-d
     * @param  int|null  $minute  minutes into today's session; lets a timed event be "upcoming" or "ended" within the day
     */
    public function statusOn(string $today, ?int $minute = null): string
    {
        $ended = $this->ends_on < $today
            || ($minute !== null && $this->ends_on === $today && $this->endTime() !== null && $minute >= SessionClock::endMinute($this->endTime()));

        $upcoming = $this->starts_on > $today
            || ($minute !== null && $this->starts_on === $today && $this->startTime() !== null && $minute < SessionClock::sessionMinute($this->startTime()));

        return match (true) {
            $this->cancelled_at !== null => self::STATUS_CANCELLED,
            $ended => self::STATUS_ENDED,
            $upcoming => self::STATUS_UPCOMING,
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
