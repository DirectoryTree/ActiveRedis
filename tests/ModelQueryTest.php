<?php

use DirectoryTree\ActiveRedis\Tests\Fixtures\ModelStub;
use Illuminate\Support\Facades\Redis;

beforeEach(fn () => Redis::flushall());

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
