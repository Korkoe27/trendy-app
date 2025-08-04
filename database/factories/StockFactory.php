<?php

namespace Database\Factories;

use App\Models\{Product,User};
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
        // // Generate random values for base fields
        // $openingUnits = fake()->numberBetween(1, 1000);
        // $addedUnits = fake()->numberBetween(1, 1000);
        // $closingUnits = fake()->numberBetween(1, min(800, $openingUnits + $addedUnits)); // Ensure closing_units is valid

        // // Calculate sales_units
        // $salesUnits = $openingUnits + $addedUnits - $closingUnits;

        return [
            'product_id' => Product::inRandomOrder()->first()->id,
            'available_units' => fake()->randomFloat(2, 0, 1000), // decimal with 2 precision
            'supplier' => fake()->company,
            'cost_price' => fake()->randomFloat(2, 10, 500), // assuming price range
            'cost_margin' => fake()->randomFloat(2, 0, 100), // assuming margin range
            'notes' => fake()->optional()->paragraph,
            'available_boxes' => fake()->randomFloat(2, 0, 100), // decimal with 2 precision
        ];
    }
}