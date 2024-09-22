<?php

use DirectoryTree\ActiveRedis\Tests\Fixtures\ModelStubWithQueryable;
use Illuminate\Support\Facades\Redis;

beforeEach(fn () => Redis::flushall());

it('generates model hash with queryable attributes when present', function () {
    $hash = (new ModelStubWithQueryable([
        'user_id' => 1,
        'company_id' => 2,
    ]))->getHashKey();

    expect($hash)->toBe('model_stub_with_queryables:id:null:company_id:2:user_id:1');
});

it('generates model hash with null queryable attributes when missing', function () {
    $hash = (new ModelStubWithQueryable)->getHashKey();

    expect($hash)->toBe('model_stub_with_queryables:id:null:company_id:null:user_id:null');
});

it('creates model with null queryable values when missing', function () {
    $model = ModelStubWithQueryable::create();

    expect($model->getHashKey())->toBe(
        "model_stub_with_queryables:id:{$model->getKey()}:company_id:null:user_id:null"
    );

    expect(ModelStubWithQueryable::first()->is($model))->toBeTrue();
});

it('creates model with queryable values when present', function () {
    $model = ModelStubWithQueryable::create([
        'user_id' => 1,
        'company_id' => 2,
    ]);

    expect($model->getHashKey())->toBe(
        "model_stub_with_queryables:id:{$model->getKey()}:company_id:2:user_id:1"
    );

    expect(ModelStubWithQueryable::first()->is($model))->toBeTrue();
});

it('can query for queryable attributes', function () {
    ModelStubWithQueryable::create([
        'user_id' => 1,
        'company_id' => 2,
    ]);

    ModelStubWithQueryable::create([
        'user_id' => 1,
        'company_id' => 2,
    ]);

    expect(ModelStubWithQueryable::exists())->toBeTrue();

    $models = ModelStubWithQueryable::query()
        ->where('user_id', 1)
        ->where('company_id', 2)
        ->get();

    expect($models->count())->toBe(2);
});
