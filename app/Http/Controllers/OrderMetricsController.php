<?php

namespace App\Http\Controllers;

use App\Models\FailedMessage;
use App\Models\Order;
use App\Models\OrderNotification;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;

class OrderMetricsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'orders_total' => Order::query()->count(),
            'orders_by_status' => Order::query()
                ->selectRaw('status, count(*) as c')
                ->groupBy('status')
                ->pluck('c', 'status'),
            'payments_total' => Payment::query()->count(),
            'payments_by_status' => Payment::query()
                ->selectRaw('status, count(*) as c')
                ->groupBy('status')
                ->pluck('c', 'status'),
            'notifications_total' => OrderNotification::query()->count(),
            'failed_messages_total' => FailedMessage::query()->count(),
        ]);
    }
}
