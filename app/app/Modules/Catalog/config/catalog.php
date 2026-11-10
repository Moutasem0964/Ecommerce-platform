<?php

return [
    'cache' => [
        'enabled' => (bool) env('CATALOG_CACHE_ENABLED', true),
        'ttl_seconds' => (int) env('CATALOG_CACHE_TTL', 600),
    ],
];
