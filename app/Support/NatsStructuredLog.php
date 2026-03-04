<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

/**
 * Structured logging for NATS: standard shape
 * { "event", "subject", "status": "success|failed|retry", "attempt", "duration_ms", "error" }.
 */
final class NatsStructuredLog
{
    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const STATUS_RETRY = 'retry';

    /**
     * Log with standard production shape.
     */
    public static function messageProcessed(
        string $event,
        string $subject,
        string $status,
        int $attempt,
        float $durationMs,
        ?string $error = null,
    ): void {
        $payload = [
            'event' => $event,
            'subject' => $subject,
            'status' => $status,
            'attempt' => $attempt,
            'duration_ms' => round($durationMs, 2),
            'error' => $error,
        ];
        if ($status === self::STATUS_FAILED || $error !== null) {
            Log::error($event, $payload);
        } else {
            Log::info($event, $payload);
        }
    }

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
