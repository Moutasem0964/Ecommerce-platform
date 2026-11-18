<?php

namespace App\Modules\Shared\Messaging;

use Illuminate\Support\Facades\DB;

class Outbox
{
    public function record(string $exchange, string $routingKey, array $payload, string $messageId): void
    {
        DB::table('outbox_messages')->insertOrIgnore([
            'message_id' => $messageId,
            'exchange' => $exchange,
            'routing_key' => $routingKey,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
        ]);
    }
}
