<?php

use DirectoryTree\ActiveRedis\Tests\Stubs\ModelStubWithSearchable;
use Illuminate\Support\Facades\Redis;

it('properly cleans up all indexes when deleting indexed models', function () {
    ModelStubWithSearchable::setRepository('indexed');

    $redis = Redis::connection('default');

    // Create a model with searchable attributes
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

    // Verify the model exists
    expect($model->exists)->toBeTrue();
    expect($redis->exists($hashKey))->toBeGreaterThan(0);

    // Verify indexes were created (only for searchable fields: user_id, company_id)
    $mainIndexKey = "idx:{$modelName}";
    $userIndexKey = "idx:{$modelName}:user_id:999";
    $companyIndexKey = "idx:{$modelName}:company_id:888";

    // Check main index
    $mainIndexBefore = $redis->zrange($mainIndexKey, 0, -1);
    expect($mainIndexBefore)->toContain($hashKey);

    // Check attribute indexes (only searchable fields are indexed)
    $userIndexBefore = $redis->zrange($userIndexKey, 0, -1);
    expect($userIndexBefore)->toContain($hashKey);

    $companyIndexBefore = $redis->zrange($companyIndexKey, 0, -1);
    expect($companyIndexBefore)->toContain($hashKey);

    // Now delete the model - this should clean up all indexes
    $model->delete();

    // Verify the model is marked as deleted
    expect($model->exists)->toBeFalse();

    // Verify the hash is completely removed from Redis
    expect($redis->exists($hashKey))->toBe(0);

    // Verify ALL indexes are properly cleaned up (only searchable field indexes exist)
    $mainIndexAfter = $redis->zrange($mainIndexKey, 0, -1);
    expect($mainIndexAfter)->not()->toContain($hashKey);

    $userIndexAfter = $redis->zrange($userIndexKey, 0, -1);
    expect($userIndexAfter)->not()->toContain($hashKey);

    $companyIndexAfter = $redis->zrange($companyIndexKey, 0, -1);
    expect($companyIndexAfter)->not()->toContain($hashKey);

    // Verify no leftover Redis keys exist for this specific model
    $allKeys = $redis->keys("*{$model->id}*");
    expect($allKeys)->toBeEmpty();

    echo "✅ All indexes properly cleaned up on deletion!\n";
});

it('verifies the fix handles edge cases in index cleanup', function () {
    ModelStubWithSearchable::setRepository('indexed');

    $redis = Redis::connection('default');

    // Create a model with some null/empty values for searchable fields
    $model = new ModelStubWithSearchable([
        'id' => 'edge-case-test-'.uniqid(),
        'user_id' => 777,                    // Valid searchable field
        'company_id' => null,                // Null searchable field (should normalize to 'null')
        'name' => 'Some Name',               // Not searchable, won't be indexed
        'description' => 'Valid description', // Not searchable, won't be indexed
    ]);

    $model->save();

    $hashKey = $model->getHashKey();
    $modelName = 'model_stub_with_searchables';

    // Verify indexes were created only for searchable fields with valid values
    $userIndexKey = "idx:{$modelName}:user_id:777";
    $companyNullIndexKey = "idx:{$modelName}:company_id:null"; // This won't exist because null values don't create indexes

    // Only searchable fields with non-null values are indexed
    expect($redis->zrange($userIndexKey, 0, -1))->toContain($hashKey);

    // Null values don't create indexes, so this index shouldn't exist
    expect($redis->exists($companyNullIndexKey))->toBe(0);

    // Non-searchable fields like 'name' and 'description' don't get indexed at all

    // Delete the model
    $model->delete();

    // Verify cleanup worked for the searchable field indexes (only user_id index existed)
    expect($redis->zrange($userIndexKey, 0, -1))->not()->toContain($hashKey);

    // company_id:null index never existed, so no cleanup needed for it
    echo "✅ Edge case cleanup works correctly!\n";
});
