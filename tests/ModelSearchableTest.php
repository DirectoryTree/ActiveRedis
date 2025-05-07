<?php

use DirectoryTree\ActiveRedis\Tests\Stubs\ModelStubWithSearchable;
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

it('can use asterisk as wildcard', function () {
    // Null Role
    ModelStubWithSearchable::create(['user_id' => 1, 'company_id' => null]);

    // Company 2
    ModelStubWithSearchable::create(['user_id' => 1, 'company_id' => 2]);
    ModelStubWithSearchable::create(['user_id' => 2, 'company_id' => 2]);

    // Company 3
    ModelStubWithSearchable::create(['user_id' => 1, 'company_id' => 3]);
    ModelStubWithSearchable::create(['user_id' => 2, 'company_id' => 3]);

    $models = ModelStubWithSearchable::query()->get();

    expect($models->count())->toBe(5);

    $models = ModelStubWithSearchable::query()
        ->where('user_id', '*')
        ->where('company_id', '*')
        ->get();

    expect($models->count())->toBe(5);

    $models = ModelStubWithSearchable::query()
        ->where('company_id', 'null')
        ->get();

    expect($models->count())->toBe(1);

    $models = ModelStubWithSearchable::query()
        ->where('user_id', '*')
        ->where('company_id', 2)
        ->get();

    expect($models->count())->toBe(2);

    $models = ModelStubWithSearchable::query()
        ->where('user_id', 1)
        ->where('company_id', '*')
        ->get();

    expect($models->count())->toBe(3);
});

it('can match portion of searchable attribute', function () {
    ModelStubWithSearchable::create(['user_id' => 1, 'company_id' => 2]);
    ModelStubWithSearchable::create(['user_id' => 1, 'company_id' => 212]);

    $models = ModelStubWithSearchable::query()
        ->where('company_id', '2*')
        ->get();

    expect($models->count())->toBe(2);

    $models = ModelStubWithSearchable::query()
        ->where('company_id', '21*')
        ->get();

    expect($models->count())->toBe(1);

    $models = ModelStubWithSearchable::query()
        ->where('company_id', '*1*')
        ->get();

    expect($models->count())->toBe(1);
});

it('can first or create with searchable attribute', function () {
    $model = ModelStubWithSearchable::firstOrCreate(
        ['user_id' => 1],
        ['name' => 'John Doe']
    );

    expect($model->user_id)->toBe('1');
    expect($model->name)->toBe('John Doe');

    $retrieved = ModelStubWithSearchable::firstOrCreate(
        ['user_id' => 1],
        ['name' => 'Jane Doe']
    );

    expect($retrieved->is($model))->toBeTrue();
    expect($retrieved->user_id)->toBe('1');
    expect($retrieved->name)->toBe('John Doe');
});

it('can update or create', function () {
    $model = ModelStubWithSearchable::updateOrCreate([
        'user_id' => '123',
    ], [
        'company_id' => '456',
    ]);

    expect($model->getHashKey())->toBe("model_stub_with_searchables:id:{$model->getKey()}:company_id:456:user_id:123");

    $model = ModelStubWithSearchable::updateOrCreate([
        'user_id' => '123',
    ], [
        'company_id' => '789',
    ]);

    expect($model->getHashKey())->toBe("model_stub_with_searchables:id:{$model->getKey()}:company_id:789:user_id:123");
    expect(ModelStubWithSearchable::get())->toHaveCount(1);
});

it('can update or create with empty values', function () {
    $model = ModelStubWithSearchable::updateOrCreate([
        'user_id' => '',
    ], [
        'company_id' => '',
    ]);

    expect($model->getHashKey())->toBe("model_stub_with_searchables:id:{$model->getKey()}:company_id:null:user_id:null");
});
