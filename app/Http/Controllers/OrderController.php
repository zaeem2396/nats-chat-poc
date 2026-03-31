<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(): JsonResponse
    {
        $orders = Order::query()->orderByDesc('id')->limit(100)->get();

        return response()->json($orders);
    }

    public function store(Request $request, OrderService $orderService): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|min:1',
            'sku' => 'required|string|max:64',
            'quantity' => 'required|integer|min:1',
            'total_cents' => 'required|integer|min:1',
        ]);

        $order = $orderService->placeOrder(
            (int) $validated['user_id'],
            $validated['sku'],
            (int) $validated['quantity'],
            (int) $validated['total_cents'],
        );

        return response()->json($order, 201);
    }

    public function show(Order $order): JsonResponse
    {
        return response()->json($order->load('payments'));
    }
}
