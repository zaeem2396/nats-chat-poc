<?php

namespace App\Logging;

use Illuminate\Support\Facades\Log;

/**
 * Structured, grep-friendly logs for the order pipeline (component prefix in the message text).
 */
final class PipelineLog
{
    public static function info(string $component, string $message, array $context = []): void
    {
        Log::info("[{$component}] {$message}", $context);
    }

    public static function warning(string $component, string $message, array $context = []): void
    {
        Log::warning("[{$component}] {$message}", $context);
    }

    public static function error(string $component, string $message, array $context = []): void
    {
        Log::error("[{$component}] {$message}", $context);
    }
}
