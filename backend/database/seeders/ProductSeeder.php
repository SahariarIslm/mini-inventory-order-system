<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        Product::factory()->count(8)->create();

        Product::factory()->outOfStock()->create([
            'name' => 'Sold Out Headphones',
            'sku' => 'DEMO-SOLD-OUT',
        ]);

        // The contested product from the core scenario: two customers race
        // to buy this single remaining unit.
        Product::factory()->lastUnit()->create([
            'name' => 'Limited Edition Keyboard',
            'sku' => 'DEMO-LAST-UNIT',
            'price' => 149.99,
        ]);
    }
}
