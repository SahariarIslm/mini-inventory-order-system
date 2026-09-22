<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guarantees the order flow will lean on at the database level.
 */
class OrderSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_user_cannot_reuse_an_idempotency_key(): void
    {
        $user = User::factory()->create();
        Order::factory()->for($user)->create(['idempotency_key' => 'retry-me']);

        $this->expectException(UniqueConstraintViolationException::class);

        Order::factory()->for($user)->create(['idempotency_key' => 'retry-me']);
    }

    public function test_different_users_may_use_the_same_idempotency_key(): void
    {
        Order::factory()->create(['idempotency_key' => 'shared-key']);
        Order::factory()->create(['idempotency_key' => 'shared-key']);

        $this->assertDatabaseCount('orders', 2);
    }

    public function test_status_is_cast_to_an_enum_and_defaults_to_confirmed(): void
    {
        $order = Order::create([
            'user_id' => User::factory()->create()->id,
            'idempotency_key' => 'k1',
            'total' => '10.00',
        ]);

        $this->assertSame(OrderStatus::Confirmed, $order->fresh()->status);
    }

    public function test_order_items_snapshot_the_product(): void
    {
        $product = Product::factory()->create(['name' => 'Keyboard', 'sku' => 'KB-1', 'price' => 49.99]);
        $item = OrderItem::factory()->for($product)->create(['quantity' => 2]);

        $this->assertSame('Keyboard', $item->product_name);
        $this->assertSame('KB-1', $item->product_sku);
        $this->assertSame('49.99', $item->unit_price);
        $this->assertSame('99.98', $item->line_total);
        $this->assertTrue($item->order->items->contains($item));
    }

    public function test_deleting_a_product_keeps_order_history(): void
    {
        $product = Product::factory()->create(['name' => 'Keyboard']);
        $item = OrderItem::factory()->for($product)->create();

        $product->delete();

        $item->refresh();
        $this->assertNull($item->product_id);
        $this->assertSame('Keyboard', $item->product_name);
    }

    public function test_deleting_an_order_removes_its_items(): void
    {
        $item = OrderItem::factory()->create();

        $item->order->delete();

        $this->assertModelMissing($item);
    }
}
