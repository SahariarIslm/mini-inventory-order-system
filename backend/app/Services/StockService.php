<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class StockService
{
    /**
     * Apply a relative stock change (positive to restock, negative to remove).
     *
     * The passed model's stock_quantity is never trusted: the row is re-read
     * under SELECT ... FOR UPDATE, so concurrent adjustments and order
     * placements on the same product are serialised and each one decides
     * against the true current value. Safe to call inside an outer
     * transaction (it becomes a savepoint and the lock is held until the
     * outer transaction commits).
     *
     * @throws InsufficientStockException if the change would take stock below zero
     */
    public function adjust(Product $product, int $delta): Product
    {
        return DB::transaction(function () use ($product, $delta) {
            $locked = Product::whereKey($product->getKey())->lockForUpdate()->firstOrFail();

            $newQuantity = $locked->stock_quantity + $delta;

            if ($newQuantity < 0) {
                throw new InsufficientStockException($locked, abs($delta));
            }

            $locked->update(['stock_quantity' => $newQuantity]);

            return $locked;
        });
    }
}
