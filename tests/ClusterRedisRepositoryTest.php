<?php

namespace DirectoryTree\ActiveRedis\Tests;

use DirectoryTree\ActiveRedis\Repositories\ClusterRedisRepository;
use Illuminate\Redis\RedisManager;
use Illuminate\Support\Facades\Redis;
use ReflectionClass;

beforeEach(function () {
    // Use default Redis connection for cluster repository tests
    // This tests backward compatibility with normal Redis
});

it('can check if a hash exists in cluster repository', function () {
    $redis = Redis::connection('default');
    $repository = new ClusterRedisRepository($redis);

    $hash = 'test:cluster:exists:' . uniqid();
    
    expect($repository->exists($hash))->toBeFalse();
    
    $redis->hset($hash, 'field', 'value');
    
    expect($repository->exists($hash))->toBeTrue();
    
    $redis->del($hash);
});

it('can set and get a single attribute in cluster', function () {
    $redis = Redis::connection('default');
    $repository = new ClusterRedisRepository($redis);

    $hash = 'test:cluster:single:' . uniqid();
    $field = 'test_field';
    $value = 'test_value';

    $repository->setAttribute($hash, $field, $value);
    
    expect($repository->getAttribute($hash, $field))->toBe($value);
    
    $redis->del($hash);
});

it('can set and get multiple attributes in cluster', function () {
    $redis = Redis::connection('default');
    $repository = new ClusterRedisRepository($redis);

    $hash = 'test:cluster:multiple:' . uniqid();
    $attributes = [
        'field1' => 'value1',
        'field2' => 'value2',
        'field3' => 'value3',
    ];

    $repository->setAttributes($hash, $attributes);
    
    expect($repository->getAttributes($hash))->toBe($attributes);
    
    $redis->del($hash);
});

it('can delete attributes in cluster', function () {
    $redis = Redis::connection('default');
    $repository = new ClusterRedisRepository($redis);

    $hash = 'test:cluster:delete:' . uniqid();
    $attributes = [
        'field1' => 'value1',
        'field2' => 'value2',
        'field3' => 'value3',
    ];

    $repository->setAttributes($hash, $attributes);
    
    $repository->deleteAttributes($hash, ['field1', 'field3']);
    
    $remaining = $repository->getAttributes($hash);
    
    expect($remaining)->toBe(['field2' => 'value2']);
    
    $redis->del($hash);
});

it('can delete a hash in cluster', function () {
    $redis = Redis::connection('default');
    $repository = new ClusterRedisRepository($redis);

    $hash = 'test:cluster:deletehash:' . uniqid();
    
    $repository->setAttribute($hash, 'field', 'value');
    expect($repository->exists($hash))->toBeTrue();
    
    $repository->delete($hash);
    expect($repository->exists($hash))->toBeFalse();
});

it('can set and get expiry in cluster', function () {
    $redis = Redis::connection('default');
    $repository = new ClusterRedisRepository($redis);

    $hash = 'test:cluster:expiry:' . uniqid();
    $seconds = 30;

    $repository->setAttribute($hash, 'field', 'value');
    $repository->setExpiry($hash, $seconds);
    
    $expiry = $repository->getExpiry($hash);
    
    expect($expiry)->toBeGreaterThan(0);
    expect($expiry)->toBeLessThanOrEqual($seconds);
    
    $redis->del($hash);
});

it('returns null for expiry of non-existent key in cluster', function () {
    $redis = Redis::connection('default');
    $repository = new ClusterRedisRepository($redis);

    $hash = 'test:cluster:nonexistent:' . uniqid();
    
    expect($repository->getExpiry($hash))->toBeNull();
});

it('can chunk through hashes matching a pattern', function () {
    $redis = Redis::connection('default');
    $repository = new ClusterRedisRepository($redis);

    $prefix = 'test:cluster:chunk:' . uniqid();
    $hashes = [];
    
    // Create test hashes across different slots
    for ($i = 1; $i <= 5; $i++) {
        $hash = $prefix . ':' . $i;
        $hashes[] = $hash;
        $repository->setAttribute($hash, 'index', (string)$i);
    }

    $found = [];
    foreach ($repository->chunk($prefix . ':*', 5) as $chunk) {
        $found = array_merge($found, $chunk);
    }

    // Clean up
    foreach ($hashes as $hash) {
        $redis->del($hash);
    }

    // Should find all keys with normal Redis
    expect(count($found))->toBe(5);
});

it('handles cluster detection with regular Redis', function () {
    $redis = Redis::connection('default');
    $repository = new ClusterRedisRepository($redis);

    // Use reflection to test protected method
    $reflection = new ReflectionClass($repository);
    $method = $reflection->getMethod('isClusterConnection');
    $method->setAccessible(true);

    // With regular Redis, cluster detection should return false
    $result = $method->invoke($repository);
    expect($result)->toBeFalse();
});

it('can perform transactions in cluster (same slot)', function () {
    $redis = Redis::connection('default');
    $repository = new ClusterRedisRepository($redis);

    // Use hash tags to ensure keys are in the same slot
    $hash1 = 'test:cluster:{same}:trans1:' . uniqid();
    $hash2 = 'test:cluster:{same}:trans2:' . uniqid();

    $repository->transaction(function ($repo) use ($hash1, $hash2) {
        $repo->setAttribute($hash1, 'field1', 'value1');
        $repo->setAttribute($hash2, 'field2', 'value2');
    });

    expect($repository->getAttribute($hash1, 'field1'))->toBe('value1');
    expect($repository->getAttribute($hash2, 'field2'))->toBe('value2');

    // Clean up
    $redis->del($hash1);
    $redis->del($hash2);
});