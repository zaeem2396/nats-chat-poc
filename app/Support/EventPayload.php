<?php

namespace App\Support;

/**
 * Normalize versioned event payloads: { version, type, data } -> use data; else use payload as-is.
 */
final class EventPayload
{
    public static function unwrap(array $payload): array
    {
        if (isset($payload['version'], $payload['type'], $payload['data']) && is_array($payload['data'])) {
            return $payload['data'];
        }
        return $payload;
    }
}
