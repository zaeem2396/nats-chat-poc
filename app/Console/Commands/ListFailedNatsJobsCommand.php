<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ListFailedNatsJobsCommand extends Command
{
    protected $signature = 'nats-chat:failed-jobs
                            {--connection=nats : Filter by queue connection}
                            {--limit=50 : Max rows}';

    protected $description = 'List failed NATS queue jobs from failed_jobs table (filter by --connection=nats)';

    public function handle(): int
    {
        $connection = $this->option('connection');
        $limit = (int) $this->option('limit');

        $rows = DB::table('failed_jobs')
            ->where('connection', $connection)
            ->orderByDesc('failed_at')
            ->limit($limit)
            ->get(['id', 'uuid', 'queue', 'failed_at']);

        if ($rows->isEmpty()) {
            $this->info("No failed jobs for connection: {$connection}");

            return self::SUCCESS;
        }

        $this->table(['ID', 'UUID', 'Queue', 'Failed At'], $rows->map(fn ($r) => [$r->id, $r->uuid, $r->queue, $r->failed_at]));

        return self::SUCCESS;
    }
}
