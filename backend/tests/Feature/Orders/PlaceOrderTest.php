<?php

namespace Tests\Feature\Orders;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlaceOrderTest extends TestCase
{
    use RefreshDatabase;

    private function placeOrder(array $items, ?string $key = 'key-1'): TestResponse
    {
        $headers = $key === null ? [] : ['Idempotency-Key' => $key];

        return $this->postJson('/api/orders', ['items' => $items], $headers);
    }

    // --- Happy path --------------------------------------------------------

    public function test_places_an_order_and_decrements_stock(): void
    {
        $user = Sanctum::actingAs(User::factory()->create());
        $keyboard = Product::factory()->create(['name' => 'Keyboard', 'sku' => 'KB-1', 'price' => 49.99, 'stock_quantity' => 5]);
        $mouse = Product::factory()->create(['price' => 20, 'stock_quantity' => 3]);

        $this->placeOrder([
            ['product_id' => $keyboard->id, 'quantity' => 2],
            ['product_id' => $mouse->id, 'quantity' => 1],
        ])
            ->assertCreated()
            ->assertHeader('Idempotent-Replayed', 'false')
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.total', '119.98')
            ->assertJsonCount(2, 'data.items')
            ->assertJsonFragment([
                'product_id' => $keyboard->id,
                'product_name' => 'Keyboard',
                'product_sku' => 'KB-1',
                'unit_price' => '49.99',
                'quantity' => 2,
                'line_total' => '99.98',
            ]);

        $this->assertSame(3, $keyboard->fresh()->stock_quantity);
        $this->assertSame(2, $mouse->fresh()->stock_quantity);
    }

    // --- The core scenario, sequentially -----------------------------------
    // (The true simultaneous version needs real MySQL row locks; see the
    // concurrency test. This proves the business rule itself.)

    public function test_only_one_buyer_gets_the_last_unit(): void
    {
        $product = Product::factory()->lastUnit()->create();
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        Sanctum::actingAs($alice);
        $this->placeOrder([['product_id' => $product->id, 'quantity' => 1]], 'alice-1')
            ->assertCreated();

        Sanctum::actingAs($bob);
        $this->placeOrder([['product_id' => $product->id, 'quantity' => 1]], 'bob-1')
            ->assertConflict()
            ->assertJson(['message' => 'Insufficient stock.', 'available' => 0, 'requested' => 1]);

        $this->assertSame(0, $product->fresh()->stock_quantity);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_a_short_line_rolls_back_the_whole_order(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $plenty = Product::factory()->create(['stock_quantity' => 10]);
        $scarce = Product::factory()->create(['stock_quantity' => 1]);

        $this->placeOrder([
            ['product_id' => $plenty->id, 'quantity' => 3],
            ['product_id' => $scarce->id, 'quantity' => 2],
        ])->assertConflict();

        $this->assertSame(10, $plenty->fresh()->stock_quantity);
        $this->assertSame(1, $scarce->fresh()->stock_quantity);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
    }

    public function test_a_failed_order_does_not_burn_the_idempotency_key(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $product = Product::factory()->outOfStock()->create();

        $this->placeOrder([['product_id' => $product->id, 'quantity' => 1]], 'try-again')
            ->assertConflict();

        $product->update(['stock_quantity' => 1]);

        $this->placeOrder([['product_id' => $product->id, 'quantity' => 1]], 'try-again')
            ->assertCreated();
    }

    // --- Idempotency -------------------------------------------------------

    public function test_retrying_with_the_same_key_returns_the_original_order_without_charging_stock_twice(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $product = Product::factory()->create(['stock_quantity' => 5]);
        $items = [['product_id' => $product->id, 'quantity' => 2]];

        $orderId = $this->placeOrder($items, 'same-key')->assertCreated()->json('data.id');

        $this->placeOrder($items, 'same-key')
            ->assertOk()
            ->assertHeader('Idempotent-Replayed', 'true')
            ->assertJsonPath('data.id', $orderId)
            ->assertJsonCount(1, 'data.items');

        $this->assertSame(3, $product->fresh()->stock_quantity);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_the_same_key_from_different_users_creates_separate_orders(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 5]);
        $items = [['product_id' => $product->id, 'quantity' => 1]];

        Sanctum::actingAs(User::factory()->create());
        $this->placeOrder($items, 'common-key')->assertCreated();

        Sanctum::actingAs(User::factory()->create());
        $this->placeOrder($items, 'common-key')->assertCreated();

        $this->assertSame(3, $product->fresh()->stock_quantity);
        $this->assertDatabaseCount('orders', 2);
    }

    // --- Validation & auth -------------------------------------------------

    public function test_idempotency_key_header_is_required(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $product = Product::factory()->create();

        $this->placeOrder([['product_id' => $product->id, 'quantity' => 1]], key: null)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['idempotency_key']);
    }

    public function test_items_are_validated(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $product = Product::factory()->create();

        $this->placeOrder([])
            ->assertUnprocessable()->assertJsonValidationErrors(['items']);

        $this->placeOrder([['product_id' => $product->id, 'quantity' => 0]])
            ->assertUnprocessable()->assertJsonValidationErrors(['items.0.quantity']);

        $this->placeOrder([['product_id' => 999, 'quantity' => 1]])
            ->assertUnprocessable()->assertJsonValidationErrors(['items.0.product_id']);

        $this->placeOrder([
            ['product_id' => $product->id, 'quantity' => 1],
            ['product_id' => $product->id, 'quantity' => 1],
        ])->assertUnprocessable()->assertJsonValidationErrors(['items.0.product_id', 'items.1.product_id']);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_guests_cannot_place_orders(): void
    {
        $product = Product::factory()->create();

        $this->placeOrder([['product_id' => $product->id, 'quantity' => 1]])
            ->assertUnauthorized();
    }
}
