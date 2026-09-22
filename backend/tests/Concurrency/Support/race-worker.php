<?php

/*
 * One racer in a concurrency test: a separate PHP process with its own
 * MySQL connection, so row locks genuinely contend.
 *
 * Usage: php race-worker.php <barrier_dir> <action> <payload_json>
 *   place   {"user_id": 1, "key": "k", "items": [{"product_id": 1, "quantity": 1}]}
 *   cancel  {"order_id": 1}
 *
 * Boots the app and connects first, then signals ready and waits at a file
 * barrier, so every racer hits OrderService at the same instant.
 * Prints a single JSON result line.
 */

use App\Exceptions\InsufficientStockException;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

[, $barrierDir, $action, $payloadJson] = $argv;

require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$result = static fn (array $data) => fwrite(STDOUT, json_encode($data).PHP_EOL);

$database = (string) config('database.connections.'.config('database.default').'.database');
if (! str_ends_with($database, '_testing')) {
    $result(['status' => 'error', 'message' => "Refusing to run against [{$database}]."]);
    exit(1);
}

$payload = json_decode($payloadJson, true);
$orders = $app->make(OrderService::class);

// Resolve everything up front so only the service call is inside the race.
$run = match ($action) {
    'place' => (function () use ($orders, $payload) {
        $user = User::findOrFail($payload['user_id']);

        return function () use ($orders, $user, $payload) {
            $order = $orders->place($user, $payload['key'], $payload['items']);

            return ['status' => $order->wasRecentlyCreated ? 'created' : 'replayed', 'order_id' => $order->id];
        };
    })(),
    'cancel' => (function () use ($orders, $payload) {
        $order = Order::findOrFail($payload['order_id']);

        return fn () => ['status' => $orders->cancel($order)->status->value, 'order_id' => $order->id];
    })(),
};

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
    $result($run());
} catch (InsufficientStockException $e) {
    $result(['status' => 'insufficient', 'available' => $e->product->stock_quantity]);
} catch (Throwable $e) {
    $result(['status' => 'error', 'message' => get_class($e).': '.$e->getMessage()]);
    exit(1);
}
