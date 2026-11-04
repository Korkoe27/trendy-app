<?php

namespace Tests\Concerns;

use App\Models\PriceEvent;
use App\Models\Product;

/**
 * Percentage and timed price days can no longer be created, but ones scheduled earlier are still
 * in the database and keep working. Tests that exercise that engine create such rows directly.
 */
trait CreatesLegacyPriceEvents
{
    /**
     * @param  array<int, float|int|null>|null  $products  product id => custom price (null = use the percent); null = the VIP list
     */
    protected function legacyEvent(int $createdBy, string $start, string $end, ?float $percent, ?array $products = null, ?string $startTime = null, ?string $endTime = null, ?string $name = 'Legacy'): PriceEvent
    {
        $event = PriceEvent::create([
            'name' => $name, 'starts_on' => $start, 'ends_on' => $end,
            'starts_time' => $startTime, 'ends_time' => $endTime,
            'percent' => $percent, 'created_by' => $createdBy,
        ]);

        $products ??= Product::where('is_vip', true)->pluck('id')->mapWithKeys(fn ($id) => [$id => null])->all();

        foreach ($products as $productId => $custom) {
            $event->products()->attach($productId, ['custom_price' => $custom]);
        }

        return $event;
    }
}
