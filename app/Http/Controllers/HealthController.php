<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use LaravelNats\Laravel\Facades\NatsV2;

/**
 * Readiness probe: app boot + optional v2 NATS ping (package observability / ops).
 */
final class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $natsV2Reachable = false;
        try {
            $natsV2Reachable = NatsV2::ping();
        } catch (\Throwable) {
            $natsV2Reachable = false;
        }

        return response()->json([
            'status' => 'ok',
            'nats_v2_reachable' => $natsV2Reachable,
        ]);
    }
}
