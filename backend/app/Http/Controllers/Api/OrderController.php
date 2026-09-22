<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PlaceOrderRequest;
use App\Http\Resources\OrderResource;
use App\Services\OrderService;

class OrderController extends Controller
{
    public function store(PlaceOrderRequest $request, OrderService $orders)
    {
        $order = $orders->place(
            $request->user(),
            $request->validated('idempotency_key'),
            $request->validated('items'),
        );

        // 201 for a new order; 200 + marker header when replaying a retry.
        return (new OrderResource($order))
            ->response()
            ->setStatusCode($order->wasRecentlyCreated ? 201 : 200)
            ->header('Idempotent-Replayed', $order->wasRecentlyCreated ? 'false' : 'true');
    }
}
