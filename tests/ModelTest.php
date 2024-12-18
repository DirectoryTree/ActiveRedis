<?php

use Carbon\Carbon;
use DirectoryTree\ActiveRedis\Exceptions\DuplicateKeyException;
use DirectoryTree\ActiveRedis\Exceptions\InvalidKeyException;
use DirectoryTree\ActiveRedis\Tests\Stubs\ModelStub;
use DirectoryTree\ActiveRedis\Tests\Stubs\ModelStubWithCustomKey;
use DirectoryTree\ActiveRedis\Tests\Stubs\ModelStubWithSearchable;
use Illuminate\Support\Facades\Date;
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

    $model->setRawAttributes([
        'name' => 'John',
        'company' => 'Acme',
    ]);

    expect($model->name)->toBe('John');
    expect($model->company)->toBe('Acme');
});

it('can get dirty attributes', function () {
    $model = new ModelStub;

    $model->setRawAttributes([
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

it('can be first or created with id', function () {
    $model = ModelStub::firstOrCreate([
        'id' => 'foo-bar',
    ]);

    $found = ModelStub::firstOrCreate([
        'id' => 'foo-bar',
    ]);

    expect($model->is($found))->toBeTrue();
    expect($model->wasRecentlyCreated)->toBeTrue();
    expect($found->wasRecentlyCreated)->toBeFalse();
});

it('can be updated or created with id', function () {
    $model = ModelStub::updateOrCreate([
        'id' => 'foo-bar',
    ], [
        'name' => 'John Doe',
    ]);

    $found = ModelStub::updateOrCreate([
        'id' => 'foo-bar',
    ], [
        'name' => 'Jane Doe',
    ]);

    expect($model->is($found))->toBeTrue();
    expect($model->name)->toBe('John Doe');
    expect($found->name)->toBe('Jane Doe');
});

it('can be updated or created with searchable attributes', function () {
    $model = ModelStubWithSearchable::updateOrCreate([
        'user_id' => 1,
        'company_id' => 2,
    ]);

    $found = ModelStubWithSearchable::updateOrCreate([
        'user_id' => 1,
        'company_id' => 2,
    ]);

    expect($model->is($found))->toBeTrue();
});

it('can delete attribute by setting it to null', function () {
    $model = ModelStub::create([
        'name' => 'John Doe',
    ]);

    $model->update(['name' => null]);

    $model->refresh();

    expect($model->name)->toBeNull();
});

it('can delete multiple attributes by setting them to null', function () {
    $model = ModelStub::create([
        'name' => 'John Doe',
        'company' => 'Acme',
    ]);

    $model->update([
        'name' => null,
        'company' => null,
    ]);

    $model->refresh();

    expect($model->name)->toBeNull();
    expect($model->company)->toBeNull();
});

it('does not throw exception when creating with existing id when forcing', function () {
    ModelStub::create([
        'id' => 'foo',
    ]);

    ModelStub::create([
        'id' => 'foo',
    ], true);

    expect(ModelStub::get())->toHaveCount(1);
});

it('does not throw exception when updating or creating with previous null searchable attribute in values', function () {
    $first = ModelStubWithSearchable::updateOrCreate([
        'id' => 'foo',
        'user_id' => 1,
        'company_id' => 2,
    ]);

    $second = ModelStubWithSearchable::updateOrCreate([
        'id' => 'foo',
        'user_id' => 1,
        'company_id' => null,
    ], ['company_id' => 2]);

    expect($first->is($second))->toBeFalse();
    expect(ModelStubWithSearchable::get())->toHaveCount(1);
});

it('throws exception when updating or creating with previous null searchable attribute in values when force is false', function () {
    ModelStubWithSearchable::updateOrCreate([
        'id' => 'foo',
        'user_id' => 1,
        'company_id' => 2,
    ]);

    ModelStubWithSearchable::updateOrCreate([
        'id' => 'foo',
        'user_id' => 1,
        'company_id' => null,
    ], ['company_id' => 2], false);
})->throws(DuplicateKeyException::class, 'A model with the key [foo] already exists.');

it('throws exception when update or creating with previous null searchable attribute in values', function () {
    ModelStubWithSearchable::create([
        'id' => 'foo',
        'user_id' => 1,
        'company_id' => 2,
    ]);

    ModelStubWithSearchable::create([
        'id' => 'foo',
        'user_id' => 1,
        'company_id' => null,
    ]);
})->throws(DuplicateKeyException::class, 'A model with the key [foo] already exists.');

it('throws exception when creating a model with an empty key', function (mixed $id) {
    ModelStub::create(['id' => $id]);
})->with([
    '',
])->throws(InvalidKeyException::class, 'A key is required to create a model.');

it('throws exception when creating a model that already exists', function () {
    ModelStub::create(['id' => 'key']);
    ModelStub::create(['id' => 'key']);
})->throws(DuplicateKeyException::class, 'A model with the key [key] already exists.');

it('can be updated', function () {
    $model = ModelStub::create([
        'name' => 'John Doe',
    ]);

    // Simulate a minute passing.
    Carbon::setTestNow(Date::now()->addMinute());

    $model->name = 'Jane Doe';

    expect($model->getOriginal('name'))->toBe('John Doe');

    $model->save();

    expect($model->getAttribute('name'))->toBe('Jane Doe');
    expect($model->getChanges())->toHaveKey('name');
    expect($model->getChanges()['name'])->toBe('Jane Doe');
    expect($model->getOriginal('name'))->toBe('Jane Doe');
    expect($model->updated_at->toString())->not->toBe($model->created_at->toString());
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

    // Simulate a minute passing.
    Carbon::setTestNow(Date::now()->addMinute());

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

it('can be transformed into an array', function () {
    $model = ModelStub::create([
        'name' => 'John Doe',
    ]);

    expect($model->toArray())->toHaveKeys([
        'id', 'name', 'created_at', 'updated_at',
    ]);
});

it('can be converted to json', function () {
    $model = ModelStub::create([
        'name' => 'John Doe',
    ]);

    expect($model->toJson())->toBe(json_encode($model->toArray()));
});

it('can be converted to string', function () {
    $model = ModelStub::create([
        'name' => 'John Doe',
    ]);

    expect((string) $model)->toBe(json_encode($model->toArray()));
});
