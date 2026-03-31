<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use LaravelNats\Laravel\Facades\NatsV2;
use Throwable;

class NatsOrdersProvisionStreamCommand extends Command
{
    protected $signature = 'nats:orders:provision-stream {--connection= : Basis NATS connection name}';

    protected $description = 'Create ORDERS JetStream stream (orders.*, payments.*, inventory.*) from nats_orders preset';

    public function handle(): int
    {
        $preset = (string) config('nats_orders.stream_preset_key', 'order_processing');
        $connection = $this->option('connection') ?: null;

        try {
            NatsV2::jetStreamProvisionPreset($preset, true, $connection);
            $this->info("JetStream stream provisioned via preset [{$preset}].");
            $this->line('Subjects: orders.*, payments.*, inventory.*');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
