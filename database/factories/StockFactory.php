<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Stock>
 */
class StockFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Select a random product
        $product = Product::inRandomOrder()->first();

        // Ensure there is at least one product
        if (!$product) {
            $product = Product::factory()->create();
        }

        // Generate values
        $total_units = fake()->numberBetween(20, 100);
        $total_cost = fake()->randomFloat(2, 100, 1000);
        $cost_price = $total_cost / $total_units;
        $cost_margin = $product->selling_price - $cost_price;

        return [
            'product_id' => $product->id,
            'total_units' => $total_units,
            'free_units' => fake()->optional()->numberBetween(0, 100),
            'supplier' => fake()->company(),
            'total_cost' => $total_cost,
            'cost_price' => round($cost_price, 2),
            'cost_margin' => round($cost_margin, 2),
            'notes' => fake()->optional()->paragraph(),
            'restock_date' => fake()->optional()->dateTimeBetween('-1 month', now()),
        ];
    }
}
