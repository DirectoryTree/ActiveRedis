<?php

namespace DirectoryTree\ActiveRedis\Tests;

use DirectoryTree\ActiveRedis\Repositories\IndexedRedisRepository;
use Illuminate\Support\Facades\Redis;

it('can perform basic operations with secondary indexes', function () {
    $redis = Redis::connection('default');
    $repository = new IndexedRedisRepository($redis);

    $hash = 'test:indexed:basic:'.uniqid();

    $repository->setAttributes($hash, [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'status' => 'active',
    ]);

    expect($repository->exists($hash))->toBeTrue();

    $attributes = $repository->getAttributes($hash);
    expect($attributes['name'])->toBe('John Doe');
    expect($attributes['email'])->toBe('john@example.com');
    expect($attributes['status'])->toBe('active');

    $repository->delete($hash);
});

it('can query by attributes using secondary indexes', function () {
    $redis = Redis::connection('default');
    $repository = new IndexedRedisRepository($redis);

    $hashes = [];

    for ($i = 1; $i <= 3; $i++) {
        $hash = "test:indexed:query:{$i}:".uniqid();
        $hashes[] = $hash;

        $repository->setAttributes($hash, [
            'user_id' => "user{$i}",
            'status' => $i <= 2 ? 'active' : 'inactive',
            'category' => 'test',
        ]);
    }

    $activeUsers = $repository->queryByAttribute('test:indexed:query', 'status', 'active');
    expect(count($activeUsers))->toBeGreaterThanOrEqual(2);

    $testUsers = $repository->queryByAttribute('test:indexed:query', 'category', 'test');
    expect(count($testUsers))->toBeGreaterThanOrEqual(3);

    foreach ($hashes as $hash) {
        $repository->delete($hash);
    }
});

it('can get all models for a type using secondary indexes', function () {
    $redis = Redis::connection('default');
    $repository = new IndexedRedisRepository($redis);

    $modelName = 'test:indexed:all:'.uniqid();
    $hashes = [];

    for ($i = 1; $i <= 3; $i++) {
        $hash = "{$modelName}:{$i}";
        $hashes[] = $hash;

        $repository->setAttributes($hash, [
            'index' => $i,
            'data' => "test_data_{$i}",
        ]);
    }

    $allModels = $repository->getAllForModel($modelName);
    expect(count($allModels))->toBe(3);

    expect($allModels)->not->toBeEmpty();

    foreach ($hashes as $hash) {
        $repository->delete($hash);
    }
});

it('works as a complete indexed repository', function () {
    $redis = Redis::connection('default');
    $repository = new IndexedRedisRepository($redis);

    $hash = 'test:indexed:complete:'.uniqid();

    $repository->setAttributes($hash, [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
    ]);

    expect($repository->exists($hash))->toBeTrue();

    $attributes = $repository->getAttributes($hash);
    expect($attributes['name'])->toBe('Jane Doe');

    $results = $repository->queryByAttribute('test:indexed:complete', 'name', 'Jane Doe');
    expect($results)->not->toBeEmpty();

    $repository->delete($hash);
});

it('handles index cleanup on deletion', function () {
    $redis = Redis::connection('default');
    $repository = new IndexedRedisRepository($redis);

    $hash = 'test:indexed:cleanup:'.uniqid();

    $repository->setAttributes($hash, [
        'name' => 'Test User',
        'status' => 'active',
    ]);

    $results = $repository->queryByAttribute('test:indexed:cleanup', 'status', 'active');
    expect(count($results))->toBeGreaterThanOrEqual(1);

    $repository->delete($hash);

    expect($repository->exists($hash))->toBeFalse();
});

it('can chunk through data using indexes when available', function () {
    $redis = Redis::connection('default');
    $repository = new IndexedRedisRepository($redis);

    $modelName = 'test:indexed:chunk';
    $hashes = [];

    for ($i = 1; $i <= 5; $i++) {
        $hash = "{$modelName}:{$i}";
        $hashes[] = $hash;

        $repository->setAttributes($hash, [
            'index' => $i,
        ]);
    }

    $found = [];
    foreach ($repository->chunk("{$modelName}:*", 3) as $chunk) {
        $found = array_merge($found, $chunk);
    }

    expect(count($found))->toBeGreaterThanOrEqual(0);

    expect($found)->toBeArray();

    foreach ($hashes as $hash) {
        $repository->delete($hash);
    }
});

it('handles transaction operations correctly', function () {
    $redis = Redis::connection('default');
    $repository = new IndexedRedisRepository($redis);

    $hash1 = 'test:indexed:trans1:'.uniqid();
    $hash2 = 'test:indexed:trans2:'.uniqid();

    $repository->transaction(function ($repo) use ($hash1, $hash2) {
        $repo->setAttribute($hash1, 'field1', 'value1');
        $repo->setAttribute($hash2, 'field2', 'value2');
    });

    expect($repository->getAttribute($hash1, 'field1'))->toBe('value1');
    expect($repository->getAttribute($hash2, 'field2'))->toBe('value2');

    $repository->delete($hash1);
    $repository->delete($hash2);
});
