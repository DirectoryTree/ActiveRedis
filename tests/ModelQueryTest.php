<?php

use DirectoryTree\ActiveRedis\Exceptions\ModelNotFoundException;
use DirectoryTree\ActiveRedis\Query;
use DirectoryTree\ActiveRedis\Tests\Fixtures\ModelStub;
use DirectoryTree\ActiveRedis\Tests\Fixtures\ModelStubWithCustomKey;
use DirectoryTree\ActiveRedis\Tests\Fixtures\ModelStubWithCustomPrefix;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Redis;

beforeEach(fn () => Redis::flushall());

it('can be found by its key', function () {
    $model = ModelStub::create();

    $found = ModelStub::find($model->getKey());

    expect($found->is($model))->toBeTrue();
    expect($found->getKey())->toBe($model->getKey());
});

it('returns null when finding by null or empty key', function (?string $key) {
    ModelStub::create();

    expect(ModelStub::find($key))->toBeNull();
})->with([
    null,
    '',
    ' ',
]);

it('does not throw exception when finding by existent key', function () {
    $model = ModelStub::create();

    $found = ModelStub::findOrFail($model->getKey());

    expect($found->is($model))->toBeTrue();
});

it('throws exception when finding by non-existent key', function () {
    ModelStub::findOrFail('invalid');
})->throws(ModelNotFoundException::class, 'No query results for model [DirectoryTree\ActiveRedis\Tests\Fixtures\ModelStub] invalid');

it('throws exception when retrieving first of an empty query', function () {
    ModelStub::firstOrFail();
})->throws(ModelNotFoundException::class, 'No query results for model [DirectoryTree\ActiveRedis\Tests\Fixtures\ModelStub].');

it('can create query', function () {
    expect(ModelStub::query())->toBeInstanceOf(Query::class);
    expect((new ModelStub)->newQuery())->toBeInstanceOf(Query::class);
});

it('can be refreshed', function () {
    $model = ModelStub::create();

    $model->name = 'John Doe';

    $model->save();

    $model->refresh();

    expect($model->name)->toBe('John Doe');
});

it('can be refreshed with custom key', function () {
    $model = ModelStubWithCustomKey::create(['custom' => 'foo']);

    $model->custom = 'bar';

    $model->save();

    $model->refresh();

    expect($model->custom)->toBe('bar');
});

it('can be expired', function () {
    $model = ModelStub::create();

    $model->setExpiry(10);

    expect(Date::now()->diffInSeconds($model->getExpiry()))->toBeGreaterThanOrEqual(9);
});

it('can be created with custom prefix', function () {
    $model = ModelStubWithCustomPrefix::create();

    expect($model->getHashPrefix())->toBe('foo_bar');
    expect($model->getHashKey())->toBe("foo_bar:id:{$model->id}");
});

it('can be retrieved with first', function () {
    $model = ModelStub::create();

    $found = ModelStub::first();

    expect($found->is($model))->toBeTrue();
});

it('can be re-retrieved with fresh', function () {
    $model = ModelStub::create();

    $model->name = 'John Doe';

    $model->save();

    $fresh = $model->fresh();

    expect($fresh->is($model))->toBeTrue();
    expect($fresh->name)->toBe('John Doe');
});

it('can get all results', function () {
    foreach (range(1, 20) as $index) {
        ModelStub::create();
    }

    expect(ModelStub::get())->toHaveCount(20);
});

it('can chunk using each on results', function () {
    foreach (range(1, 20) as $index) {
        ModelStub::create();
    }

    $count = 0;

    ModelStub::each(function ($model) use (&$count) {
        expect($model)->toBeInstanceOf(ModelStub::class);

        $count++;
    });

    expect($count)->toBe(20);
});

it('can chunk chunk results', function () {
    foreach (range(1, 20) as $index) {
        ModelStub::create();
    }

    ModelStub::chunk(10, function ($models) {
        // Redis does not guarantee the count, so we
        // just check if it's greater than 0.
        expect($models->count())->toBeGreaterThan(0);
    });
});
