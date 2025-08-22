<?php

use DirectoryTree\ActiveRedis\Tests\Stubs\ModelStubWithSearchable;
use Illuminate\Support\Facades\Redis;

it('properly deletes all indexed model attributes when using indexed repository', function () {
    ModelStubWithSearchable::setRepository('indexed');
    
    // Create a model with searchable attributes
    $model = new ModelStubWithSearchable([
        'id' => 'test-' . uniqid(),
        'user_id' => 123,
        'company_id' => 456,
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);
    
    $model->save();
    
    $hashKey = $model->getHashKey();
    $redis = Redis::connection('default');
    
    // Verify the model exists
    expect($model->exists)->toBeTrue();
    expect($redis->exists($hashKey))->toBeTrue();
    
    // Check what attributes are stored
    $storedAttributes = $redis->hgetall($hashKey);
    expect($storedAttributes)->not()->toBeEmpty();
    expect($storedAttributes)->toHaveKeys(['user_id', 'company_id', 'name', 'email', 'created_at', 'updated_at']);
    
    // Check indexes
    $modelName = 'model_stub_with_searchables'; // This is the pluralized, snake_case name
    $mainIndexKey = "idx:{$modelName}";
    $userIndexKey = "idx:{$modelName}:user_id:123";
    $companyIndexKey = "idx:{$modelName}:company_id:456";
    
    // Verify indexes were created
    expect($redis->zrange($mainIndexKey, 0, -1))->toContain($hashKey);
    expect($redis->zrange($userIndexKey, 0, -1))->toContain($hashKey);
    expect($redis->zrange($companyIndexKey, 0, -1))->toContain($hashKey);
    
    // Now delete the model
    $model->delete();
    
    // Verify the model is marked as deleted
    expect($model->exists)->toBeFalse();
    
    // Verify the hash is completely removed
    expect($redis->exists($hashKey))->toBeFalse();
    
    // Verify all indexes are cleaned up
    expect($redis->zrange($mainIndexKey, 0, -1))->not()->toContain($hashKey);
    expect($redis->zrange($userIndexKey, 0, -1))->not()->toContain($hashKey);
    expect($redis->zrange($companyIndexKey, 0, -1))->not()->toContain($hashKey);
    
    // Verify no leftover keys exist
    $allKeys = $redis->keys("*{$model->id}*");
    expect($allKeys)->toBeEmpty();
});

it('properly deletes indexed model with complex searchable attributes', function () {
    ModelStubWithSearchable::setRepository('indexed');
    
    // Create multiple models to test proper cleanup
    $models = [];
    $ids = [];
    
    for ($i = 1; $i <= 3; $i++) {
        $model = new ModelStubWithSearchable([
            'id' => 'complex-test-' . $i . '-' . uniqid(),
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
    
    // Verify all models exist
    foreach ($models as $model) {
        expect($model->exists)->toBeTrue();
        expect($redis->exists($model->getHashKey()))->toBeTrue();
    }
    
    // Delete the middle model
    $modelToDelete = $models[1]; // Second model
    $hashKeyToDelete = $modelToDelete->getHashKey();
    
    // Check that it's in the indexes before deletion
    $mainIndexKey = "idx:{$modelName}";
    $userIndexKey = "idx:{$modelName}:user_id:101";
    $companyIndexKey = "idx:{$modelName}:company_id:201";
    
    expect($redis->zrange($mainIndexKey, 0, -1))->toContain($hashKeyToDelete);
    expect($redis->zrange($userIndexKey, 0, -1))->toContain($hashKeyToDelete);
    expect($redis->zrange($companyIndexKey, 0, -1))->toContain($hashKeyToDelete);
    
    // Delete the model
    $modelToDelete->delete();
    
    // Verify only the deleted model is removed
    expect($modelToDelete->exists)->toBeFalse();
    expect($redis->exists($hashKeyToDelete))->toBeFalse();
    
    // Verify indexes are properly cleaned up - the deleted model should not be there
    expect($redis->zrange($mainIndexKey, 0, -1))->not()->toContain($hashKeyToDelete);
    expect($redis->zrange($userIndexKey, 0, -1))->not()->toContain($hashKeyToDelete);
    expect($redis->zrange($companyIndexKey, 0, -1))->not()->toContain($hashKeyToDelete);
    
    // Verify other models still exist in indexes
    $remainingModels = [$models[0], $models[2]];
    foreach ($remainingModels as $model) {
        expect($redis->zrange($mainIndexKey, 0, -1))->toContain($model->getHashKey());
        expect($model->exists)->toBeTrue();
    }
    
    // Clean up remaining models
    foreach ($remainingModels as $model) {
        $model->delete();
    }
});