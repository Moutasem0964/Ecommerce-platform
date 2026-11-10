<?php

namespace App\Modules\Shared\Console;

use App\Modules\Shared\Services\CacheRecovery;
use App\Modules\Shared\Services\ReplicaHealth;
use Illuminate\Console\Command;

class MonitorHealth extends Command
{
    protected $signature = 'health:monitor';

    protected $description = 'Write a heartbeat to the primary every second, mark the read replica healthy, lagging or down, and flush the cache after Redis comes back';

    private bool $running = true;

    public function handle(ReplicaHealth $health, CacheRecovery $cache): int
    {
        $this->trap([SIGTERM, SIGINT], fn () => $this->running = false);
        $this->info('Monitoring the read replica (max lag '.config('replica.max_lag_seconds').'s) and Redis');

        $lastReplica = null;
        $lastCache = null;
        while ($this->running) {
            $cacheState = $cache->check();
            if ($cacheState !== $lastCache) {
                $this->line(now()->toDateTimeString().'  redis is '.match ($cacheState) {
                    CacheRecovery::RECOVERED => 'back, cache flushed',
                    CacheRecovery::FLUSHED_AGAIN => 'up, cache flushed again after the breaker window',
                    default => $cacheState,
                });
                $lastCache = $cacheState === CacheRecovery::UP || $cacheState === CacheRecovery::DOWN ? $cacheState : CacheRecovery::UP;
            }

            if ($health->replicaConfigured()) {
                $health->check();
                $state = $health->status()['state'];
                if ($state !== $lastReplica) {
                    $this->line(now()->toDateTimeString().'  replica is '.$health->describe());
                    $lastReplica = $state;
                }
            }

            usleep(config('replica.check_interval_ms') * 1000);
        }

        return self::SUCCESS;
    }
}
