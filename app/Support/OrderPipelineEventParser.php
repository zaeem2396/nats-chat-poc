<?php

namespace App\Support;

use Basis\Nats\Message\Msg;

/**
 * Parses JetStream message bodies as versioned envelopes:
 * { id, type, version, data, idempotency_key? }.
 */
final class OrderPipelineEventParser
{
    /**
     * @return array{id: string, type: string, version: string, data: array<string, mixed>, idempotency_key?: string}|null
     */
    public static function parse(Msg $msg): ?array
    {
        $body = (string) $msg->payload->body;
        $decoded = json_decode($body, true);
        if (! is_array($decoded)) {
            return null;
        }
        if (! isset($decoded['id'], $decoded['type'], $decoded['version'], $decoded['data']) || ! is_string($decoded['id']) || ! is_array($decoded['data'])) {
            return null;
        }

        return $decoded;
    }
}
