<?php

use DirectoryTree\ActiveRedis\Tests\Stubs\ModelStubWithSearchable;
use Illuminate\Support\Facades\Redis;

it('properly cleans up all indexes when deleting indexed models', function () {
    ModelStubWithSearchable::setRepository('indexed');
    
    $redis = Redis::connection('default');
    
    // Create a model with searchable attributes
    $model = new ModelStubWithSearchable([
        'id' => 'deletion-fix-test-' . uniqid(),
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
    
    // Verify indexes were created
    $mainIndexKey = "idx:{$modelName}";
    $userIndexKey = "idx:{$modelName}:user_id:999";
    $companyIndexKey = "idx:{$modelName}:company_id:888";
    $nameIndexKey = "idx:{$modelName}:name:Deletion Test User";
    $statusIndexKey = "idx:{$modelName}:status:active";
    
    // Check main index
    $mainIndexBefore = $redis->zrange($mainIndexKey, 0, -1);
    expect($mainIndexBefore)->toContain($hashKey);
    
    // Check attribute indexes
    $userIndexBefore = $redis->zrange($userIndexKey, 0, -1);
    expect($userIndexBefore)->toContain($hashKey);
    
    $companyIndexBefore = $redis->zrange($companyIndexKey, 0, -1);  
    expect($companyIndexBefore)->toContain($hashKey);
    
    $nameIndexBefore = $redis->zrange($nameIndexKey, 0, -1);
    expect($nameIndexBefore)->toContain($hashKey);
    
    $statusIndexBefore = $redis->zrange($statusIndexKey, 0, -1);
    expect($statusIndexBefore)->toContain($hashKey);
    
    // Now delete the model - this should clean up all indexes
    $model->delete();
    
    // Verify the model is marked as deleted
    expect($model->exists)->toBeFalse();
    
    // Verify the hash is completely removed from Redis
    expect($redis->exists($hashKey))->toBe(0);
    
    // Verify ALL indexes are properly cleaned up
    $mainIndexAfter = $redis->zrange($mainIndexKey, 0, -1);
    expect($mainIndexAfter)->not()->toContain($hashKey);
    
    $userIndexAfter = $redis->zrange($userIndexKey, 0, -1);
    expect($userIndexAfter)->not()->toContain($hashKey);
    
    $companyIndexAfter = $redis->zrange($companyIndexKey, 0, -1);
    expect($companyIndexAfter)->not()->toContain($hashKey);
    
    $nameIndexAfter = $redis->zrange($nameIndexKey, 0, -1);
    expect($nameIndexAfter)->not()->toContain($hashKey);
    
    $statusIndexAfter = $redis->zrange($statusIndexKey, 0, -1);
    expect($statusIndexAfter)->not()->toContain($hashKey);
    
    // Verify no leftover Redis keys exist for this specific model
    $allKeys = $redis->keys("*{$model->id}*");
    expect($allKeys)->toBeEmpty();
    
    echo "✅ All indexes properly cleaned up on deletion!\n";
});

it('verifies the fix handles edge cases in index cleanup', function () {
    ModelStubWithSearchable::setRepository('indexed');
    
    $redis = Redis::connection('default');
    
    // Create a model with some null/empty values
    $model = new ModelStubWithSearchable([
        'id' => 'edge-case-test-' . uniqid(),
        'user_id' => 777,
        'company_id' => null, // This should not create an index
        'name' => '',        // Empty string should not create an index
        'description' => 'Valid description', // This should create an index
    ]);
    
    $model->save();
    
    $hashKey = $model->getHashKey();
    $modelName = 'model_stub_with_searchables';
    
    // Verify indexes were created only for valid values
    $userIndexKey = "idx:{$modelName}:user_id:777";
    $descIndexKey = "idx:{$modelName}:description:Valid description";
    $companyIndexKey = "idx:{$modelName}:company_id:"; // Should not exist
    $nameIndexKey = "idx:{$modelName}:name:"; // Should not exist
    
    expect($redis->zrange($userIndexKey, 0, -1))->toContain($hashKey);
    expect($redis->zrange($descIndexKey, 0, -1))->toContain($hashKey);
    
    // These should be empty because null/empty values don't create indexes
    expect($redis->exists($companyIndexKey))->toBe(0);
    expect($redis->exists($nameIndexKey))->toBe(0);
    
    // Delete the model
    $model->delete();
    
    // Verify cleanup worked for the valid indexes
    expect($redis->zrange($userIndexKey, 0, -1))->not()->toContain($hashKey);
    expect($redis->zrange($descIndexKey, 0, -1))->not()->toContain($hashKey);
    
    echo "✅ Edge case cleanup works correctly!\n";
});