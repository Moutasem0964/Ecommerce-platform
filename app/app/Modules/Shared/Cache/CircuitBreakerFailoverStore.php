<?php

namespace App\Modules\Shared\Cache;

use Illuminate\Cache\CacheManager;
use Illuminate\Cache\Events\CacheFailedOver;
use Illuminate\Cache\FailoverStore;
use Illuminate\Contracts\Events\Dispatcher;
use RuntimeException;
use Throwable;

class CircuitBreakerFailoverStore extends FailoverStore
{
    public function __construct(
        CacheManager $cache,
        Dispatcher $events,
        array $stores,
        private readonly string $flagPath,
        private readonly int $openSeconds,
    ) {
        parent::__construct($cache, $events, $stores);
    }

    protected function attemptOnAllStores(string $method, array $arguments)
    {
        $primary = $this->stores[0];
        $stores = $this->isOpen() ? array_slice($this->stores, 1) : $this->stores;
        $lastException = null;

        foreach ($stores as $store) {
            try {
                return $this->store($store)->{$method}(...$arguments);
            } catch (Throwable $e) {
                $lastException = $e;

                if ($store === $primary) {
                    $this->open();
                    $this->events->dispatch(new CacheFailedOver($store, $e));
                }
            }
        }

        throw $lastException ?? new RuntimeException('All failover cache stores failed.');
    }

    public function isOpen(): bool
    {
        clearstatcache(true, $this->flagPath);
        $openedAt = @filemtime($this->flagPath);

        return $openedAt !== false && time() - $openedAt < $this->openSeconds;
    }

    private function open(): void
    {
        @touch($this->flagPath);
    }
}
