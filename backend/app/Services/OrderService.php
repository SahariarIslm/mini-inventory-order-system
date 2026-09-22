<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(private StockService $stock) {}

    /**
     * Place an order, at most once per (user, idempotency key).
     *
     * Two independent mechanisms:
     *  - Idempotency: the order row is inserted first, so the unique
     *    (user_id, idempotency_key) index claims the key atomically. A
     *    concurrent retry blocks on that insert, then fails with a unique
     *    violation once the first commits, and gets the original order back.
     *  - Stock: each product row is locked (SELECT ... FOR UPDATE via
     *    StockService) in ascending id order — a consistent lock order so two
     *    multi-item orders can't deadlock — and checked against the locked
     *    value. If any line is short, the whole transaction rolls back
     *    (including the order row, so the key stays free for a retry).
     *
     * Returns the order; $order->wasRecentlyCreated is false for a replay.
     *
     * @param  array<int, array{product_id: int, quantity: int}>  $items
     *
     * @throws \App\Exceptions\InsufficientStockException
     */
    public function place(User $user, string $idempotencyKey, array $items): Order
    {
        if ($existing = $this->findByKey($user, $idempotencyKey)) {
            return $existing;
        }

        try {
            return DB::transaction(
                fn () => $this->createOrder($user, $idempotencyKey, $items),
                attempts: 3, // retried only on deadlock / lock-wait errors
            );
        } catch (UniqueConstraintViolationException $e) {
            // A concurrent request with the same key committed first.
            return $this->findByKey($user, $idempotencyKey) ?? throw $e;
        }
    }

    private function createOrder(User $user, string $idempotencyKey, array $items): Order
    {
        $order = $user->orders()->create([
            'idempotency_key' => $idempotencyKey,
            'status' => OrderStatus::Confirmed,
            'total' => 0,
        ]);

        $products = Product::findMany(array_column($items, 'product_id'))->keyBy('id');
        $total = '0.00';

        foreach (collect($items)->sortBy('product_id') as $line) {
            $product = $products->get($line['product_id'])
                ?? throw (new ModelNotFoundException)->setModel(Product::class, [$line['product_id']]);

            // Returns the freshly locked row; price and name come from it too.
            $locked = $this->stock->adjust($product, -$line['quantity']);
            $lineTotal = bcmul($locked->price, (string) $line['quantity'], 2);

            $order->items()->create([
                'product_id' => $locked->id,
                'product_name' => $locked->name,
                'product_sku' => $locked->sku,
                'unit_price' => $locked->price,
                'quantity' => $line['quantity'],
                'line_total' => $lineTotal,
            ]);

            $total = bcadd($total, $lineTotal, 2);
        }

        $order->update(['total' => $total]);

        return $order->load('items');
    }

    private function findByKey(User $user, string $idempotencyKey): ?Order
    {
        return $user->orders()
            ->where('idempotency_key', $idempotencyKey)
            ->with('items')
            ->first();
    }
}
