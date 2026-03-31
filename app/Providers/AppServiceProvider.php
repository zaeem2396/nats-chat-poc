<?php

namespace App\Providers;

use App\Contracts\OrderStreamProvisioner;
use App\Services\Nats\OrderStreamConsumerProvisioner;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(OrderStreamProvisioner::class, OrderStreamConsumerProvisioner::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Ensure the NATS queue connector is registered when using the nats driver.
        // NatsServiceProvider is deferred, so it only boots when 'nats' (or NatsManager) is resolved.
        if (config('queue.default') === 'nats') {
            $this->app->make('nats');
        }
    }
}
