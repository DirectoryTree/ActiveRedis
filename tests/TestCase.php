<?php

namespace DirectoryTree\ActiveRedis\Tests;

use DirectoryTree\ActiveRedis\ActiveRedisServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Get package providers.
     */
    protected function getPackageProviders($app): array
    {
        return [ActiveRedisServiceProvider::class];
    }

    /**
     * Define environment setup.
     */
    protected function defineEnvironment($app)
    {
        // Configure Redis connections for testing
        $app['config']->set('database.redis.default', [
            'client' => 'phpredis',
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', 6379),
            'database' => 0,
        ]);
    }
}
