<?php

return [
    'target' => env('INVENTORY_GRPC_TARGET', 'inventory:9090'),
    'timeout_ms' => (int) env('INVENTORY_GRPC_TIMEOUT_MS', 2000),
];
