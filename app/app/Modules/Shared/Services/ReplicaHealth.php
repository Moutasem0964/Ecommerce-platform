<?php

namespace App\Modules\Shared\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PDO;
use Throwable;

class ReplicaHealth
{
    public const HEALTHY = 'healthy';
    public const LAGGING = 'lagging';
    public const DOWN = 'down';
    public const UNKNOWN = 'unknown';
    public const DISABLED = 'disabled';

    private const STATUS_KEY = 'db:replica:status';
    private const PROBE_CONNECTION = 'replica_probe';

    private ?array $status = null;

    public function status(): array
    {
        if (! $this->replicaConfigured()) {
            return ['state' => self::DISABLED, 'lag' => null];
        }

        return $this->status ??= Cache::get(self::STATUS_KEY) ?? ['state' => self::UNKNOWN, 'lag' => null];
    }

    public function shouldReadFromPrimary(): bool
    {
        $state = $this->status()['state'];

        return $state !== self::HEALTHY && $state !== self::DISABLED;
    }

    public function describe(): string
    {
        ['state' => $state, 'lag' => $lag] = $this->status();

        return $lag === null ? $state : sprintf('%s (%.1fs)', $state, $lag);
    }

    public function replicaConfigured(): bool
    {
        return config('database.connections.mysql.read.host.0') !== config('database.connections.mysql.write.host.0');
    }

    public function check(): array
    {
        try {
            DB::connection('mysql')->table('replication_heartbeat')->upsert(
                [['id' => 1, 'beat_us' => $this->nowMicros()]],
                ['id'],
                ['beat_us'],
            );
        } catch (Throwable) {
            return $this->record(self::UNKNOWN, null);
        }

        try {
            $beat = DB::connection($this->probeConnection())->table('replication_heartbeat')->where('id', 1)->value('beat_us');
        } catch (Throwable) {
            DB::purge(self::PROBE_CONNECTION);

            return $this->record(self::DOWN, null);
        }

        if ($beat === null) {
            return $this->record(self::LAGGING, null);
        }

        $lag = max(0, ($this->nowMicros() - (int) $beat) / 1_000_000);

        return $this->record($lag <= config('replica.max_lag_seconds') ? self::HEALTHY : self::LAGGING, $lag);
    }

    private function record(string $state, ?float $lag): array
    {
        $status = ['state' => $state, 'lag' => $lag === null ? null : round($lag, 1)];
        Cache::put(self::STATUS_KEY, $status, config('replica.status_ttl_seconds'));

        return $this->status = $status;
    }

    private function probeConnection(): string
    {
        if (config('database.connections.'.self::PROBE_CONNECTION) === null) {
            $config = config('database.connections.mysql');
            unset($config['read'], $config['write'], $config['sticky']);
            $config['host'] = config('database.connections.mysql.read.host.0');
            $config['options'] = [PDO::ATTR_TIMEOUT => 1];
            config(['database.connections.'.self::PROBE_CONNECTION => $config]);
        }

        return self::PROBE_CONNECTION;
    }

    private function nowMicros(): int
    {
        return (int) round(microtime(true) * 1_000_000);
    }
}
