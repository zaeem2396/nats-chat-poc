<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * In-memory/cache metrics for NATS processing (messages_processed, messages_failed, retries, avg_processing_time).
 */
class MetricsService
{
    private const PREFIX = 'nats_metrics.';

    private const TTL_SECONDS = 86400; // 24h

    public function incrementProcessed(): void
    {
        Cache::add(self::PREFIX . 'messages_processed', 0, self::TTL_SECONDS);
        Cache::increment(self::PREFIX . 'messages_processed');
    }

    public function incrementFailed(): void
    {
        Cache::add(self::PREFIX . 'messages_failed', 0, self::TTL_SECONDS);
        Cache::increment(self::PREFIX . 'messages_failed');
    }

    public function incrementRetries(): void
    {
        Cache::add(self::PREFIX . 'retries_count', 0, self::TTL_SECONDS);
        Cache::increment(self::PREFIX . 'retries_count');
    }

    public function recordProcessingTime(float $durationMs): void
    {
        $sumKey = self::PREFIX . 'processing_sum';
        $countKey = self::PREFIX . 'processing_count';
        Cache::add($sumKey, 0, self::TTL_SECONDS);
        Cache::add($countKey, 0, self::TTL_SECONDS);
        Cache::put($sumKey, Cache::get($sumKey, 0) + $durationMs, self::TTL_SECONDS);
        Cache::increment($countKey);
    }

    public function get(): array
    {
        $sum = (float) Cache::get(self::PREFIX . 'processing_sum', 0);
        $count = (int) Cache::get(self::PREFIX . 'processing_count', 0);
        return [
            'messages_processed' => (int) Cache::get(self::PREFIX . 'messages_processed', 0),
            'messages_failed' => (int) Cache::get(self::PREFIX . 'messages_failed', 0),
            'retries_count' => (int) Cache::get(self::PREFIX . 'retries_count', 0),
            'avg_processing_time_ms' => $count > 0 ? round($sum / $count, 2) : 0,
        ];
    }
}
