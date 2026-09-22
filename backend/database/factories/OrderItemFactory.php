<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrderItem>
 */
class OrderItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'product_name' => fn (array $attributes) => Product::find($attributes['product_id'])->name,
            'product_sku' => fn (array $attributes) => Product::find($attributes['product_id'])->sku,
            'unit_price' => fn (array $attributes) => Product::find($attributes['product_id'])->price,
            'quantity' => fake()->numberBetween(1, 3),
            'line_total' => fn (array $attributes) => bcmul((string) $attributes['unit_price'], (string) $attributes['quantity'], 2),
        ];
    }
}
