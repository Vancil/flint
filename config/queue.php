<?php

return [
    'driver'      => env('QUEUE_DRIVER', 'database'),
    'retry_after' => (int) env('QUEUE_RETRY_AFTER', 90),
    'redis' => [
        'host'     => env('REDIS_HOST', '127.0.0.1'),
        'port'     => (int) env('REDIS_PORT', 6379),
        'password' => env('REDIS_PASSWORD', null),
        'database' => (int) env('REDIS_DB', 0),
    ],
];
