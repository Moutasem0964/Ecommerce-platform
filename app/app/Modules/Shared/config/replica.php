<?php

return [
    'max_lag_seconds' => (float) env('REPLICA_MAX_LAG_SECONDS', 2),
    'status_ttl_seconds' => (int) env('REPLICA_STATUS_TTL_SECONDS', 10),
    'check_interval_ms' => (int) env('REPLICA_CHECK_INTERVAL_MS', 1000),
];
