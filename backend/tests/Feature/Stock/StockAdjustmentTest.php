<?php

namespace Tests\Feature\Stock;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StockAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    private function url(Product $product): string
    {
        return "/api/products/{$product->id}/stock-adjustments";
    }

    public function test_admin_can_restock_a_product(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $product = Product::factory()->outOfStock()->create();

        $this->postJson($this->url($product), ['quantity' => 25])
            ->assertOk()
            ->assertJsonPath('data.stock_quantity', 25)
            ->assertJsonPath('data.in_stock', true);
    }

    public function test_admin_can_write_off_stock(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $product = Product::factory()->create(['stock_quantity' => 10]);

        $this->postJson($this->url($product), ['quantity' => -4])
            ->assertOk()
            ->assertJsonPath('data.stock_quantity', 6);
    }

    public function test_write_off_beyond_available_stock_returns_409(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $product = Product::factory()->create(['stock_quantity' => 3]);

        $this->postJson($this->url($product), ['quantity' => -5])
            ->assertConflict()
            ->assertExactJson([
                'message' => 'Insufficient stock.',
                'product_id' => $product->id,
                'available' => 3,
                'requested' => 5,
            ]);

        $this->assertSame(3, $product->fresh()->stock_quantity);
    }

    public function test_quantity_is_required_and_must_be_a_non_zero_integer(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $product = Product::factory()->create();

        $this->postJson($this->url($product), [])
            ->assertUnprocessable()->assertJsonValidationErrors(['quantity']);

        $this->postJson($this->url($product), ['quantity' => 0])
            ->assertUnprocessable()->assertJsonValidationErrors(['quantity']);

        $this->postJson($this->url($product), ['quantity' => 1.5])
            ->assertUnprocessable()->assertJsonValidationErrors(['quantity']);
    }

    public function test_staff_cannot_adjust_stock(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $product = Product::factory()->create(['stock_quantity' => 3]);

        $this->postJson($this->url($product), ['quantity' => 100])->assertForbidden();

        $this->assertSame(3, $product->fresh()->stock_quantity);
    }

    public function test_guests_cannot_adjust_stock(): void
    {
        $product = Product::factory()->create();

        $this->postJson($this->url($product), ['quantity' => 1])->assertUnauthorized();
    }

    public function test_adjusting_a_missing_product_returns_404(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/products/999/stock-adjustments', ['quantity' => 1])->assertNotFound();
    }
}
