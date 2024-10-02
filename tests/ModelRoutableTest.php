<?php

use DirectoryTree\ActiveRedis\Tests\Fixtures\ModelStub;

it('is routable', function () {
    $model = ModelStub::create();

    expect($model->resolveRouteBinding($model->getKey())->is($model))->toBeTrue();
    expect($model->resolveRouteBinding('invalid'))->toBeNull();
});
