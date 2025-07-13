<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Categories>
 */
class CategoriesFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
        public function definition(): array
            {
                static $categories = [
                    ['name' => 'drinks', 'pricing_model' => 'per_unit'],
                    ['name' => 'food', 'pricing_model' => 'per_plate'],
                    ['name' => 'games', 'pricing_model' => 'per_slot'],
                    ['name' => 'snacks', 'pricing_model' => 'per_unit'],
                    ['name' => 'services', 'pricing_model' => 'flat_fee'],
                ];

                // Get a category or generate a random one if all predefined are used
                if (count($categories) > 0) {
                    $category = array_shift($categories);
                    return [
                        'name' => $category['name'],
                        'pricing_model' => $category['pricing_model'],
                    ];
                }

                // Fallback for additional records beyond the predefined ones
                $pricingModels = ['per_unit', 'per_plate', 'per_slot', 'flat_fee'];
                
                return [
                    'name' => $this->faker->unique()->word(),
                    'pricing_model' => $this->faker->randomElement($pricingModels),
                ];
            }
}
