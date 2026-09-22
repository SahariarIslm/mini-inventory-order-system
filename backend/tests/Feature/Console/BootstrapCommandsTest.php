<?php

namespace Tests\Feature\Console;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Commands run by the container entrypoint on every start.
 */
class BootstrapCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_if_empty_seeds_a_fresh_database(): void
    {
        $this->artisan('app:seed-if-empty')
            ->expectsOutputToContain('seeding demo data')
            ->assertSuccessful();

        $this->assertDatabaseHas('users', ['email' => 'admin@example.com', 'role' => 'admin']);
        $this->assertDatabaseHas('users', ['email' => 'staff@example.com', 'role' => 'staff']);
        $this->assertSame(1, Product::where('sku', 'DEMO-LAST-UNIT')->value('stock_quantity'));
    }

    public function test_seed_if_empty_is_a_no_op_on_restart(): void
    {
        $this->artisan('app:seed-if-empty')->assertSuccessful();
        $users = User::count();
        $products = Product::count();

        $this->artisan('app:seed-if-empty')
            ->expectsOutputToContain('skipping seed')
            ->assertSuccessful();

        $this->assertSame($users, User::count());
        $this->assertSame($products, Product::count());
    }

    public function test_seed_if_empty_leaves_existing_data_alone(): void
    {
        User::factory()->create();

        $this->artisan('app:seed-if-empty')->assertSuccessful();

        $this->assertSame(1, User::count());
        $this->assertSame(0, Product::count());
    }

    public function test_wait_for_database_succeeds_when_reachable(): void
    {
        $this->artisan('app:wait-for-database', ['--timeout' => 0])
            ->expectsOutputToContain('is ready')
            ->assertSuccessful();
    }

    public function test_wait_for_database_gives_up_after_the_timeout(): void
    {
        // Nothing listens on port 1: connection refused immediately.
        $original = config('database.default');
        config([
            'database.connections.unreachable' => [
                'driver' => 'mysql',
                'host' => '127.0.0.1',
                'port' => 1,
                'database' => 'nope',
                'username' => 'nope',
                'password' => '',
            ],
            'database.default' => 'unreachable',
        ]);

        $this->artisan('app:wait-for-database', ['--timeout' => 0, '--interval' => 0])
            ->expectsOutputToContain('not ready')
            ->assertFailed()
            ->run();

        // RefreshDatabase rolls back on the default connection at teardown.
        config(['database.default' => $original]);
    }
}
