<?php

namespace App\Modules\Shared\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Throwable;

class CacheRecovery
{
    public const UP = 'up';
    public const DOWN = 'down';
    public const RECOVERED = 'recovered';
    public const FLUSHED_AGAIN = 'flushed again';

    private ?bool $wasUp = null;

    private ?int $flushAgainAt = null;

    public function check(): string
    {
        $isUp = $this->ping();
        $recovered = $isUp && $this->wasUp !== true;
        $this->wasUp = $isUp;

        if (! $isUp) {
            $this->flushAgainAt = null;

            return self::DOWN;
        }

        if ($recovered) {
            Cache::store('redis')->flush();
            $this->flushAgainAt = time() + config('cache_breaker.open_seconds') + 1;

            return self::RECOVERED;
        }

        if ($this->flushAgainAt !== null && time() >= $this->flushAgainAt) {
            Cache::store('redis')->flush();
            $this->flushAgainAt = null;

            return self::FLUSHED_AGAIN;
        }

        return self::UP;
    }

    private function ping(): bool
    {
        try {
            Redis::connection('cache')->ping();

            return true;
        } catch (Throwable) {
            Redis::purge('cache');

            return false;
        }
    }
}
