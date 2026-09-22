<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CancelOrderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Place a real order through the service so stock is genuinely taken.
     */
    private function placeOrder(User $user, array $lines): Order
    {
        return app(OrderService::class)->place(
            $user,
            'key-'.uniqid(),
            array_map(fn (array $line) => ['product_id' => $line[0]->id, 'quantity' => $line[1]], $lines),
        );
    }

    public function test_cancelling_returns_stock_for_every_line(): void
    {
        $staff = Sanctum::actingAs(User::factory()->create());
        $keyboard = Product::factory()->create(['stock_quantity' => 5]);
        $mouse = Product::factory()->create(['stock_quantity' => 5]);
        $order = $this->placeOrder($staff, [[$keyboard, 2], [$mouse, 3]]);

        $this->assertSame(3, $keyboard->fresh()->stock_quantity);

        $this->postJson("/api/orders/{$order->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonCount(2, 'data.items');

        $this->assertSame(5, $keyboard->fresh()->stock_quantity);
        $this->assertSame(5, $mouse->fresh()->stock_quantity);
    }

    public function test_cancelling_the_last_unit_makes_it_buyable_again(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $product = Product::factory()->lastUnit()->create();
        $order = $this->placeOrder($alice, [[$product, 1]]);

        Sanctum::actingAs($alice);
        $this->postJson("/api/orders/{$order->id}/cancel")->assertOk();

        Sanctum::actingAs($bob);
        $this->postJson('/api/orders', ['items' => [['product_id' => $product->id, 'quantity' => 1]]], ['Idempotency-Key' => 'bob-1'])
            ->assertCreated();

        $this->assertSame(0, $product->fresh()->stock_quantity);
    }

    public function test_cancelling_twice_does_not_restock_twice(): void
    {
        $staff = Sanctum::actingAs(User::factory()->create());
        $product = Product::factory()->create(['stock_quantity' => 5]);
        $order = $this->placeOrder($staff, [[$product, 2]]);

        $this->postJson("/api/orders/{$order->id}/cancel")->assertOk();
        $this->postJson("/api/orders/{$order->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertSame(5, $product->fresh()->stock_quantity);
    }

    public function test_service_checks_the_locked_status_not_a_stale_model(): void
    {
        // Simulates the race: this request loaded the order while confirmed,
        // then another request cancelled it before we got the lock.
        $product = Product::factory()->create(['stock_quantity' => 5]);
        $stale = $this->placeOrder(User::factory()->create(), [[$product, 2]]);
        app(OrderService::class)->cancel(Order::find($stale->id));

        $this->assertSame(OrderStatus::Confirmed, $stale->status);

        app(OrderService::class)->cancel($stale);

        $this->assertSame(5, $product->fresh()->stock_quantity);
    }

    public function test_lines_for_deleted_products_are_skipped(): void
    {
        $staff = Sanctum::actingAs(User::factory()->create());
        $kept = Product::factory()->create(['stock_quantity' => 5]);
        $deleted = Product::factory()->create(['stock_quantity' => 5]);
        $order = $this->placeOrder($staff, [[$kept, 1], [$deleted, 1]]);
        $deleted->delete();

        $this->postJson("/api/orders/{$order->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertSame(5, $kept->fresh()->stock_quantity);
    }

    public function test_admins_can_cancel_any_order(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 5]);
        $order = $this->placeOrder(User::factory()->create(), [[$product, 1]]);

        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson("/api/orders/{$order->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_staff_cannot_cancel_someone_elses_order(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 5]);
        $order = $this->placeOrder(User::factory()->create(), [[$product, 1]]);

        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/orders/{$order->id}/cancel")->assertForbidden();

        $this->assertSame(OrderStatus::Confirmed, $order->fresh()->status);
        $this->assertSame(4, $product->fresh()->stock_quantity);
    }

    public function test_cancelling_a_missing_order_returns_404(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/orders/999/cancel')->assertNotFound();
    }

    public function test_guests_cannot_cancel_orders(): void
    {
        $order = OrderItem::factory()->create()->order;

        $this->postJson("/api/orders/{$order->id}/cancel")->assertUnauthorized();
    }
}
