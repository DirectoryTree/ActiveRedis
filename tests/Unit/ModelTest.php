<?php

use DirectoryTree\ActiveRedis\Tests\Fixtures\ModelStub;
use DirectoryTree\ActiveRedis\Tests\Fixtures\ModelStubWithCustomKey;

it('can be created with attributes', function () {
    $model = new ModelStub([
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
    $model = new ModelStub;

    expect($model->getPrefix())->toBe('model_stubs');
});

it('generates base hash from model key', function () {
    expect((new ModelStub)->getBaseHash())->toBe('model_stubs:id');
    expect((new ModelStubWithCustomKey)->getBaseHash())->toBe('model_stub_with_custom_keys:custom');
});
