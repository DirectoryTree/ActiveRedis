<?php

use DirectoryTree\ActiveRedis\Tests\Stubs\ModelStubWithEventListeners;
use Illuminate\Support\Facades\Redis;

beforeEach(function () {
    Redis::flushall();

    // Clear the events called array before each test.
    ModelStubWithEventListeners::$eventsCalled = [];
});

it('fires events on create', function () {
    ModelStubWithEventListeners::create();

    expect(ModelStubWithEventListeners::$eventsCalled)->toBe([
        'saving',
        'creating',
        'created',
        'saved',
    ]);
});

it('fires events on update', function () {
    $model = ModelStubWithEventListeners::create();

    ModelStubWithEventListeners::$eventsCalled = [];

    $model->update(['name' => 'test']);

    expect(ModelStubWithEventListeners::$eventsCalled)->toBe([
        'saving',
        'updating',
        'updated',
        'saved',
    ]);
});

it('fires events on delete', function () {
    $model = ModelStubWithEventListeners::create();

    ModelStubWithEventListeners::$eventsCalled = [];

    $model->delete();

    expect(ModelStubWithEventListeners::$eventsCalled)->toBe([
        'deleting',
        'deleted',
    ]);
});

it('fires events on retrieved', function () {
    ModelStubWithEventListeners::create();

    ModelStubWithEventListeners::$eventsCalled = [];

    ModelStubWithEventListeners::first();

    expect(ModelStubWithEventListeners::$eventsCalled)->toBe(['retrieved']);
});

it('fires events on many retrieved', function () {
    ModelStubWithEventListeners::create();
    ModelStubWithEventListeners::create();

    ModelStubWithEventListeners::$eventsCalled = [];

    ModelStubWithEventListeners::all();

    expect(ModelStubWithEventListeners::$eventsCalled)->toBe(['retrieved', 'retrieved']);
});

it('does not fire updated and saved when model is saved without changes', function () {
    $model = ModelStubWithEventListeners::create();

    ModelStubWithEventListeners::$eventsCalled = [];

    $model->save();

    expect(ModelStubWithEventListeners::$eventsCalled)->toBe([
        'saving',
        'updating',
    ]);
});
