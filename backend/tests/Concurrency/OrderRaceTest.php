<?php

namespace Tests\Concurrency;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Real concurrent purchases against MySQL: each buyer is a separate process
 * with its own connection, released simultaneously from a barrier.
 *
 * Uses DatabaseTruncation rather than RefreshDatabase, because the buyer
 * processes can only see committed data.
 */
class OrderRaceTest extends TestCase
{
    use DatabaseTruncation;

    public function test_two_buyers_racing_for_the_last_unit_exactly_one_wins(): void
    {
        $product = Product::factory()->lastUnit()->create();
        [$alice, $bob] = User::factory()->count(2)->create();

        $results = $this->race([
            $this->place($alice, 'alice-key', [[$product, 1]]),
            $this->place($bob, 'bob-key', [[$product, 1]]),
        ]);

        $this->assertStatuses(['created' => 1, 'insufficient' => 1], $results);
        $this->assertSame(0, $product->fresh()->stock_quantity);
        $this->assertSame(1, Order::count());
    }

    public function test_many_buyers_cannot_oversell(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 3]);
        $buyers = User::factory()->count(10)->create();

        $results = $this->race($buyers->map(
            fn (User $user) => $this->place($user, "key-{$user->id}", [[$product, 1]])
        )->all());

        $this->assertStatuses(['created' => 3, 'insufficient' => 7], $results);
        $this->assertSame(0, $product->fresh()->stock_quantity);
        $this->assertSame(3, (int) OrderItem::sum('quantity'));
    }

    public function test_concurrent_retries_with_the_same_key_create_exactly_one_order(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 10]);
        $user = User::factory()->create();

        $results = $this->race(array_fill(0, 5, $this->place($user, 'double-click', [[$product, 2]])));

        $this->assertStatuses(['created' => 1, 'replayed' => 4], $results);
        $this->assertCount(1, array_unique(array_column($results, 'order_id')), 'Every retry must get the same order.');
        $this->assertSame(8, $product->fresh()->stock_quantity);
        $this->assertSame(1, Order::count());
    }

    public function test_orders_locking_the_same_products_in_opposite_order_do_not_deadlock(): void
    {
        $first = Product::factory()->create(['stock_quantity' => 100]);
        $second = Product::factory()->create(['stock_quantity' => 100]);
        $buyers = User::factory()->count(6)->create();

        // Half the buyers list the products one way round, half the other.
        $results = $this->race($buyers->map(fn (User $user, int $i) => $this->place(
            $user,
            "key-{$user->id}",
            $i % 2 === 0 ? [[$first, 1], [$second, 1]] : [[$second, 1], [$first, 1]],
        ))->all());

        $this->assertStatuses(['created' => 6], $results);
        $this->assertSame(94, $first->fresh()->stock_quantity);
        $this->assertSame(94, $second->fresh()->stock_quantity);
    }

    public function test_concurrent_cancels_return_stock_exactly_once(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 5]);
        $order = app(OrderService::class)->place(User::factory()->create(), 'to-cancel', [
            ['product_id' => $product->id, 'quantity' => 2],
        ]);

        $this->assertSame(3, $product->fresh()->stock_quantity);

        $results = $this->race(array_fill(0, 5, ['cancel', ['order_id' => $order->id]]));

        $this->assertStatuses(['cancelled' => 5], $results);
        $this->assertSame(5, $product->fresh()->stock_quantity);
    }

    /**
     * @param  array<int, array{0: Product, 1: int}>  $lines
     * @return array{0: string, 1: array}
     */
    private function place(User $user, string $key, array $lines): array
    {
        return ['place', [
            'user_id' => $user->id,
            'key' => $key,
            'items' => array_map(fn (array $line) => ['product_id' => $line[0]->id, 'quantity' => $line[1]], $lines),
        ]];
    }

    /**
     * Start one worker process per job, wait until all are booted and
     * connected, then release them together. Returns each worker's decoded
     * JSON result.
     *
     * @param  array<int, array{0: string, 1: array}>  $jobs  [action, payload] pairs for race-worker.php
     */
    private function race(array $jobs): array
    {
        $barrier = sys_get_temp_dir().'/order-race-'.Str::random(10);
        File::ensureDirectoryExists($barrier);

        try {
            $env = [
                'APP_ENV' => 'testing',
                'DB_CONNECTION' => 'mysql',
                'DB_DATABASE' => config('database.connections.mysql.database'),
                'CACHE_STORE' => 'array',
                'SESSION_DRIVER' => 'array',
                'QUEUE_CONNECTION' => 'sync',
            ];

            $processes = array_map(fn (array $job) => Process::env($env)->timeout(120)->start([
                PHP_BINARY,
                base_path('tests/Concurrency/Support/race-worker.php'),
                $barrier,
                $job[0],
                json_encode($job[1]),
            ]), $jobs);

            $this->waitUntilAllReady($barrier, $processes);
            touch($barrier.'/go');

            return array_map(function ($process) {
                $result = $process->wait();
                $decoded = json_decode(trim($result->output()), true);

                $this->assertIsArray($decoded, "Buyer produced no result.\nstdout: {$result->output()}\nstderr: {$result->errorOutput()}");
                $this->assertNotSame('error', $decoded['status'], 'Buyer failed: '.($decoded['message'] ?? ''));

                return $decoded;
            }, $processes);
        } finally {
            File::deleteDirectory($barrier);
        }
    }

    private function waitUntilAllReady(string $barrier, array $processes): void
    {
        $deadline = microtime(true) + 90;

        while (count(glob($barrier.'/ready-*')) < count($processes)) {
            foreach ($processes as $process) {
                if (! $process->running()) {
                    $result = $process->wait();
                    $this->fail("A buyer exited before the barrier.\nstdout: {$result->output()}\nstderr: {$result->errorOutput()}");
                }
            }

            if (microtime(true) > $deadline) {
                $this->fail('Buyers did not all become ready in time.');
            }

            usleep(20_000);
        }
    }

    private function assertStatuses(array $expected, array $results): void
    {
        $actual = array_count_values(array_column($results, 'status'));
        ksort($expected);
        ksort($actual);

        $this->assertSame($expected, $actual, 'Unexpected outcome mix: '.json_encode($results));
    }
}
