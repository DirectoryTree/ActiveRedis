<?php

namespace DirectoryTree\ActiveRedis\Tests;

use DirectoryTree\ActiveRedis\Model;
use Illuminate\Support\Facades\Redis;

beforeEach(function () {
    // Set repository to cluster mode to test the ClusterRedisRepository
    // but use regular Redis connection for backward compatibility testing
    Model::setRepository('cluster');
});

afterEach(function () {
    // Reset to default repository
    Model::setRepository('redis');
});

it('can create models in cluster mode', function () {
    $visit = new class extends Model {
        protected array $searchable = ['ip'];
    };

    $model = $visit::create([
        'ip' => '192.168.1.100',
        'user_agent' => 'Mozilla/5.0',
        'path' => '/test',
    ]);

    expect($model->exists)->toBeTrue();
    expect($model->ip)->toBe('192.168.1.100');
    expect($model->user_agent)->toBe('Mozilla/5.0');
    
    // Clean up
    $model->delete();
});

it('can query models by searchable attributes with cluster repository', function () {
    $visit = new class extends Model {
        protected array $searchable = ['user_id'];
    };

    // Create test model
    $model = $visit::create([
        'user_id' => '1001',
        'path' => '/page1',
    ]);

    // Query by user_id
    $result = $visit::where('user_id', '1001')->first();
    expect($result)->not->toBeNull();
    expect($result->path)->toBe('/page1');

    // Clean up
    $model->delete();
});

it('can use hash tags for co-location in cluster mode', function () {
    $visit = new class extends Model {
        protected array $searchable = ['user_id'];
        
        // Override to use hash tags for related keys
        public function getHashKey(): string
        {
            $userId = $this->getAttribute('user_id');
            $baseKey = parent::getHashKey();
            
            // Add hash tag to ensure related keys are on same slot
            if ($userId) {
                return str_replace('user_id:' . $userId, 'user_id:{' . $userId . '}', $baseKey);
            }
            
            return $baseKey;
        }
    };

    // Create multiple models for same user (should be co-located)
    $model1 = $visit::create([
        'user_id' => '1001',
        'path' => '/page1',
        'timestamp' => '2023-01-01',
    ]);

    $model2 = $visit::create([
        'user_id' => '1001', 
        'path' => '/page2',
        'timestamp' => '2023-01-02',
    ]);

    // Verify models can be found individually
    $found1 = $visit::find($model1->getKey());
    $found2 = $visit::find($model2->getKey());
    
    expect($found1)->not->toBeNull();
    expect($found2)->not->toBeNull();

    // Hash keys should contain hash tags
    expect($model1->getHashKey())->toContain('{1001}');
    expect($model2->getHashKey())->toContain('{1001}');

    // Clean up
    $model1->delete();
    $model2->delete();
});

it('can handle model updates with cluster repository', function () {
    $visit = new class extends Model {
        protected array $searchable = ['status'];
    };

    $model = $visit::create([
        'status' => 'pending',
        'data' => 'initial',
    ]);

    expect($model->status)->toBe('pending');
    expect($model->data)->toBe('initial');

    // Update model
    $model->update([
        'status' => 'completed',
        'data' => 'updated',
    ]);

    expect($model->status)->toBe('completed');
    expect($model->data)->toBe('updated');

    // Verify in Redis
    $fresh = $visit::find($model->getKey());
    expect($fresh)->not->toBeNull();
    expect($fresh->status)->toBe('completed');
    expect($fresh->data)->toBe('updated');

    // Clean up
    $model->delete();
});

it('can handle model expiry in cluster mode', function () {
    $visit = new class extends Model {};

    $model = $visit::create([
        'temp_data' => 'expires_soon',
    ]);

    // Set expiry
    $model->setExpiry(now()->addSeconds(5));

    // Check expiry exists
    expect($model->getExpiry())->not->toBeNull();

    // Clean up (don't wait for expiry in tests)
    $model->delete();
});

it('can chunk through models with cluster repository', function () {
    $visit = new class extends Model {};

    $models = [];
    
    // Create test models
    for ($i = 1; $i <= 3; $i++) {
        $models[] = $visit::create([
            'index' => $i,
            'data' => 'test_data_' . $i,
        ]);
    }

    // Chunk through all models
    $found = [];
    $visit::chunk(5, function ($chunk) use (&$found) {
        foreach ($chunk as $model) {
            $found[] = $model;
        }
    });

    // Should find all models with regular Redis
    expect(count($found))->toBe(3);

    // Clean up
    foreach ($models as $model) {
        $model->delete();
    }
});

it('maintains data consistency with cluster repository', function () {
    $visit = new class extends Model {
        protected array $searchable = ['category'];
    };

    // Create a model
    $model = $visit::create([
        'category' => 'tech',
        'title' => 'Test tech',
        'content' => 'Content for tech',
    ]);

    // Verify model can be found by search
    $found = $visit::where('category', 'tech')->first();
    expect($found)->not->toBeNull();
    expect($found->title)->toBe('Test tech');

    // Verify model can be found by ID
    $foundById = $visit::find($model->getKey());
    expect($foundById)->not->toBeNull();
    expect($foundById->category)->toBe('tech');

    // Clean up
    $model->delete();
});