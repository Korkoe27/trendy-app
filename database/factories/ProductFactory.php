<?php

namespace Database\Factories;

use App\Models\Categories;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Calculate cost and selling prices for profit
        $costPrice = fake()->randomFloat(2, 5, 50);
        $profit = fake()->numberBetween(1, 20);
        $sellingPrice = $costPrice + $profit;

        return [
            'name' => fake()->unique()->word(),
            'category_id' => Categories::inRandomOrder()->first()->id, // assumes a Category factory exists
            'sku' => fake()->unique()->bothify('SKU-####-???'),
            'cost_price' => $costPrice,
            'selling_price' => $sellingPrice,
            'unit_profit' => $profit,
            'units_per_box' => fake()->randomFloat(2, 1, 50),
            'is_active' => fake()->boolean(90),
        ];
    }
}
