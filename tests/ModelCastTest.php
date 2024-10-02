<?php

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use DirectoryTree\ActiveRedis\Tests\Fixtures\ModelEnumStub;
use DirectoryTree\ActiveRedis\Tests\Fixtures\ModelStubWithCasts;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Redis;

beforeEach(fn () => Redis::flushall());

it('casts enum', function () {
    $model = new ModelStubWithCasts;

    $model->enum = 'one';

    $model->save();
    $model->refresh();

    expect($model->enum)->toBe(ModelEnumStub::One);
    expect($model->enum->value)->toBe(ModelEnumStub::One->value);
});

it('casts json', function () {
    $model = new ModelStubWithCasts;

    $model->json = [
        'name' => 'John',
        'age' => 30,
    ];

    $model->save();
    $model->refresh();

    expect($model->json)->toBeArray();
    expect($model->json['name'])->toEqual('John');
    expect($model->json['age'])->toEqual(30);

    expect($model->toArray()['json'])->toBe([
        'name' => 'John',
        'age' => 30,
    ]);
});

it('casts array', function () {
    $model = new ModelStubWithCasts;

    $model->array = [1, 2, 3];

    $model->save();
    $model->refresh();

    expect($model->array)->toBeArray();
    expect($model->array)->toEqual([1, 2, 3]);
    expect($model->toArray()['array'])->toBe([1, 2, 3]);
});

it('casts date', function () {
    Date::setTestNow(Date::now());

    $model = new ModelStubWithCasts;

    $model->date = Date::now()->format('Y-m-d');

    $model->save();
    $model->refresh();

    expect($model->date)->toBeInstanceOf(Carbon::class);
    expect($model->date)->toEqual(Date::today());
    expect($model->date)->isSameDay(Date::now());
    expect($model->toArray()['date'])->toBe(Date::now()->startOfDay()->toISOString());
});

it('casts string', function () {
    $model = new ModelStubWithCasts;

    $model->string = 123;

    $model->save();
    $model->refresh();

    expect($model->string)->toBeString();
    expect($model->string)->toEqual('123');
});

it('casts object', function () {
    $model = new ModelStubWithCasts;

    $object = new stdClass;
    $object->name = 'John';
    $object->age = 30;

    $model->object = $object;

    $model->save();
    $model->refresh();

    expect($model->object)->toBeObject();
    expect($model->object)->toBeInstanceOf(stdClass::class);
    expect($model->object->name)->toEqual('John');
    expect($model->object->age)->toEqual(30);
    expect($model->toArray()['object'])->toBeInstanceOf(stdClass::class);
});

it('casts decimal', function () {
    $model = new ModelStubWithCasts;

    $model->decimal = 123.456789;

    $model->save();
    $model->refresh();

    expect($model->decimal)->toBeString();
    expect($model->decimal)->toEqual('123.46');
    expect($model->toArray()['decimal'])->toBe('123.46');
});

it('casts timestamp', function () {
    $model = new ModelStubWithCasts;

    $model->timestamp = $timestamp = Date::now()->timestamp;

    $model->save();
    $model->refresh();

    expect($model->timestamp)->toBeInt();
    expect($model->timestamp)->toEqual($timestamp);
    expect($model->toArray()['timestamp'])->toBe($timestamp);
});

it('casts collection', function () {
    $model = new ModelStubWithCasts;

    $model->collection = new Collection([1, 2, 3]);

    $model->save();
    $model->refresh();

    expect($model->collection)->toBeInstanceOf(Collection::class);
    expect($model->collection)->toEqual(collect([1, 2, 3]));
    expect($model->toArray()['collection'])->toBe([1, 2, 3]);
});

it('casts integer', function () {
    $model = new ModelStubWithCasts;

    $model->integer = 123;

    $model->save();
    $model->refresh();

    expect($model->integer)->toBeInt();
    expect($model->integer)->toEqual(123);
    expect($model->toArray()['integer'])->toBe(123);
});

it('casts boolean', function () {
    $model = new ModelStubWithCasts;

    $model->save();
    $model->refresh();

    $model->boolean = '1';
    expect($model->boolean)->toBeTrue();

    $model->save();
    $model->refresh();

    $model->boolean = '0';
    expect($model->boolean)->toBeFalse();
    expect($model->toArray()['boolean'])->toBeFalse();
});

it('casts float', function () {
    $model = new ModelStubWithCasts;

    $model->float = '123.45';

    $model->save();
    $model->refresh();

    expect($model->float)->toBeFloat();
    expect($model->float)->toBe(123.45);
    expect($model->toArray()['float'])->toBe(123.45);
});

it('casts datetime', function () {
    $model = new ModelStubWithCasts;

    $model->datetime = '2024-09-24 15:30:00';

    $model->save();
    $model->refresh();

    expect($model->datetime)->toBeInstanceOf(Carbon::class);
    expect($model->datetime)->toEqual(Carbon::parse('2024-09-24 15:30:00'));
    expect($model->toArray()['datetime'])->toBe(Carbon::parse('2024-09-24 15:30:00')->toISOString());
});

it('casts custom_datetime', function () {
    $model = new ModelStubWithCasts;

    $model->custom_datetime = '2024-09-24';

    $model->save();
    $model->refresh();

    expect($model->custom_datetime)->toBeInstanceOf(Carbon::class);
    expect($model->custom_datetime)->toEqual(Carbon::parse('2024-09-24 00:00:00'));
    expect($model->toArray()['custom_datetime'])->toBe('2024-09-24');
});

it('casts immutable_date', function () {
    $model = new ModelStubWithCasts;

    $model->immutable_date = '2024-09-24';

    $model->save();
    $model->refresh();

    expect($model->immutable_date)->toBeInstanceOf(CarbonImmutable::class);
    expect($model->immutable_date)->toEqual(CarbonImmutable::parse('2024-09-24 00:00:00'));
    expect($model->toArray()['immutable_date'])->toBe(CarbonImmutable::parse('2024-09-24 00:00:00')->toISOString());
});

it('casts immutable_datetime', function () {
    $model = new ModelStubWithCasts;

    $model->immutable_datetime = '2024-09-24 15:30:00';

    $model->save();
    $model->refresh();

    expect($model->immutable_datetime)->toBeInstanceOf(CarbonImmutable::class);
    expect($model->immutable_datetime)->toEqual(CarbonImmutable::parse('2024-09-24 15:30:00'));
    expect($model->toArray()['immutable_datetime'])->toBe(CarbonImmutable::parse('2024-09-24 15:30:00')->toISOString());
});
