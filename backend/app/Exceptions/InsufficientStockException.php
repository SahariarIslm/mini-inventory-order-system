<?php

namespace App\Exceptions;

use App\Models\Product;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    public function __construct(
        public readonly Product $product,
        public readonly int $requested,
    ) {
        parent::__construct("Insufficient stock for product [{$product->sku}].");
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => 'Insufficient stock.',
            'product_id' => $this->product->id,
            'available' => $this->product->stock_quantity,
            'requested' => $this->requested,
        ], 409);
    }
}
