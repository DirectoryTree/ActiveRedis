# Redis Cluster Support for ActiveRedis

ActiveRedis now supports Redis Cluster mode, enabling horizontal scaling and high availability with AWS ElastiCache Redis Cluster and other Redis Cluster deployments.

## Table of Contents

- [Overview](#overview)
- [Quick Start](#quick-start)
- [Local Development Setup](#local-development-setup)
- [AWS ElastiCache Configuration](#aws-elasticache-configuration)
- [Model Configuration](#model-configuration)
- [Key Design Considerations](#key-design-considerations)
- [Performance Optimization](#performance-optimization)
- [Testing](#testing)
- [Troubleshooting](#troubleshooting)

## Overview

Redis Cluster mode distributes data across multiple Redis nodes, providing:

- **Horizontal Scaling**: Distribute data across multiple nodes
- **High Availability**: Automatic failover with replica nodes
- **Improved Performance**: Parallel operations across cluster nodes
- **AWS Integration**: Native support for ElastiCache Redis Cluster

### Key Features

- ✅ **Secondary Index System** - Uses Sorted Sets for efficient cluster-compatible queries
- ✅ **No SCAN Dependencies** - Eliminates problematic SCAN operations in cluster mode
- ✅ **Hash tag support** - Co-locate related keys for transactions
- ✅ **Automatic cluster detection** - Works with both Predis and PhpRedis
- ✅ **AWS ElastiCache compatibility** - Production-ready for AWS environments
- ✅ **Transparent operation** - Existing code works with minimal changes
- ✅ **Hybrid Compatibility** - Works optimally in both cluster and non-cluster environments

## Quick Start

### 1. Enable Cluster Mode

```php
use DirectoryTree\ActiveRedis\Model;

// Use indexed repository for optimal cluster performance
Model::setRepository('indexed');

// Or use basic cluster repository
Model::setRepository('cluster');

// Or per model with advanced indexing
class Visit extends Model 
{
    protected static string $repository = 'indexed';
    protected ?string $connection = 'activeredis_cluster';
}
```

### 2. Configure Redis Cluster Connection

Add to your `config/database.php`:

```php
'redis' => [
    'activeredis_cluster' => [
        'client' => 'predis',
        'cluster' => 'redis',
        'default' => [
            'host' => env('REDIS_CLUSTER_HOST', '127.0.0.1'),
            'port' => env('REDIS_CLUSTER_PORT', 7001),
        ],
        'clusters' => [
            ['host' => '127.0.0.1', 'port' => 7001],
            ['host' => '127.0.0.1', 'port' => 7002],
            ['host' => '127.0.0.1', 'port' => 7003],
        ],
        'options' => [
            'cluster' => 'redis',
        ],
    ],
],
```

### 3. Use Models as Normal

```php
// Create models
$visit = Visit::create([
    'ip' => '192.168.1.100',
    'user_agent' => 'Mozilla/5.0',
    'path' => '/home',
]);

// Query models (works across all cluster nodes)
$visits = Visit::where('ip', '192.168.1.*')->get();

// Update/delete operations work transparently
$visit->update(['path' => '/updated']);
$visit->delete();
```

## Local Development Setup

### Using Docker Compose

1. **Start Redis Cluster**:
   ```bash
   # Use the provided cluster setup
   ./scripts/setup-redis-cluster.sh
   ```

2. **Verify Cluster**:
   ```bash
   docker exec redis-cluster-1 redis-cli -c -h redis-cluster-1 -p 7001 cluster info
   ```

3. **Run Tests**:
   ```bash
   REDIS_CLIENT=predis vendor/bin/pest tests/ClusterRedisRepositoryTest.php
   ```

### Manual Cluster Setup

```bash
# Create 6 Redis instances
for port in {7001..7006}; do
    redis-server --port $port --cluster-enabled yes \
                 --cluster-config-file nodes-${port}.conf \
                 --cluster-node-timeout 5000 \
                 --appendonly yes --daemonize yes
done

# Create cluster
redis-cli --cluster create 127.0.0.1:7001 127.0.0.1:7002 127.0.0.1:7003 \
          127.0.0.1:7004 127.0.0.1:7005 127.0.0.1:7006 \
          --cluster-replicas 1
```

## AWS ElastiCache Configuration

### Standard Configuration

```php
'redis' => [
    'activeredis_cluster_aws' => [
        'client' => 'predis',
        'cluster' => 'redis',
        'default' => [
            'host' => env('REDIS_CLUSTER_ENDPOINT'),
            'port' => env('REDIS_CLUSTER_PORT', 6379),
            'password' => env('REDIS_AUTH_TOKEN'),
        ],
        'options' => [
            'cluster' => 'redis',
            'ssl' => env('REDIS_SSL', true),
            'verify_peer' => false,
        ],
    ],
],
```

### Environment Variables

```bash
# .env file
REDIS_CLUSTER_ENDPOINT=your-cluster.xxxxx.cache.amazonaws.com
REDIS_CLUSTER_PORT=6379
REDIS_AUTH_TOKEN=your-auth-token-here
REDIS_SSL=true
```

### Multi-Node Configuration (Recommended)

For production environments, specify multiple endpoints:

```php
'activeredis_cluster_production' => [
    'client' => 'predis',
    'cluster' => 'redis',
    'clusters' => [
        ['host' => 'node1.cluster.cache.amazonaws.com', 'port' => 6379],
        ['host' => 'node2.cluster.cache.amazonaws.com', 'port' => 6379],
        ['host' => 'node3.cluster.cache.amazonaws.com', 'port' => 6379],
    ],
    'options' => [
        'cluster' => 'redis',
        'ssl' => ['verify_peer' => false],
        'timeout' => 5.0,
        'password' => env('REDIS_AUTH_TOKEN'),
    ],
],
```

## Model Configuration

### Basic Cluster Model

```php
use DirectoryTree\ActiveRedis\Model;

class Visit extends Model
{
    protected static string $repository = 'cluster';
    protected ?string $connection = 'activeredis_cluster';
    protected array $searchable = ['ip', 'user_id'];
}
```

### Hash Tag Optimization

Use hash tags to ensure related keys are co-located on the same cluster slot:

```php
class UserVisit extends Model
{
    protected static string $repository = 'cluster';
    protected array $searchable = ['user_id'];
    
    public function getHashKey(): string
    {
        $userId = $this->getAttribute('user_id');
        $baseKey = parent::getHashKey();
        
        // Add hash tag to co-locate all visits for a user
        if ($userId) {
            return str_replace(
                'user_id:' . $userId, 
                'user_id:{' . $userId . '}', 
                $baseKey
            );
        }
        
        return $baseKey;
    }
}
```

This ensures all visits for a user are on the same cluster node, enabling:
- Multi-key transactions
- Atomic operations
- Better performance for user-specific queries

### Advanced Configuration

```php
class Visit extends Model
{
    protected static string $repository = 'cluster';
    protected ?string $connection = 'activeredis_cluster';
    
    // Searchable attributes for efficient querying
    protected array $searchable = ['ip', 'user_id', 'country'];
    
    // Type casting works normally
    protected array $casts = [
        'user_id' => 'integer',
        'timestamp' => 'datetime',
        'metadata' => 'json',
    ];
    
    // Events work across cluster nodes
    protected static function booted(): void
    {
        static::creating(function (self $visit) {
            $visit->timestamp = now();
        });
    }
}
```

## Key Design Considerations

### 1. Cluster-aware SCAN Operations

ActiveRedis automatically:
- Detects cluster mode
- Scans all master nodes
- Aggregates results from all nodes
- Handles node failures gracefully

### 2. Transaction Limitations

Redis Cluster transactions are limited to keys in the same hash slot:

```php
// ❌ May fail - keys might be on different nodes
$model1->update(['field' => 'value1']);
$model2->update(['field' => 'value2']); // Different hash slot

// ✅ Works - use hash tags to co-locate keys
$userVisit1 = UserVisit::create(['user_id' => '{1001}', 'data' => 'visit1']);
$userVisit2 = UserVisit::create(['user_id' => '{1001}', 'data' => 'visit2']);
// Both visits are now on the same node
```

### 3. Query Performance with Secondary Indexes

The `indexed` repository provides significant performance improvements:

- **Single-key operations**: Optimal (direct routing)  
- **Attribute queries**: O(log n) using sorted set indexes
- **Pattern queries**: Efficient using pre-built indexes (no SCAN needed)
- **Searchable attributes**: Instant lookup using secondary indexes
- **Range queries**: Native sorted set range operations

**Index Types:**
- **Main Index**: `idx:{model_name}` - All records for a model type
- **Attribute Index**: `idx:{model_name}:{attribute}:{value}` - Records by attribute value
- **Composite Support**: Multiple attribute queries can be intersected

**Benefits over SCAN-based approaches:**
- ✅ Consistent performance in both cluster and non-cluster modes
- ✅ No cross-node scanning required
- ✅ Logarithmic query complexity instead of linear
- ✅ Automatic index maintenance with 7-day expiry

### 4. Connection Management

The cluster repository manages connections efficiently:
- Reuses existing cluster connections
- Handles node discovery automatically
- Provides failover support

## Performance Optimization

### 1. Use Hash Tags for Related Data

```php
// Co-locate user data
$key = "user:{$userId}:visits:$visitId";

// Co-locate time-series data  
$key = "events:{2023-12}:$eventId";
```

### 2. Batch Operations

```php
// Create multiple models efficiently
$visits = collect($data)->map(fn($item) => new Visit($item));
Visit::insertMany($visits); // Custom method for bulk operations
```

### 3. Optimize Searchable Attributes

```php
class Visit extends Model
{
    // Order by frequency of queries (most frequent first)
    protected array $searchable = ['user_id', 'ip', 'country'];
}
```

### 4. Connection Pooling

Configure appropriate connection timeouts:

```php
'options' => [
    'timeout' => 5.0,
    'read_write_timeout' => 5.0,
    'retry_interval' => 100,
],
```

## Testing

### Local Cluster Tests

```bash
# Start local cluster
./scripts/setup-redis-cluster.sh

# Run cluster-specific tests  
REDIS_CLIENT=predis vendor/bin/pest tests/ClusterRedisRepositoryTest.php
REDIS_CLIENT=predis vendor/bin/pest tests/ModelClusterTest.php

# Run all tests with cluster
REDIS_CLIENT=predis MODEL_REPOSITORY=cluster vendor/bin/pest
```

### AWS Integration Tests

```bash
# Set AWS credentials and endpoint
export REDIS_CLUSTER_ENDPOINT="your-cluster.cache.amazonaws.com"
export REDIS_AUTH_TOKEN="your-token"
export REDIS_SSL=true

# Run tests against AWS
vendor/bin/pest tests/ClusterRedisRepositoryTest.php
```

### Performance Testing

```php
// Benchmark cluster vs single-node performance
$start = microtime(true);

// Create 1000 models
for ($i = 0; $i < 1000; $i++) {
    Visit::create(['index' => $i, 'data' => 'test']);
}

$duration = microtime(true) - $start;
echo "Created 1000 models in {$duration}s";
```

## Troubleshooting

### Common Issues

#### 1. Connection Refused
```
RedisException: Connection refused [tcp://127.0.0.1:7001]
```
**Solution**: Ensure Redis Cluster is running:
```bash
docker-compose -f docker-compose.cluster.yml ps
```

#### 2. Cluster Not Initialized
```
CLUSTERDOWN Hash slot not served
```
**Solution**: Initialize the cluster:
```bash
./scripts/setup-redis-cluster.sh
```

#### 3. Cross-slot Transaction Errors
```
CROSSSLOT Keys in request don't hash to the same slot
```
**Solution**: Use hash tags for related keys:
```php
// ❌ Different slots
$key1 = "user:1001:profile";
$key2 = "user:1001:settings";

// ✅ Same slot
$key1 = "user:{1001}:profile";  
$key2 = "user:{1001}:settings";
```

#### 4. AWS Authentication Issues
```
NOAUTH Authentication required
```
**Solution**: Check your auth token configuration:
```php
'password' => env('REDIS_AUTH_TOKEN'),
```

### Debugging Commands

```bash
# Check cluster status
redis-cli --cluster info 127.0.0.1:7001

# Monitor cluster operations
redis-cli --cluster call 127.0.0.1:7001 MONITOR

# Check key distribution
redis-cli --cluster call 127.0.0.1:7001 DBSIZE
```

### Monitoring

Set up monitoring for cluster health:

```php
// Health check endpoint
Route::get('/redis-cluster/health', function () {
    try {
        $redis = Redis::connection('activeredis_cluster');
        $redis->ping();
        return response()->json(['status' => 'healthy']);
    } catch (Exception $e) {
        return response()->json(['status' => 'unhealthy', 'error' => $e->getMessage()], 500);
    }
});
```

## Migration Guide

### From Single Redis to Cluster

1. **Update Configuration**:
   ```php
   // Before
   'connection' => 'redis'
   
   // After  
   'connection' => 'activeredis_cluster'
   ```

2. **Update Repository**:
   ```php
   // Before
   Model::setRepository('redis');
   
   // After
   Model::setRepository('cluster');
   ```

3. **Review Hash Tags**:
   - Identify related keys that need co-location
   - Implement hash tag strategy
   - Test transaction requirements

4. **Performance Testing**:
   - Benchmark query performance
   - Monitor cluster health
   - Optimize based on usage patterns

### Backward Compatibility

The cluster implementation maintains full backward compatibility:
- Existing models work without changes
- All query methods work identically  
- Events and observers function normally
- Type casting works across cluster nodes

---

## Next Steps

- Review the example configurations in `config/redis-cluster-example.php`
- Test with your local cluster using `./scripts/setup-redis-cluster.sh`
- Deploy to AWS ElastiCache for production scaling
- Monitor performance and optimize hash tag usage
- Consider implementing custom bulk operations for better performance