<?php

namespace App\Http\Controllers;

use Composer\InstalledVersions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LaravelNats\Exceptions\NatsNoRespondersException;
use LaravelNats\Exceptions\NatsRequestTimeoutException;
use LaravelNats\Laravel\Facades\NatsV2;

/**
 * Smoke tests for laravel-nats v1.5+ (request/reply, ping, multi-value HPUB headers).
 */
final class NatsV2FeaturesController extends Controller
{
    public function smoke(): JsonResponse
    {
        $packageVersion = InstalledVersions::getPrettyVersion('zaeem2396/laravel-nats');

        $ping = false;
        try {
            $ping = NatsV2::ping();
        } catch (\Throwable) {
        }

        $multiHeaderPublishOk = false;
        try {
            NatsV2::publish('poc.v2.multiheader.demo', ['smoke' => true], [
                'X-PoC-Trace' => ['span-a', 'span-b'],
            ]);
            $multiHeaderPublishOk = true;
        } catch (\Throwable) {
        }

        return response()->json([
            'laravel_nats' => $packageVersion,
            'nats_v2_ping' => $ping,
            'multi_header_publish_ok' => $multiHeaderPublishOk,
        ]);
    }

    public function rpcPreferences(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|min:1',
        ]);

        try {
            $payload = NatsV2::request('user.rpc.preferences', $validated, 5.0);
            $body = $payload->body;
            $decoded = json_decode($body, true);

            return response()->json([
                'ok' => true,
                'reply' => is_array($decoded) ? $decoded : ['raw' => $body],
            ]);
        } catch (NatsNoRespondersException $e) {
            return response()->json([
                'ok' => false,
                'error' => 'no_responders',
                'message' => $e->getMessage(),
            ], 503);
        } catch (NatsRequestTimeoutException $e) {
            return response()->json([
                'ok' => false,
                'error' => 'timeout',
                'message' => $e->getMessage(),
            ], 504);
        }
    }
}
