<?php

/**
 * Example Redis Cluster Configuration for ActiveRedis
 * 
 * Add this to your config/database.php file under the 'redis' array.
 */

return [
    'redis' => [
        // Local Redis Cluster configuration (for development)
        'activeredis_cluster_local' => [
            'client' => 'predis',
            'cluster' => 'redis',
            'default' => [
                'host' => env('REDIS_CLUSTER_HOST', '127.0.0.1'),
                'port' => env('REDIS_CLUSTER_PORT', 7001),
                'database' => 0,
                'password' => env('REDIS_PASSWORD', null),
            ],
            'clusters' => [
                [
                    'host' => env('REDIS_CLUSTER_HOST', '127.0.0.1'),
                    'port' => env('REDIS_CLUSTER_PORT_1', 7001),
                ],
                [
                    'host' => env('REDIS_CLUSTER_HOST', '127.0.0.1'),
                    'port' => env('REDIS_CLUSTER_PORT_2', 7002),
                ],
                [
                    'host' => env('REDIS_CLUSTER_HOST', '127.0.0.1'),
                    'port' => env('REDIS_CLUSTER_PORT_3', 7003),
                ],
            ],
            'options' => [
                'cluster' => 'redis',
            ],
        ],

        // AWS ElastiCache Redis Cluster configuration
        'activeredis_cluster_aws' => [
            'client' => 'predis',
            'cluster' => 'redis',
            'default' => [
                'host' => env('REDIS_CLUSTER_ENDPOINT', 'your-cluster.cache.amazonaws.com'),
                'port' => env('REDIS_CLUSTER_PORT', 6379),
                'database' => 0,
                'password' => env('REDIS_AUTH_TOKEN', null), // Required for Auth Token enabled clusters
            ],
            'options' => [
                'cluster' => 'redis',
                'ssl' => env('REDIS_SSL', false), // Enable for in-transit encryption
                'verify_peer' => false, // Disable SSL peer verification for AWS
            ],
        ],

        // Production-ready cluster configuration with multiple endpoints
        'activeredis_cluster_production' => [
            'client' => 'predis',
            'cluster' => 'redis',
            'clusters' => [
                [
                    'host' => env('REDIS_CLUSTER_NODE_1', 'node1.cluster.cache.amazonaws.com'),
                    'port' => env('REDIS_CLUSTER_PORT', 6379),
                ],
                [
                    'host' => env('REDIS_CLUSTER_NODE_2', 'node2.cluster.cache.amazonaws.com'),
                    'port' => env('REDIS_CLUSTER_PORT', 6379),
                ],
                [
                    'host' => env('REDIS_CLUSTER_NODE_3', 'node3.cluster.cache.amazonaws.com'),
                    'port' => env('REDIS_CLUSTER_PORT', 6379),
                ],
            ],
            'options' => [
                'cluster' => 'redis',
                'ssl' => [
                    'verify_peer' => false,
                ],
                'timeout' => 5.0,
                'read_write_timeout' => 5.0,
                'retry_interval' => 100,
                'password' => env('REDIS_AUTH_TOKEN', null),
            ],
        ],

        // PhpRedis cluster configuration (alternative client)
        'activeredis_cluster_phpredis' => [
            'client' => 'phpredis',
            'cluster' => 'redis',
            'clusters' => [
                [
                    'host' => env('REDIS_CLUSTER_HOST', '127.0.0.1'),
                    'port' => env('REDIS_CLUSTER_PORT_1', 7001),
                ],
                [
                    'host' => env('REDIS_CLUSTER_HOST', '127.0.0.1'),
                    'port' => env('REDIS_CLUSTER_PORT_2', 7002),
                ],
                [
                    'host' => env('REDIS_CLUSTER_HOST', '127.0.0.1'),
                    'port' => env('REDIS_CLUSTER_PORT_3', 7003),
                ],
            ],
            'options' => [
                'cluster' => [
                    'failover' => 'error', // 'error', 'distribute', 'distribute_slaves'
                    'timeout' => 5,
                    'read_timeout' => 5,
                ],
            ],
        ],
    ],
];

/**
 * Example .env variables:
 * 
 * # Local Development Cluster
 * REDIS_CLUSTER_HOST=127.0.0.1
 * REDIS_CLUSTER_PORT=7001
 * REDIS_CLUSTER_PORT_1=7001
 * REDIS_CLUSTER_PORT_2=7002
 * REDIS_CLUSTER_PORT_3=7003
 * 
 * # AWS ElastiCache Cluster
 * REDIS_CLUSTER_ENDPOINT=your-cluster.xxxxx.cache.amazonaws.com
 * REDIS_CLUSTER_PORT=6379
 * REDIS_AUTH_TOKEN=your-auth-token
 * REDIS_SSL=true
 * 
 * # Production Cluster Nodes
 * REDIS_CLUSTER_NODE_1=node1.cluster.cache.amazonaws.com
 * REDIS_CLUSTER_NODE_2=node2.cluster.cache.amazonaws.com
 * REDIS_CLUSTER_NODE_3=node3.cluster.cache.amazonaws.com
 */