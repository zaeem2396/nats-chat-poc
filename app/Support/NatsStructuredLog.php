<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

/**
 * Structured logging for NATS events (event, status, room_id, duration_ms).
 */
final class NatsStructuredLog
{
    public static function event(
        string $event,
        string $status,
        array $context = [],
    ): void {
        Log::info($event, array_merge([
            'event' => $event,
            'status' => $status,
        ], $context));
    }

    public static function error(
        string $event,
        string $status,
        \Throwable $exception,
        array $context = [],
    ): void {
        Log::error($event, array_merge([
            'event' => $event,
            'status' => $status,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ], $context));
    }

    public static function withDuration(string $event, string $status, float $durationMs, array $context = []): void
    {
        self::event($event, $status, array_merge($context, ['duration_ms' => round($durationMs, 2)]));
    }
}
