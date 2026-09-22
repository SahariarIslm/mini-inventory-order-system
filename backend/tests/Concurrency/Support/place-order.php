<?php

/*
 * One "buyer" in a concurrency test: a separate PHP process with its own
 * MySQL connection, so row locks genuinely contend.
 *
 * Usage: php place-order.php <user_id> <idempotency_key> <barrier_dir> <items_json>
 *
 * Boots the app and connects first, then signals ready and waits at a file
 * barrier, so every buyer calls OrderService::place() at the same instant.
 * Prints a single JSON result line.
 */

use App\Exceptions\InsufficientStockException;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

[, $userId, $key, $barrierDir, $itemsJson] = $argv;

require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$result = static fn (array $data) => fwrite(STDOUT, json_encode($data).PHP_EOL);

$database = (string) config('database.connections.'.config('database.default').'.database');
if (! str_ends_with($database, '_testing')) {
    $result(['status' => 'error', 'message' => "Refusing to run against [{$database}]."]);
    exit(1);
}

$user = User::findOrFail($userId);
$items = json_decode($itemsJson, true);
$orders = $app->make(OrderService::class);
DB::connection()->getPdo(); // connect before the barrier, not inside the race

touch($barrierDir.'/ready-'.getmypid());

$deadline = microtime(true) + 60;
while (! file_exists($barrierDir.'/go')) {
    if (microtime(true) > $deadline) {
        $result(['status' => 'error', 'message' => 'Timed out waiting at barrier.']);
        exit(1);
    }
    usleep(1000);
}

try {
    $order = $orders->place($user, $key, $items);

    $result([
        'status' => $order->wasRecentlyCreated ? 'created' : 'replayed',
        'order_id' => $order->id,
    ]);
} catch (InsufficientStockException $e) {
    $result(['status' => 'insufficient', 'available' => $e->product->stock_quantity]);
} catch (Throwable $e) {
    $result(['status' => 'error', 'message' => get_class($e).': '.$e->getMessage()]);
    exit(1);
}
