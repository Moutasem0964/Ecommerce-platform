<?php

return [
    'private_key_path' => env('JWT_PRIVATE_KEY_PATH', storage_path('keys/jwt-private.pem')),
    'public_key_path' => env('JWT_PUBLIC_KEY_PATH', storage_path('keys/jwt-public.pem')),

    'ttl_minutes' => (int) env('JWT_TTL_MINUTES', 60),

    'issuer' => env('JWT_ISSUER', 'ecommerce-app'),
];
