<?php

return [
    'driver' => env('SIGNATURE_DRIVER', 'fake'),

    'ttl_days' => (int) env('SIGNATURE_REQUEST_TTL_DAYS', 7),

    'sync' => [
        'interval_minutes' => 60,
        'stale_minutes' => 30,
        'limit' => 20,
    ],

    'yousign' => [
        'api_key' => env('YOUSIGN_API_KEY'),
        'base_url' => env('YOUSIGN_BASE_URL', 'https://api-sandbox.yousign.app/v3'),
        'webhook_secret' => env('YOUSIGN_WEBHOOK_SECRET'),
        'signature_level' => env('YOUSIGN_SIGNATURE_LEVEL', 'electronic_signature'),
        'timeout' => 30,
    ],
];
