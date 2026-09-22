<?php

namespace Tests\Feature\Stock;

use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockServiceTest extends TestCase
{
    use RefreshDatabase;

    private StockService $stock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stock = new StockService;
    }

    public function test_positive_delta_restocks(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 5]);

        $updated = $this->stock->adjust($product, 3);

        $this->assertSame(8, $updated->stock_quantity);
        $this->assertSame(8, $product->fresh()->stock_quantity);
    }

    public function test_negative_delta_decrements(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 5]);

        $this->stock->adjust($product, -2);

        $this->assertSame(3, $product->fresh()->stock_quantity);
    }

    public function test_can_take_the_last_unit_down_to_exactly_zero(): void
    {
        $product = Product::factory()->lastUnit()->create();

        $this->stock->adjust($product, -1);

        $this->assertSame(0, $product->fresh()->stock_quantity);
    }

    public function test_refuses_to_go_below_zero_and_leaves_stock_unchanged(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 2]);

        try {
            $this->stock->adjust($product, -3);
            $this->fail('Expected InsufficientStockException.');
        } catch (InsufficientStockException $e) {
            $this->assertSame(2, $e->product->stock_quantity);
            $this->assertSame(3, $e->requested);
        }

        $this->assertSame(2, $product->fresh()->stock_quantity);
    }

    public function test_decides_against_the_database_value_not_a_stale_model(): void
    {
        // Simulates the race: this request loaded the product while it had
        // 5 units, then another request bought 4 before we got the lock.
        $stale = Product::factory()->create(['stock_quantity' => 5]);
        Product::whereKey($stale->id)->update(['stock_quantity' => 1]);

        $this->assertSame(5, $stale->stock_quantity);

        $this->expectException(InsufficientStockException::class);

        $this->stock->adjust($stale, -3);
    }

    public function test_stale_model_restock_adds_to_the_database_value(): void
    {
        $stale = Product::factory()->create(['stock_quantity' => 5]);
        Product::whereKey($stale->id)->update(['stock_quantity' => 1]);

        $this->stock->adjust($stale, 10);

        // 1 + 10, not 5 + 10: no lost update.
        $this->assertSame(11, $stale->fresh()->stock_quantity);
    }
}
