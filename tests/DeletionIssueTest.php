<?php

use DirectoryTree\ActiveRedis\Tests\Stubs\ModelStubWithSearchable;
use Illuminate\Support\Facades\Redis;

it('properly deletes all indexed model attributes when using indexed repository', function () {
    ModelStubWithSearchable::setRepository('indexed');

    $model = new ModelStubWithSearchable([
        'id' => 'test-'.uniqid(),
        'user_id' => 123,
        'company_id' => 456,
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);

    $model->save();

    $hashKey = $model->getHashKey();
    $redis = Redis::connection('default');

    expect($model->exists)->toBeTrue();
    expect($redis->exists($hashKey))->toBeTruthy();

    $storedAttributes = $redis->hgetall($hashKey);
    expect($storedAttributes)->not()->toBeEmpty();
    expect($storedAttributes)->toHaveKeys(['user_id', 'company_id', 'name', 'email', 'created_at', 'updated_at']);

    $modelName = 'model_stub_with_searchables';
    $mainIndexKey = "idx:{$modelName}";
    $userIndexKey = "idx:{$modelName}:user_id:123";
    $companyIndexKey = "idx:{$modelName}:company_id:456";

    expect($redis->zrange($mainIndexKey, 0, -1))->toContain($hashKey);
    expect($redis->zrange($userIndexKey, 0, -1))->toContain($hashKey);
    expect($redis->zrange($companyIndexKey, 0, -1))->toContain($hashKey);

    $model->delete();

    expect($model->exists)->toBeFalse();

    expect($redis->exists($hashKey))->toBeFalsy();

    expect($redis->zrange($mainIndexKey, 0, -1))->not()->toContain($hashKey);
    expect($redis->zrange($userIndexKey, 0, -1))->not()->toContain($hashKey);
    expect($redis->zrange($companyIndexKey, 0, -1))->not()->toContain($hashKey);

    $allKeys = $redis->keys("*{$model->id}*");
    expect($allKeys)->toBeEmpty();
});

it('properly deletes indexed model with complex searchable attributes', function () {
    ModelStubWithSearchable::setRepository('indexed');

    $models = [];
    $ids = [];

    for ($i = 1; $i <= 3; $i++) {
        $model = new ModelStubWithSearchable([
            'id' => 'complex-test-'.$i.'-'.uniqid(),
            'user_id' => 100 + $i,
            'company_id' => 200 + $i,
            'status' => $i <= 2 ? 'active' : 'inactive',
            'category' => 'test-category',
        ]);

        $model->save();
        $models[] = $model;
        $ids[] = $model->id;
    }

    $redis = Redis::connection('default');
    $modelName = 'model_stub_with_searchables';

    foreach ($models as $model) {
        expect($model->exists)->toBeTrue();
        expect($redis->exists($model->getHashKey()))->toBeTruthy();
    }

    $modelToDelete = $models[1];
    $hashKeyToDelete = $modelToDelete->getHashKey();

    $mainIndexKey = "idx:{$modelName}";
    $userIndexKey = "idx:{$modelName}:user_id:102";
    $companyIndexKey = "idx:{$modelName}:company_id:202";

    expect($redis->zrange($mainIndexKey, 0, -1))->toContain($hashKeyToDelete);
    expect($redis->zrange($userIndexKey, 0, -1))->toContain($hashKeyToDelete);
    expect($redis->zrange($companyIndexKey, 0, -1))->toContain($hashKeyToDelete);

    $modelToDelete->delete();

    expect($modelToDelete->exists)->toBeFalse();
    expect($redis->exists($hashKeyToDelete))->toBeFalsy();

    expect($redis->zrange($mainIndexKey, 0, -1))->not()->toContain($hashKeyToDelete);
    expect($redis->zrange($userIndexKey, 0, -1))->not()->toContain($hashKeyToDelete);
    expect($redis->zrange($companyIndexKey, 0, -1))->not()->toContain($hashKeyToDelete);

    $remainingModels = [$models[0], $models[2]];
    foreach ($remainingModels as $model) {
        expect($redis->zrange($mainIndexKey, 0, -1))->toContain($model->getHashKey());
        expect($model->exists)->toBeTrue();
    }

    foreach ($remainingModels as $model) {
        $model->delete();
    }
});
