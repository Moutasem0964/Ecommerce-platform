<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Broadcaster
    |--------------------------------------------------------------------------
    */
    'default' => env('BROADCAST_CONNECTION', 'reverb'),

    /*
    |--------------------------------------------------------------------------
    | Broadcast Connections
    |--------------------------------------------------------------------------
    */
    'connections' => [
        'reverb' => [
            'driver' => 'reverb',
            'key' => env('REVERB_APP_KEY'),
            'secret' => env('REVERB_APP_SECRET'),
            'app_id' => env('REVERB_APP_ID'),
            'options' => [
                'host' => env('REVERB_HOST', '127.0.0.1'),
                'port' => env('REVERB_PORT', 8080),
                'scheme' => env('REVERB_SCHEME', 'http'),
                'useTLS' => env('REVERB_SCHEME', 'http') === 'https',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guard for Broadcasting
    |--------------------------------------------------------------------------
    | هنا نخبر Laravel باستخدام guard المسمى 'jwt' للتحقق من هوية المستخدم
    | عند محاولة الاشتراك في القنوات الخاصة (Private Channels)
    */
    'guard' => 'api',
];
