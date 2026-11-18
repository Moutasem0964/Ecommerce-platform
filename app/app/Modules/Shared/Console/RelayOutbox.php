<?php

namespace App\Modules\Shared\Console;

use App\Modules\Shared\Messaging\OutboxRelay;
use Illuminate\Console\Command;
use Throwable;

class RelayOutbox extends Command
{
    protected $signature = 'messaging:relay-outbox';

    protected $description = 'Publish outbox messages to RabbitMQ (written in the same transaction as the business change)';

    private bool $running = true;

    public function handle(OutboxRelay $relay): int
    {
        $this->trap([SIGTERM, SIGINT], fn () => $this->running = false);
        $this->info('Relaying the outbox to RabbitMQ every '.config('messaging.outbox_poll_ms').' ms');

        $lastError = null;
        $lastPurge = 0;

        while ($this->running) {
            $published = 0;

            try {
                $result = $relay->relay();
                $published = $result->published;
                $error = $result->error;
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }

            if ($published > 0) {
                $this->line(now()->toDateTimeString()."  published {$published} message(s)");
            }

            if ($error !== $lastError) {
                $this->line(now()->toDateTimeString().'  '.($error === null ? 'publishing normally' : "cannot publish yet: {$error}"));
                $lastError = $error;
            }

            if (time() - $lastPurge >= 60) {
                $relay->purgePublishedBefore(now()->subDay());
                $lastPurge = time();
            }

            if ($published === 0) {
                usleep(config('messaging.outbox_poll_ms') * 1000);
            }
        }

        return self::SUCCESS;
    }
}
