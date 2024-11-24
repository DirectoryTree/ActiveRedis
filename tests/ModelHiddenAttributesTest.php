<?php

use DirectoryTree\ActiveRedis\Tests\Stubs\ModelStubWithHiddenAttributes;
use DirectoryTree\ActiveRedis\Tests\Stubs\ModelStubWithVisibleAttributes;

it('does not include hidden attributes when transformed into array', function () {
    $model = new ModelStubWithHiddenAttributes([
        'visible' => 'visible',
        'hidden' => 'hidden',
    ]);

    expect($model->toArray())->toBe(['visible' => 'visible']);

    $model->setHidden(['visible']);

    expect($model->toArray())->toBe(['hidden' => 'hidden']);
});

it('only includes visible attributes when transformed into array', function () {
    $model = new ModelStubWithVisibleAttributes([
        'visible' => 'visible',
        'hidden' => 'hidden',
    ]);

    expect($model->toArray())->toBe(['visible' => 'visible']);

    $model->setVisible(['hidden']);

    expect($model->toArray())->toBe(['hidden' => 'hidden']);
});
