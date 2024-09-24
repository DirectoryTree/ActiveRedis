<?php

use DirectoryTree\ActiveRedis\Model;
use DirectoryTree\ActiveRedis\Query;
use DirectoryTree\ActiveRedis\Repositories\RedisRepository;
use DirectoryTree\ActiveRedis\Tests\TestCase;
use Illuminate\Redis\RedisManager;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

uses(TestCase::class)->in(__DIR__);

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function query(Model $model): Query
{
    return new Query($model, repository());
}

function repository(): RedisRepository
{
    return new RedisRepository(
        app(RedisManager::class)->connection()
    );
}
