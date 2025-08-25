<?php

use DirectoryTree\ActiveRedis\Tests\Stubs\ModelStubWithSearchable;
use Illuminate\Support\Facades\Redis;

it('properly cleans up all indexes when deleting indexed models', function () {
    ModelStubWithSearchable::setRepository('indexed');

    $redis = Redis::connection('default');

    $model = new ModelStubWithSearchable([
        'id' => 'deletion-fix-test-'.uniqid(),
        'user_id' => 999,
        'company_id' => 888,
        'name' => 'Deletion Test User',
        'status' => 'active',
    ]);

    $model->save();

    $hashKey = $model->getHashKey();
    $modelName = 'model_stub_with_searchables';

    expect($model->exists)->toBeTrue();
    expect($redis->exists($hashKey))->toBeGreaterThan(0);

    $mainIndexKey = "idx:{$modelName}";
    $userIndexKey = "idx:{$modelName}:user_id:999";
    $companyIndexKey = "idx:{$modelName}:company_id:888";

    $mainIndexBefore = $redis->zrange($mainIndexKey, 0, -1);
    expect($mainIndexBefore)->toContain($hashKey);

    $userIndexBefore = $redis->zrange($userIndexKey, 0, -1);
    expect($userIndexBefore)->toContain($hashKey);

    $companyIndexBefore = $redis->zrange($companyIndexKey, 0, -1);
    expect($companyIndexBefore)->toContain($hashKey);

    $model->delete();

    expect($model->exists)->toBeFalse();

    expect($redis->exists($hashKey))->toBe(0);

    $mainIndexAfter = $redis->zrange($mainIndexKey, 0, -1);
    expect($mainIndexAfter)->not()->toContain($hashKey);

    $userIndexAfter = $redis->zrange($userIndexKey, 0, -1);
    expect($userIndexAfter)->not()->toContain($hashKey);

    $companyIndexAfter = $redis->zrange($companyIndexKey, 0, -1);
    expect($companyIndexAfter)->not()->toContain($hashKey);

    $allKeys = $redis->keys("*{$model->id}*");
    expect($allKeys)->toBeEmpty();

    echo "✅ All indexes properly cleaned up on deletion!\n";
});

it('verifies the fix handles edge cases in index cleanup', function () {
    ModelStubWithSearchable::setRepository('indexed');

    $redis = Redis::connection('default');

    $model = new ModelStubWithSearchable([
        'id' => 'edge-case-test-'.uniqid(),
        'user_id' => 777,
        'company_id' => null,
        'name' => 'Some Name',
        'description' => 'Valid description',
    ]);

    $model->save();

    $hashKey = $model->getHashKey();
    $modelName = 'model_stub_with_searchables';

    $userIndexKey = "idx:{$modelName}:user_id:777";
    $companyNullIndexKey = "idx:{$modelName}:company_id:null";

    expect($redis->zrange($userIndexKey, 0, -1))->toContain($hashKey);

    expect($redis->exists($companyNullIndexKey))->toBe(0);

    $model->delete();

    expect($redis->zrange($userIndexKey, 0, -1))->not()->toContain($hashKey);
    echo "✅ Edge case cleanup works correctly!\n";
});
