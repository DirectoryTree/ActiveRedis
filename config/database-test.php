<?php

// Test configuration for Redis cluster
return [
    'redis' => [
        'default' => [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', 6379),
            'database' => 0,
        ],

        'cluster' => [
            'client' => 'predis',
            'cluster' => 'redis',
            'default' => [
                'host' => '127.0.0.1',
                'port' => 7001,
                'database' => 0,
            ],
            'clusters' => [
                ['host' => '127.0.0.1', 'port' => 7001],
                ['host' => '127.0.0.1', 'port' => 7002],
                ['host' => '127.0.0.1', 'port' => 7003],
            ],
            'options' => [
                'cluster' => 'redis',
            ],
        ],
    ],
];