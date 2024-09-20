<?php

use DirectoryTree\ActiveRedis\ModelNotFoundException;
use DirectoryTree\ActiveRedis\Query;
use DirectoryTree\ActiveRedis\Tests\Fixtures\ModelStub;
use Illuminate\Support\Facades\Redis;

beforeEach(fn () => Redis::flushall());

it('can be created without attributes', function () {
    $model = ModelStub::create();

    expect($model->exists)->toBeTrue();
    expect($model->wasRecentlyCreated)->toBeTrue();
    expect($model->getAttributes())->toHaveKeys([
        'id', 'created_at', 'updated_at',
    ]);

    $hash = $model->getModelHash();

    expect($hash)->toBe("model_stubs:id:{$model->id}");
    expect(repository()->exists($hash))->toBeTrue();
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

    $model->name = 'Jane Doe';

    expect($model->getOriginal('name'))->toBe('John Doe');

    $model->save();

    expect($model->getAttribute('name'))->toBe('Jane Doe');
    expect($model->getChanges())->toHaveKey('name');
    expect($model->getChanges()['name'])->toBe('Jane Doe');
    expect($model->getOriginal('name'))->toBe('Jane Doe');
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

it('does not throw exception when finding by existent primary key', function () {
    $model = ModelStub::create();

    $found = ModelStub::findOrFail($model->getKey());

    expect($found->is($model))->toBeTrue();
});

it('throws exception when finding by non-existent primary key', function () {
    ModelStub::findOrFail('invalid');
})->throws(ModelNotFoundException::class);

it('throws exception when retrieving first of an empty query', function () {
    ModelStub::query()->firstOrFail();
})->throws(ModelNotFoundException::class);

it('can create query', function () {
    expect(ModelStub::query())->toBeInstanceOf(Query::class);
    expect((new ModelStub)->newQuery())->toBeInstanceOf(Query::class);
});
