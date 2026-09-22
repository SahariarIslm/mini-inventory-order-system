<?php

namespace Tests\Feature\Orders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ViewOrdersTest extends TestCase
{
    use RefreshDatabase;

    // --- Listing -----------------------------------------------------------

    public function test_staff_only_see_their_own_orders(): void
    {
        $staff = Sanctum::actingAs(User::factory()->create());
        $mine = Order::factory()->for($staff)->count(2)->create();
        Order::factory()->count(3)->create();

        $response = $this->getJson('/api/orders')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2);

        $this->assertEqualsCanonicalizing($mine->pluck('id')->all(), array_column($response->json('data'), 'id'));
    }

    public function test_admins_see_every_order(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        Order::factory()->count(3)->create();

        $this->getJson('/api/orders')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_orders_are_listed_newest_first_with_items_and_placer(): void
    {
        $staff = Sanctum::actingAs(User::factory()->create(['name' => 'Sam Staff']));
        $older = Order::factory()->for($staff)->create();
        $newer = Order::factory()->for($staff)->create();
        OrderItem::factory()->for($newer)->count(2)->create();

        $this->getJson('/api/orders')
            ->assertOk()
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $older->id)
            ->assertJsonCount(2, 'data.0.items')
            ->assertJsonPath('data.0.user.name', 'Sam Staff');
    }

    public function test_listing_is_paginated(): void
    {
        $staff = Sanctum::actingAs(User::factory()->create());
        Order::factory()->for($staff)->count(20)->create();

        $this->getJson('/api/orders')
            ->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.total', 20);
    }

    // --- Single order ------------------------------------------------------

    public function test_staff_can_view_their_own_order(): void
    {
        $staff = Sanctum::actingAs(User::factory()->create());
        $order = Order::factory()->for($staff)->create();
        OrderItem::factory()->for($order)->create();

        $this->getJson("/api/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonCount(1, 'data.items');
    }

    public function test_staff_cannot_view_someone_elses_order(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $other = Order::factory()->create();

        $this->getJson("/api/orders/{$other->id}")->assertForbidden();
    }

    public function test_admins_can_view_any_order(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $order = Order::factory()->create();

        $this->getJson("/api/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $order->id);
    }

    public function test_missing_order_returns_404(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/orders/999')->assertNotFound();
    }

    public function test_guests_cannot_view_orders(): void
    {
        $order = Order::factory()->create();

        $this->getJson('/api/orders')->assertUnauthorized();
        $this->getJson("/api/orders/{$order->id}")->assertUnauthorized();
    }
}
