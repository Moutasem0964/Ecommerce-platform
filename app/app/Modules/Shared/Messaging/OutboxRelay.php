<?php

namespace App\Modules\Shared\Messaging;

use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Throwable;

class OutboxRelay
{
    public function __construct(private readonly RabbitMq $rabbit)
    {
    }

    public function relay(int $limit = 50): RelayResult
    {
        return DB::transaction(function () use ($limit) {
            $pending = DB::table('outbox_messages')
                ->whereNull('published_at')
                ->orderBy('id')
                ->limit($limit)
                ->lock('for update skip locked')
                ->get();

            $published = 0;

            foreach ($pending as $row) {
                try {
                    $this->rabbit->publish(
                        $row->exchange,
                        $row->routing_key,
                        json_decode($row->payload, true, 512, JSON_THROW_ON_ERROR),
                        $row->message_id,
                    );
                } catch (Throwable $e) {
                    DB::table('outbox_messages')->where('id', $row->id)->update([
                        'attempts' => $row->attempts + 1,
                        'last_error' => mb_substr($e->getMessage(), 0, 1000),
                    ]);
                    $this->rabbit->reset();

                    return new RelayResult($published, $pending->count() - $published, $e->getMessage());
                }

                DB::table('outbox_messages')->where('id', $row->id)->update([
                    'attempts' => $row->attempts + 1,
                    'last_error' => null,
                    'published_at' => now(),
                ]);
                $published++;
            }

            return new RelayResult($published, 0, null);
        });
    }

    public function purgePublishedBefore(DateTimeInterface $before): int
    {
        return DB::table('outbox_messages')
            ->whereNotNull('published_at')
            ->where('published_at', '<', $before)
            ->delete();
    }
}
