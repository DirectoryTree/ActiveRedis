<?php

use DirectoryTree\ActiveRedis\Tests\Stubs\ModelStub;

it('is routable', function () {
    $model = ModelStub::create();

    expect($model->resolveRouteBinding($model->getKey())->is($model))->toBeTrue();
    expect($model->resolveRouteBinding('invalid'))->toBeNull();
});
