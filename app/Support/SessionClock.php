<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * The pub's working day is a session that runs past midnight (for example Saturday 15:00 to
 * Sunday 03:00). Times of day are placed on that session, not on the calendar: with a 03:00
 * closing hour, 18:00 is 15 hours into the session and 01:00 is 22 hours into it, so "01:00"
 * on a Saturday event means Sunday 01:00.
 *
 * A "session minute" counts minutes since the closing hour (0 to 1439); 1440 is the end of the
 * session.
 */
class SessionClock
{
    public const MINUTES = 1440;

    /** Closing hour as minutes after midnight (config pricing.day_ends_at). */
    public static function closingMinutes(): int
    {
        return self::minutesOfDay((string) config('pricing.day_ends_at', '03:00'));
    }

    /** The session minute the pub opens (config pricing.opens_at). */
    public static function opensMinute(): int
    {
        return self::sessionMinute((string) config('pricing.opens_at', '15:00'));
    }

    public static function minutesOfDay(string $time): int
    {
        [$hour, $minute] = array_map('intval', explode(':', $time) + [0, 0]);

        return $hour * 60 + $minute;
    }

    /** A clock time as a session minute. 03:00 with a 03:00 closing hour is 0 (the start). */
    public static function sessionMinute(string $time): int
    {
        return (self::minutesOfDay($time) - self::closingMinutes() + self::MINUTES) % self::MINUTES;
    }

    /** As an END time: the closing hour itself means the end of the session, not its start. */
    public static function endMinute(string $time): int
    {
        return self::sessionMinute($time) ?: self::MINUTES;
    }

    /** Where a moment falls on the session timeline. */
    public static function minuteOf(CarbonInterface $at): int
    {
        return (((int) $at->format('G')) * 60 + (int) $at->format('i') - self::closingMinutes() + self::MINUTES) % self::MINUTES;
    }

    /** The calendar moment of a session minute on a business date. */
    public static function instant(Carbon|string $date, int $sessionMinute): Carbon
    {
        return Carbon::parse($date)->startOfDay()->addMinutes(self::closingMinutes() + $sessionMinute);
    }

    /**
     * The part of a business date an event covers, as [start, end) session minutes. Only the
     * first and last day of an event can be partial; every other day is the whole session.
     *
     * @return array{0: int, 1: int}
     */
    public static function window(string $date, string $startsOn, string $endsOn, ?string $startTime, ?string $endTime): array
    {
        $start = ($date === $startsOn && $startTime) ? self::sessionMinute($startTime) : 0;
        $end = ($date === $endsOn && $endTime) ? self::endMinute($endTime) : self::MINUTES;

        return [$start, $end];
    }
}
