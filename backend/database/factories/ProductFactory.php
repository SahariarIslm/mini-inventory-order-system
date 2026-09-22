<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => ucwords(fake()->words(3, true)),
            'sku' => fake()->unique()->bothify('SKU-####-????'),
            'description' => fake()->sentence(),
            'price' => fake()->randomFloat(2, 1, 500),
            'stock_quantity' => fake()->numberBetween(5, 100),
        ];
    }

    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock_quantity' => 0,
        ]);
    }

    public function lastUnit(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock_quantity' => 1,
        ]);
    }
}
