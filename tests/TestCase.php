<?php

namespace DirectoryTree\ActiveRedis\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.redis.options.prefix', null);
    }
}
