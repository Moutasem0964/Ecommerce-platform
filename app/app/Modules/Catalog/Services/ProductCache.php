<?php

namespace App\Modules\Catalog\Services;

use Closure;
use Illuminate\Support\Facades\Cache;

class ProductCache
{
    private const LIST_VERSION_KEY = 'catalog:list-version';

    private string $lastStatus = 'BYPASS';

    public function product(int $id, Closure $load): ?array
    {
        if (! $this->enabled()) {
            $this->lastStatus = 'BYPASS';

            return $load();
        }

        $key = $this->productKey($id);
        $cached = Cache::get($key);
        if ($cached !== null) {
            $this->lastStatus = 'HIT';

            return $cached;
        }

        $this->lastStatus = 'MISS';
        $attributes = $load();
        if ($attributes !== null) {
            Cache::put($key, $attributes, $this->ttl());
        }

        return $attributes;
    }

    public function page(?string $category, int $perPage, int $page, Closure $load): array
    {
        if (! $this->enabled()) {
            $this->lastStatus = 'BYPASS';

            return $load();
        }

        $key = sprintf(
            'catalog:list:v%d:%s:%d:%d',
            $this->listVersion(),
            $category === null ? 'all' : md5($category),
            $perPage,
            $page,
        );

        $cached = Cache::get($key);
        if ($cached !== null) {
            $this->lastStatus = 'HIT';

            return $cached;
        }

        $this->lastStatus = 'MISS';
        $page = $load();
        Cache::put($key, $page, $this->ttl());

        return $page;
    }

    public function lastStatus(): string
    {
        return $this->lastStatus;
    }

    public function forgetProduct(int $id): void
    {
        Cache::forget($this->productKey($id));
    }

    public function invalidateLists(): void
    {
        Cache::add(self::LIST_VERSION_KEY, 1);
        Cache::increment(self::LIST_VERSION_KEY);
    }

    private function listVersion(): int
    {
        return (int) Cache::rememberForever(self::LIST_VERSION_KEY, fn () => 1);
    }

    private function productKey(int $id): string
    {
        return "catalog:product:{$id}";
    }

    public function enabled(): bool
    {
        return (bool) config('catalog.cache.enabled');
    }

    private function ttl(): int
    {
        return (int) config('catalog.cache.ttl_seconds');
    }
}
