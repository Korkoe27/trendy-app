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
        // Generate random values for base fields
        $openingUnits = fake()->numberBetween(1, 1000);
        $addedUnits = fake()->numberBetween(1, 1000);
        $closingUnits = fake()->numberBetween(1, min(800, $openingUnits + $addedUnits)); // Ensure closing_units is valid

        // Calculate sales_units
        $salesUnits = $openingUnits + $addedUnits - $closingUnits;

        return [
            'product_id' => Product::inRandomOrder()->first()->id,
            'opening_units' => $openingUnits,
            'added_units' => $addedUnits,
            'closing_units' => $closingUnits,
            'closing_boxes' => fake()->numberBetween(1, 50),
            'sales_units' => $salesUnits,
            'sales_boxes' => $salesUnits / 24, // Calculate based on sales_units
            // 'created_by' => User::inRandomOrder()->first()->id,
        ];
    }
}