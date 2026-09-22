<?php

namespace Tests\Feature\Products;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        return Sanctum::actingAs(User::factory()->admin()->create());
    }

    private function actingAsStaff(): User
    {
        return Sanctum::actingAs(User::factory()->create());
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Mechanical Keyboard',
            'sku' => 'KB-001',
            'description' => 'Tactile switches.',
            'price' => 89.5,
            'stock_quantity' => 10,
        ], $overrides);
    }

    // --- Reading -----------------------------------------------------------

    public function test_guests_cannot_access_products(): void
    {
        $this->getJson('/api/products')->assertUnauthorized();
    }

    public function test_staff_can_list_products_paginated(): void
    {
        $this->actingAsStaff();
        Product::factory()->count(20)->create();

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.total', 20)
            ->assertJsonStructure(['data' => [['id', 'name', 'sku', 'description', 'price', 'stock_quantity', 'in_stock']], 'links', 'meta']);
    }

    public function test_staff_can_view_a_single_product(): void
    {
        $this->actingAsStaff();
        $product = Product::factory()->lastUnit()->create(['price' => 149.99]);

        $this->getJson("/api/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.price', '149.99')
            ->assertJsonPath('data.stock_quantity', 1)
            ->assertJsonPath('data.in_stock', true);
    }

    public function test_out_of_stock_product_is_flagged(): void
    {
        $this->actingAsStaff();
        $product = Product::factory()->outOfStock()->create();

        $this->getJson("/api/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.in_stock', false);
    }

    public function test_missing_product_returns_json_404(): void
    {
        $this->actingAsStaff();

        $this->getJson('/api/products/999')->assertNotFound();
    }

    // --- Creating ----------------------------------------------------------

    public function test_admin_can_create_a_product(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/products', $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('data.sku', 'KB-001')
            ->assertJsonPath('data.price', '89.50')
            ->assertJsonPath('data.stock_quantity', 10);

        $this->assertDatabaseHas('products', ['sku' => 'KB-001', 'stock_quantity' => 10]);
    }

    public function test_staff_cannot_create_a_product(): void
    {
        $this->actingAsStaff();

        $this->postJson('/api/products', $this->validPayload())->assertForbidden();

        $this->assertDatabaseCount('products', 0);
    }

    public function test_create_validates_required_fields(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/products', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'sku', 'price', 'stock_quantity']);
    }

    public function test_create_rejects_negative_price_and_stock(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/products', $this->validPayload(['price' => -1, 'stock_quantity' => -5]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['price', 'stock_quantity']);
    }

    public function test_create_rejects_price_with_more_than_two_decimals(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/products', $this->validPayload(['price' => 9.999]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['price']);
    }

    public function test_create_rejects_duplicate_sku(): void
    {
        $this->actingAsAdmin();
        Product::factory()->create(['sku' => 'KB-001']);

        $this->postJson('/api/products', $this->validPayload(['sku' => 'KB-001']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sku']);
    }

    // --- Updating ----------------------------------------------------------

    public function test_admin_can_partially_update_a_product(): void
    {
        $this->actingAsAdmin();
        $product = Product::factory()->create(['name' => 'Old Name', 'sku' => 'KB-001']);

        $this->patchJson("/api/products/{$product->id}", ['name' => 'New Name'])
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.sku', 'KB-001');
    }

    public function test_update_allows_keeping_the_same_sku(): void
    {
        $this->actingAsAdmin();
        $product = Product::factory()->create(['sku' => 'KB-001']);

        $this->patchJson("/api/products/{$product->id}", ['sku' => 'KB-001', 'price' => 10])
            ->assertOk();
    }

    public function test_update_rejects_another_products_sku(): void
    {
        $this->actingAsAdmin();
        Product::factory()->create(['sku' => 'TAKEN']);
        $product = Product::factory()->create();

        $this->patchJson("/api/products/{$product->id}", ['sku' => 'TAKEN'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sku']);
    }

    public function test_update_cannot_set_stock_directly(): void
    {
        $this->actingAsAdmin();
        $product = Product::factory()->create(['stock_quantity' => 3]);

        $this->patchJson("/api/products/{$product->id}", ['stock_quantity' => 100])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['stock_quantity']);

        $this->assertSame(3, $product->fresh()->stock_quantity);
    }

    public function test_staff_cannot_update_a_product(): void
    {
        $this->actingAsStaff();
        $product = Product::factory()->create(['name' => 'Original']);

        $this->patchJson("/api/products/{$product->id}", ['name' => 'Hacked'])->assertForbidden();

        $this->assertSame('Original', $product->fresh()->name);
    }

    // --- Deleting ----------------------------------------------------------

    public function test_admin_can_delete_a_product(): void
    {
        $this->actingAsAdmin();
        $product = Product::factory()->create();

        $this->deleteJson("/api/products/{$product->id}")->assertNoContent();

        $this->assertModelMissing($product);
    }

    public function test_staff_cannot_delete_a_product(): void
    {
        $this->actingAsStaff();
        $product = Product::factory()->create();

        $this->deleteJson("/api/products/{$product->id}")->assertForbidden();

        $this->assertModelExists($product);
    }
}
