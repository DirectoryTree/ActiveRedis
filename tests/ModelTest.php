<?php

use Carbon\Carbon;
use DirectoryTree\ActiveRedis\Exceptions\DuplicateKeyException;
use DirectoryTree\ActiveRedis\Exceptions\InvalidKeyException;
use DirectoryTree\ActiveRedis\Exceptions\ModelNotFoundException;
use DirectoryTree\ActiveRedis\Query;
use DirectoryTree\ActiveRedis\Tests\Fixtures\ModelStub;
use DirectoryTree\ActiveRedis\Tests\Fixtures\ModelStubWithCustomKey;
use Illuminate\Support\Facades\Redis;

beforeEach(fn () => Redis::flushall());

it('can be instantiated with attributes', function () {
    $model = new ModelStub([
        'name' => 'John',
        'company_id' => 1,
        'company_name' => 'Acme',
    ]);

    expect($model->name)->toBe('John');
    expect($model->company_id)->toBe('1');
    expect($model->company_name)->toBe('Acme');
});

it('can be filled with attributes', function () {
    $model = (new ModelStub)->fill([
        'name' => 'John',
        'company' => 'Acme',
    ]);

    expect($model->name)->toBe('John');
    expect($model->company)->toBe('Acme');
});

it('can set attributes', function () {
    $model = new ModelStub;

    $model->name = 'John';

    $model->setAttribute('company', 'Acme');

    expect($model->name)->toBe('John');
    expect($model->company)->toBe('Acme');
});

it('can set all attributes', function () {
    $model = new ModelStub;

    $model->setAttributes([
        'name' => 'John',
        'company' => 'Acme',
    ]);

    expect($model->name)->toBe('John');
    expect($model->company)->toBe('Acme');
});

it('can get dirty attributes', function () {
    $model = new ModelStub;

    $model->setAttributes([
        'name' => 'John',
        'company' => 'Acme',
    ]);

    expect($model->isDirty())->toBeTrue();
    expect($model->isDirty('name'))->toBeTrue();
    expect($model->isDirty(['name', 'company']))->toBeTrue();
    expect($model->isDirty(['name', 'invalid']))->toBeTrue();

    expect($model->isDirty('invalid'))->toBeFalse();
    expect($model->isDirty(['foo', 'bar']))->toBeFalse();
});

it('has date attributes', function () {
    $model = new ModelStub;

    expect($model->getDates())->toBe([
        'created_at',
        'updated_at',
    ]);
});

it('does not have dates by default', function () {
    $model = new ModelStub;

    expect($model->created_at)->toBeNull();
    expect($model->updated_at)->toBeNull();
});

it('generates prefix off of class name', function () {
    expect((new ModelStub)->getHashPrefix())->toBe('model_stubs');
});

it('generates hash from unsaved model null id', function () {
    expect((new ModelStub)->getHashKey())->toBe('model_stubs:id:null');
    expect((new ModelStubWithCustomKey)->getHashKey())->toBe('model_stub_with_custom_keys:custom:null');
});

it('generates base hash from model key', function () {
    expect((new ModelStub)->getBaseHash())->toBe('model_stubs:id');
    expect((new ModelStubWithCustomKey)->getBaseHash())->toBe('model_stub_with_custom_keys:custom');
});

it('generates original hash from unsaved model', function () {
    expect((new ModelStub)->getBaseHash())->toBe('model_stubs:id');
    expect((new ModelStubWithCustomKey)->getBaseHash())->toBe('model_stub_with_custom_keys:custom');
});

it('can be created without attributes', function () {
    $model = ModelStub::create();

    expect($model->exists)->toBeTrue();
    expect($model->wasRecentlyCreated)->toBeTrue();
    expect($model->created_at)->toBeInstanceOf(Carbon::class);
    expect($model->updated_at)->toBeInstanceOf(Carbon::class);
    expect($model->getAttributes())->toHaveKeys([
        'id', 'created_at', 'updated_at',
    ]);

    $hash = $model->getHashKey();

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

it('throws exception when creating a model with an empty key', function (mixed $id) {
    ModelStub::create(['id' => $id]);
})->with([
    '',
    null,
])->throws(InvalidKeyException::class);

it('throws exception when creating a model that already exists', function () {
    ModelStub::create(['id' => 'key']);
    ModelStub::create(['id' => 'key']);
})->throws(DuplicateKeyException::class);

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

it('can update key', function () {
    $model = ModelStub::create(['id' => 'foo']);

    $model->update(['id' => 'bar']);

    expect(ModelStub::get())->toHaveCount(1);
    expect(ModelStub::where('id', 'bar')->exists())->toBeTrue();
    expect(ModelStub::where('id', 'foo')->exists())->toBeFalse();
});

it('can be touched', function () {
    $model = ModelStub::create();

    Carbon::setTestNow(now()->addMinute());

    $model->touch();

    expect($model->updated_at)->toBeInstanceOf(Carbon::class);
    expect($model->updated_at->toString())->not->toBe($model->created_at->toString());
});

it('can determine existence', function () {
    $model = ModelStub::create();

    expect(ModelStub::exists())->toBeTrue();
    expect(ModelStub::whereKey('invalid')->exists())->toBeFalse();
    expect(ModelStub::whereKey($model->getKey())->exists())->toBeTrue();
});

it('can be deleted', function () {
    $model = ModelStub::create();

    $model->delete();

    expect($model->exists)->toBeFalse();
    expect(repository()->exists($model->getHashKey()))->toBeFalse();
});

it('can be found by its key', function () {
    $model = ModelStub::create();

    $found = ModelStub::find($model->getKey());

    expect($found->getKey())->toBe($model->getKey());
});

it('does not throw exception when finding by existent key', function () {
    $model = ModelStub::create();

    $found = ModelStub::findOrFail($model->getKey());

    expect($found->is($model))->toBeTrue();
});

it('throws exception when finding by non-existent key', function () {
    ModelStub::findOrFail('invalid');
})->throws(ModelNotFoundException::class);

it('throws exception when retrieving first of an empty query', function () {
    ModelStub::query()->firstOrFail();
})->throws(ModelNotFoundException::class);

it('can create query', function () {
    expect(ModelStub::query())->toBeInstanceOf(Query::class);
    expect((new ModelStub)->newQuery())->toBeInstanceOf(Query::class);
});
