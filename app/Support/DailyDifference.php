<?php

namespace App\Support;

/**
 * The one rule for a day's collection difference, so Debt Payments, Analytics, the Excel export
 * and the Inventory screens always agree.
 *
 *   expected   = drinks + food - on the house + snooker shortfall
 *   collected  = cash + momo + hubtel          (snooker money is kept separate from these)
 *   difference = collected - expected          (negative = money missing)
 *
 * Snooker has no stock, so its record is the coins given from the bar: the day stores the money
 * those coins should have brought in (`snooker_expected`, NULL on days before the check existed).
 * Only a SHORTFALL (typed below expected) is counted; an excess is flagged but never allowed to
 * offset real shortfalls in drinks.
 */
class DailyDifference
{
    /** @param  object|array  $row  a daily_sales_summaries row */
    public static function snookerShortfall($row): float
    {
        $expected = data_get($row, 'snooker_expected');

        if ($expected === null) {
            return 0.0;
        }

        return max(0.0, round((float) $expected - (float) data_get($row, 'snooker', 0), 2));
    }

    /** @param  object|array  $row */
    public static function expected($row): float
    {
        return (float) data_get($row, 'drinks_total', 0)
            + (float) data_get($row, 'food_total', 0)
            - (float) data_get($row, 'on_the_house', 0)
            + self::snookerShortfall($row);
    }

    /** @param  object|array  $row */
    public static function collected($row): float
    {
        return (float) data_get($row, 'total_cash', 0)
            + (float) data_get($row, 'total_momo', 0)
            + (float) data_get($row, 'total_hubtel', 0);
    }

    /** @param  object|array  $row */
    public static function difference($row): float
    {
        return self::collected($row) - self::expected($row);
    }
}
