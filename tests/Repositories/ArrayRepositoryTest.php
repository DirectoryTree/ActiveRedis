<?php

use DirectoryTree\ActiveRedis\Repositories\ArrayRepository;

it('can check if a hash exists', function () {
    $repository = new ArrayRepository(['foo' => ['bar' => 'baz']]);

    expect($repository->exists('foo'))->toBeTrue();
    expect($repository->exists('bar'))->toBeFalse();
});

it('can chunk through hashes matching a pattern', function () {
    $repository = new ArrayRepository([
        'user:1' => [],
        'user:2' => [],
        'post:1' => [],
        'user:3' => [],
    ]);

    $chunks = iterator_to_array($repository->chunk('user:*', 2));

    expect($chunks)->toHaveCount(2);
    expect($chunks[0])->toEqual(['user:1', 'user:2']);
    expect($chunks[1])->toEqual(['user:3']);
});

it('can set and get a single attribute', function () {
    $repository = new ArrayRepository;

    $repository->setAttribute('foo', 'bar', 'baz');

    expect($repository->getAttribute('foo', 'bar'))->toBe('baz');
});

it('can set and get multiple attributes', function () {
    $repository = new ArrayRepository;

    $repository->setAttributes('foo', ['bar' => 'baz', 'qux' => 'quux']);

    expect($repository->getAttributes('foo'))->toEqual(['bar' => 'baz', 'qux' => 'quux']);
});

it('can delete attributes', function () {
    $repository = new ArrayRepository(['foo' => ['bar' => 'baz', 'qux' => 'quux']]);

    $repository->deleteAttributes('foo', 'bar');

    expect($repository->getAttributes('foo'))->toEqual(['qux' => 'quux']);

    $repository->deleteAttributes('foo', ['qux']);

    expect($repository->getAttributes('foo'))->toEqual([]);
});

it('can delete a hash', function () {
    $repository = new ArrayRepository(['foo' => ['bar' => 'baz']]);

    $repository->delete('foo');

    expect($repository->exists('foo'))->toBeFalse();
});

it('can set and get expiry', function () {
    $repository = new ArrayRepository;

    $repository->setExpiry('foo', 10);

    expect($repository->getExpiry('foo'))->toBeGreaterThanOrEqual(9);
});

it('returns null for expiry of non-existent key', function () {
    $repository = new ArrayRepository;

    expect($repository->getExpiry('foo'))->toBeNull();
});

it('deletes expired hashes', function () {
    $repository = new ArrayRepository;

    $repository->setAttributes('foo', ['bar' => 'baz']);

    $repository->setExpiry('foo', 1);

    sleep(2);

    expect($repository->getAttributes('foo'))->toEqual([]);
});

it('handles getting attributes from expired hash', function () {
    $repository = new ArrayRepository;

    $repository->setAttributes('foo', ['bar' => 'baz']);

    $repository->setExpiry('foo', 1);

    sleep(2);

    expect($repository->getAttribute('foo', 'bar'))->toBeNull();
});
