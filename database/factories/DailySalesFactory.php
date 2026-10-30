<?php

namespace Database\Factories;

use App\Models\Stock;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * One row per product per trading day, as written by Inventory::actuallySubmitInventory().
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DailySales>
 */
class DailySalesFactory extends Factory
{
    public function definition(): array
    {
        $stock = Stock::inRandomOrder()->first() ?? Stock::factory()->create();
        $price = (float) $stock->product->selling_price;
        $sold = fake()->numberBetween(5, 40);
        $closing = (float) $stock->total_units;
        $opening = $closing + $sold;
        $date = now()->subDay();
        $upb = (int) $stock->product->units_per_box;

        return [
            'product_id' => $stock->product_id,
            'stock_id' => $stock->id,
            'sales_date' => $date->toDateString(),
            'opening_stock' => $opening,
            'closing_stock' => $closing,
            'opening_boxes' => $upb > 0 ? floor($opening / $upb) : 0,
            'closing_boxes' => $upb > 0 ? floor($closing / $upb) : 0,
            'total_amount' => $sold * $price,
            'unit_profit' => $sold * (float) $stock->cost_margin,
            'metadata' => json_encode(['selling_price' => $price, 'cost_margin' => (float) $stock->cost_margin, 'demo' => true]),
            'created_at' => $date->copy()->addDay()->setTime(16, 0),
            'updated_at' => $date->copy()->addDay()->setTime(16, 0),
        ];
    }

    /**
     * Build the row from the stock the day opened with and the closing stock row that was
     * written for it. sold = opening - closing, like the app computes it.
     */
    public function forClose(Stock $opening, Stock $closing, float $sold, Carbon $businessDate, Carbon $enteredAt): static
    {
        return $this->state(function () use ($opening, $closing, $sold, $businessDate, $enteredAt) {
            $product = $opening->product;
            $price = (float) $product->selling_price;
            $upb = (int) $product->units_per_box;
            $costMargin = (float) $opening->cost_margin;

            return [
                'product_id' => $opening->product_id,
                'stock_id' => $closing->id,
                'sales_date' => $businessDate->toDateString(),
                'opening_stock' => $opening->total_units,
                'closing_stock' => $closing->total_units,
                'opening_boxes' => $upb > 0 ? floor($opening->total_units / $upb) : 0,
                'closing_boxes' => $upb > 0 ? floor($closing->total_units / $upb) : 0,
                'total_amount' => round($sold * $price, 2),
                'unit_profit' => round($sold * $costMargin, 2),
                'metadata' => json_encode([
                    'selling_price' => $price,
                    'cost_margin' => $costMargin,
                    'demo' => true,
                ]),
                'created_at' => $enteredAt,
                'updated_at' => $enteredAt,
            ];
        });
    }
}
