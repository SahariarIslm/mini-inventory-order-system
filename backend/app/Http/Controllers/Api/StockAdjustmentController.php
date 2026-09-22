<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdjustStockRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\StockService;

class StockAdjustmentController extends Controller
{
    public function __invoke(AdjustStockRequest $request, Product $product, StockService $stock)
    {
        $product = $stock->adjust($product, $request->integer('quantity'));

        return new ProductResource($product);
    }
}
