<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Supplier>
 */
class SupplierFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'=> fake()->company(),
            'phone'=> fake()->phoneNumber(),
            'email'=> fake()->unique()->optional()->safeEmail(),
            'address'=> fake()->optional()->address(),
            'metadata'=> null,
        ];
    }
}
