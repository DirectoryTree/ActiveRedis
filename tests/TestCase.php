<?php

namespace DirectoryTree\ActiveRedis\Tests;

use DirectoryTree\ActiveRedis\ActiveRedisServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [ActiveRedisServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.redis.options.prefix', null);
    }
}
