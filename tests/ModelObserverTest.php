<?php

use DirectoryTree\ActiveRedis\Tests\Stubs\ModelObserverStub;
use DirectoryTree\ActiveRedis\Tests\Stubs\ModelStub;
use Illuminate\Support\Facades\Redis;

beforeEach(function () {
    Redis::flushall();
    ModelStub::flushEventListeners();
});

it('can be observed', function () {
    ModelStub::observe(ModelObserverStub::class);

    ModelStub::create();

    $model = ModelStub::first();

    $model->update(['foo' => 'bar']);

    $model->delete();

    expect(ModelObserverStub::$eventsCalled)->toBe([
        'saving',
        'creating',
        'created',
        'saved',
        'retrieved',
        'saving',
        'updating',
        'updated',
        'saved',
        'deleted',
    ]);
});
