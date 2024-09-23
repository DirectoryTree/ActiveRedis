<?php

use DirectoryTree\ActiveRedis\Tests\Fixtures\ModelStubWithSearchable;
use Illuminate\Support\Facades\Redis;

beforeEach(fn () => Redis::flushall());

it('generates model hash with searchable attributes when present', function () {
    $hash = (new ModelStubWithSearchable([
        'user_id' => 1,
        'company_id' => 2,
    ]))->getHashKey();

    expect($hash)->toBe('model_stub_with_searchables:id:null:company_id:2:user_id:1');
});

it('generates model hash with null searchable attributes when missing', function () {
    $hash = (new ModelStubWithSearchable)->getHashKey();

    expect($hash)->toBe('model_stub_with_searchables:id:null:company_id:null:user_id:null');
});

it('creates model with null searchable values when missing', function () {
    $model = ModelStubWithSearchable::create();

    expect($model->getHashKey())->toBe(
        "model_stub_with_searchables:id:{$model->getKey()}:company_id:null:user_id:null"
    );

    expect(ModelStubWithSearchable::first()->is($model))->toBeTrue();
});

it('creates model with searchable values when present', function () {
    $model = ModelStubWithSearchable::create([
        'user_id' => 1,
        'company_id' => 2,
    ]);

    expect($model->getHashKey())->toBe(
        "model_stub_with_searchables:id:{$model->getKey()}:company_id:2:user_id:1"
    );

    expect(ModelStubWithSearchable::first()->is($model))->toBeTrue();
});

it('can query for searchable attributes', function () {
    ModelStubWithSearchable::create([
        'user_id' => 1,
        'company_id' => 2,
    ]);

    ModelStubWithSearchable::create([
        'user_id' => 1,
        'company_id' => 2,
    ]);

    expect(ModelStubWithSearchable::exists())->toBeTrue();

    $models = ModelStubWithSearchable::query()
        ->where('user_id', 1)
        ->where('company_id', 2)
        ->get();

    expect($models->count())->toBe(2);
});
