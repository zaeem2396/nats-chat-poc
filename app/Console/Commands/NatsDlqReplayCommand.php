<?php

namespace App\Console\Commands;

use App\Logging\PipelineLog;
use App\Models\FailedMessage;
use Illuminate\Console\Command;
use LaravelNats\Laravel\Facades\NatsV2;

/**
 * Republish stored failed JetStream envelopes to their original capture subject (replay).
 */
class NatsDlqReplayCommand extends Command
{
    protected $signature = 'nats:dlq:replay
                            {--id=* : Replay only failed_messages.id(s)}
                            {--limit=20 : Max rows to process}
                            {--dry-run : Show actions without publishing}';

    protected $description = 'Replay failed_messages rows back to JetStream (source_subject + raw envelope)';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $dry = (bool) $this->option('dry-run');
        $ids = array_filter(array_map('intval', (array) $this->option('id')));

        $q = FailedMessage::query()->orderBy('id');
        if ($ids !== []) {
            $q->whereIn('id', $ids);
        }
        $rows = $q->limit($limit)->get();

        if ($rows->isEmpty()) {
            $this->info('No failed_messages rows to replay.');

            return self::SUCCESS;
        }

        $stream = (string) config('nats_orders.stream_name', 'ORDERS');
        $replayCount = 0;

        foreach ($rows as $row) {
            $subject = $row->source_subject;
            if ($subject === null || $subject === '') {
                $this->warn("Skip id={$row->id}: missing source_subject (cannot replay).");

                continue;
            }

            $payload = $row->payload;
            if (! is_array($payload) || ! isset($payload['id'], $payload['data'])) {
                $this->warn("Skip id={$row->id}: payload is not a full envelope.");

                continue;
            }

            if ($dry) {
                $this->line("[dry-run] Would publish to {$subject} stream={$stream} failed_message_id={$row->id}");
                $replayCount++;

                continue;
            }

            try {
                NatsV2::jetStreamPublish(
                    $stream,
                    $subject,
                    $payload,
                    useEnvelope: false,
                    waitForAck: true,
                );
                PipelineLog::info('DlqReplay', 'Replayed message to JetStream', [
                    'failed_message_id' => $row->id,
                    'subject' => $subject,
                    'message_id' => $payload['id'] ?? null,
                ]);
                $this->info("Replayed id={$row->id} → {$subject}");
                $replayCount++;
            } catch (\Throwable $e) {
                $this->error("Failed id={$row->id}: {$e->getMessage()}");

                return self::FAILURE;
            }
        }

        $this->info("Done. Replayed {$replayCount} message(s).");

        return self::SUCCESS;
    }
}
