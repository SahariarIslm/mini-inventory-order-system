<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PlaceOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = Order::visibleTo($request->user())
            ->with(['items', 'user'])
            ->latest('id')
            ->paginate(15);

        return OrderResource::collection($orders);
    }

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

    public function show(Order $order)
    {
        Gate::authorize('view', $order);

        return new OrderResource($order->load(['items', 'user']));
    }

    public function cancel(Order $order, OrderService $orders)
    {
        Gate::authorize('cancel', $order);

        return new OrderResource($orders->cancel($order)->load('user'));
    }
}
