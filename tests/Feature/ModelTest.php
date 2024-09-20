<?php

use Illuminate\Support\Facades\Redis;
use DirectoryTree\ActiveRedis\Tests\Fixtures\ModelStub;

beforeEach(fn () => Redis::flushall());

it('can be created without attributes', function () {
    $model = ModelStub::create();

    expect($model->exists)->toBeTrue();
    expect($model->wasRecentlyCreated)->toBeTrue();
    expect($model->getAttributes())->toHaveKeys([
        'id', 'created_at', 'updated_at',
    ]);

    expect(repository()->exists($model->getModelHash()))->toBeTrue();
});

it('can be created with attributes', function () {
    $model = ModelStub::create([
        'name' => 'John Doe',
    ]);

    expect($model->getAttributes())->toHaveKeys([
        'id', 'name', 'created_at', 'updated_at',
    ]);

    expect($model->getAttribute('name'))->toBe('John Doe');
});

it('can be updated', function () {
    $model = ModelStub::create([
        'name' => 'John Doe',
    ]);

    $model->update([
        'name' => 'Jane Doe',
    ]);

    expect($model->getAttribute('name'))->toBe('Jane Doe');
    expect($model->getChanges())->toHaveKey('name');
    expect($model->getChanges()['name'])->toBe('Jane Doe');
});

it('can be deleted', function () {
    $model = ModelStub::create();

    $model->delete();

    expect($model->exists)->toBeFalse();
    expect(repository()->exists($model->getModelHash()))->toBeFalse();
});

it('can be found by its primary key', function () {
    $model = ModelStub::create();

    $found = ModelStub::find($model->getKey());

    expect($found->getKey())->toBe($model->getKey());
});

