<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Merges order pipeline JetStream preset into package {@see config('nats_basis.jetstream.presets')}.
 */
class OrderMessagingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/nats_orders.php',
            'nats_orders'
        );
    }

    public function boot(): void
    {
        $presets = config('nats_basis.jetstream.presets', []);
        if (! is_array($presets)) {
            $presets = [];
        }

        $key = (string) config('nats_orders.stream_preset_key', 'order_processing');
        $presets[$key] = [
            'name' => config('nats_orders.stream_name', 'ORDERS'),
            'subjects' => [
                'orders.*',
                'payments.*',
                'inventory.*',
            ],
            'storage' => 'file',
            'retention' => 'limits',
        ];

        config(['nats_basis.jetstream.presets' => $presets]);
    }
}
