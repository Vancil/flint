<?php

return [
    'driver' => env('CACHE_DRIVER', 'file'),
    'ttl'    => (int) env('CACHE_TTL', 3600),
    'prefix' => env('CACHE_PREFIX', 'flint_'),
    'redis'  => [
        'host'     => env('REDIS_HOST', '127.0.0.1'),
        'port'     => (int) env('REDIS_PORT', 6379),
        'password' => env('REDIS_PASSWORD', null),
        'database' => (int) env('REDIS_CACHE_DB', 1),
    ],
];
