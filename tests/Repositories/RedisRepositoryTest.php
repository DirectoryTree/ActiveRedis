<?php

use DirectoryTree\ActiveRedis\Repositories\RedisRepository;
use Illuminate\Support\Facades\Redis;

beforeEach(fn () => Redis::flushdb());

it('can check if a hash exists', function () {
    $repository = new RedisRepository(Redis::connection());

    $repository->setAttributes('foo', ['bar' => 'baz']);

    expect($repository->exists('foo'))->toBeTrue();
    expect($repository->exists('bar'))->toBeFalse();

    $repository->delete('foo');
});

it('can chunk through hashes matching a pattern', function () {
    $repository = new RedisRepository(Redis::connection());

    $repository->setAttributes('user:1', ['foo']);
    $repository->setAttributes('user:2', ['foo']);
    $repository->setAttributes('post:1', ['foo']);
    $repository->setAttributes('user:3', ['foo']);

    $chunks = iterator_to_array($repository->chunk('user:*', 2));

    expect($chunks)->toHaveCount(2);
});

it('can set and get a single attribute', function () {
    $repository = new RedisRepository(Redis::connection());

    $repository->setAttribute('foo', 'bar', 'baz');

    expect($repository->getAttribute('foo', 'bar'))->toBe('baz');

    $repository->delete('foo');
});

it('can set and get multiple attributes', function () {
    $repository = new RedisRepository(Redis::connection());

    $repository->setAttributes('foo', ['bar' => 'baz', 'qux' => 'quux']);

    expect($repository->getAttributes('foo'))->toEqual(['bar' => 'baz', 'qux' => 'quux']);

    $repository->delete('foo');
});

it('can delete attributes', function () {
    $repository = new RedisRepository(Redis::connection());

    $repository->setAttributes('foo', ['bar' => 'baz', 'qux' => 'quux']);

    $repository->deleteAttributes('foo', 'bar');

    expect($repository->getAttributes('foo'))->toEqual(['qux' => 'quux']);

    $repository->deleteAttributes('foo', ['qux']);

    expect($repository->getAttributes('foo'))->toEqual([]);

    $repository->delete('foo');
});

it('can delete a hash', function () {
    $repository = new RedisRepository(Redis::connection());

    $repository->setAttributes('foo', ['bar' => 'baz']);

    $repository->delete('foo');

    expect($repository->exists('foo'))->toBeFalse();
});

it('can set and get expiry', function () {
    $repository = new RedisRepository(Redis::connection());

    $repository->setAttributes('foo', ['bar' => 'baz']);

    $repository->setExpiry('foo', 10);

    expect($repository->getExpiry('foo'))->toBeGreaterThanOrEqual(9);

    $repository->delete('foo');
});

it('returns null for expiry of non-existent key', function () {
    $repository = new RedisRepository(Redis::connection());

    expect($repository->getExpiry('foo'))->toBeNull();
});

it('deletes expired hashes', function () {
    $repository = new RedisRepository(Redis::connection());

    $repository->setAttributes('foo', ['bar' => 'baz']);

    $repository->setExpiry('foo', 1);

    sleep(2);

    expect($repository->getAttributes('foo'))->toEqual([]);
});

it('handles getting attributes from expired hash', function () {
    $repository = new RedisRepository(Redis::connection());

    $repository->setAttributes('foo', ['bar' => 'baz']);

    $repository->setExpiry('foo', 1);

    sleep(2);

    expect($repository->getAttribute('foo', 'bar'))->toBeFalsy();
});
