<?php

namespace Database\Factories;

use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Collection;

/**
 * One row per trading day, as written by Inventory::actuallySubmitInventory().
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DailySalesSummary>
 */
class DailySalesSummaryFactory extends Factory
{
    public function definition(): array
    {
        $drinks = fake()->numberBetween(800, 4000);
        $date = now()->subDay();

        return [
            'sales_date' => $date->toDateString(),
            'product_id' => Product::inRandomOrder()->first()?->id ?? Product::factory()->create()->id,
            'drinks_total' => $drinks,
            'items_sold' => fake()->numberBetween(50, 300),
            'total_cash' => round($drinks * 0.55, 2),
            'total_momo' => round($drinks * 0.35, 2),
            'total_hubtel' => round($drinks * 0.10, 2),
            'total_money' => $drinks,
            'food_total' => 0,
            'snooker' => 0,
            'on_the_house' => 0,
            'total_profit' => round($drinks * 0.3, 2),
            'metadata' => json_encode(['demo' => true]),
            'created_at' => $date->copy()->addDay()->setTime(17, 0),
            'updated_at' => $date->copy()->addDay()->setTime(17, 0),
        ];
    }

    /**
     * Aggregate a day's daily_sales rows into the summary the app would write.
     *
     * Expected money = drinks_total + food_total - on_the_house (same as Analytics).
     * Collected money = expected - $shortfall + $overage, so a day with a shortfall leaves
     * exactly that gap for the Debt Payments page to pick up.
     *
     * @param  Collection  $dailySales  DailySales models for the day
     * @param  array{food?: float, snooker?: float, on_the_house?: float, shortfall?: float, overage?: float, momo_share?: float, hubtel_share?: float}  $extras
     */
    public function forDay(Carbon $date, Carbon $enteredAt, Collection $dailySales, array $extras = []): static
    {
        return $this->state(function () use ($date, $enteredAt, $dailySales, $extras) {
            $sold = fn ($row) => max(0, $row->opening_stock - $row->closing_stock);

            $drinksTotal = round($dailySales->sum('total_amount'), 2);
            $food = (float) ($extras['food'] ?? 0);
            $onTheHouse = (float) ($extras['on_the_house'] ?? 0);

            $expected = $drinksTotal + $food - $onTheHouse;
            $collected = max(0, round($expected - (float) ($extras['shortfall'] ?? 0) + (float) ($extras['overage'] ?? 0), 2));

            $momo = round($collected * (float) ($extras['momo_share'] ?? 0.35), 2);
            $hubtel = round($collected * (float) ($extras['hubtel_share'] ?? 0.10), 2);
            $cash = round($collected - $momo - $hubtel, 2);

            $mostSold = $dailySales->sortByDesc($sold)->first();

            return [
                'sales_date' => $date->toDateString(),
                'product_id' => $mostSold->product_id,
                'drinks_total' => $drinksTotal,
                'items_sold' => (int) $dailySales->sum($sold),
                'total_cash' => $cash,
                'total_momo' => $momo,
                'total_hubtel' => $hubtel,
                'total_money' => round($cash + $momo + $hubtel, 2),
                'food_total' => $food,
                'snooker' => (float) ($extras['snooker'] ?? 0),
                'on_the_house' => $onTheHouse,
                'total_profit' => round($dailySales->sum('unit_profit'), 2),
                'metadata' => json_encode(['demo' => true]),
                'created_at' => $enteredAt,
                'updated_at' => $enteredAt,
            ];
        });
    }
}
