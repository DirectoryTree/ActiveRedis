# CLAUDE.md - ActiveRedis Project Guide

## Project Overview
**ActiveRedis** is a PHP library that provides an Active Record implementation for Redis hashes in Laravel applications. It allows developers to interact with Redis using an Eloquent-like API, making Redis operations more intuitive for Laravel developers.

- **Package Name**: `directorytree/activeredis`
- **License**: MIT
- **Author**: Steve Bauman (DirectoryTree)
- **PHP Version**: >= 8.1
- **Laravel Support**: 9.0, 10.0, 11.0, 12.0

## Project Structure
```
src/
├── ActiveRedisServiceProvider.php    # Laravel service provider
├── Model.php                        # Main abstract model class
├── Query.php                        # Query builder for Redis operations
├── Concerns/                        # Traits for model functionality
│   ├── Bootable.php                # Model bootstrapping
│   ├── HasAttributes.php           # Attribute handling
│   ├── HasCasts.php                # Type casting support
│   ├── HasEvents.php               # Model events
│   ├── HasTimestamps.php           # Timestamp management
│   ├── HidesAttributes.php         # Attribute visibility
│   └── Routable.php                # URL routing support
├── Exceptions/                      # Custom exceptions
│   ├── AttributeNotSearchableException.php
│   ├── DuplicateKeyException.php
│   ├── InvalidKeyException.php
│   ├── JsonEncodingException.php
│   └── ModelNotFoundException.php
└── Repositories/                    # Data access layer
    ├── ArrayRepository.php         # Array-based repository (for testing)
    ├── ClusterRedisRepository.php  # Redis Cluster implementation ⭐ NEW
    ├── RedisRepository.php         # Redis implementation
    └── Repository.php              # Repository interface

Development Files:
├── docker-compose.cluster.yml       # Redis Cluster setup
├── scripts/setup-redis-cluster.sh   # Cluster initialization script
├── config/redis-cluster-example.php # Example cluster configurations
├── CLUSTER.md                       # Comprehensive cluster documentation
└── tests/
    ├── ClusterRedisRepositoryTest.php  # Cluster repository tests
    └── ModelClusterTest.php           # Model cluster integration tests
```

## Key Features

### 1. Eloquent-like API
- Create, read, update, delete operations similar to Eloquent
- Model relationships and attributes
- Query builder with method chaining
- Model events and observers

### 2. Redis Hash Storage
- Models stored as Redis hashes with predictable key patterns
- Format: `{plural_model_name}:{key_name}:{key_value}`
- Example: `visits:id:f195637b-7d48-43ab-abab-86e93dfc9410`

### 3. Redis Cluster Support ⭐ **NEW**
- **Multi-node scaling**: Distribute data across Redis Cluster nodes
- **AWS ElastiCache compatible**: Works with AWS ElastiCache Redis Cluster
- **Automatic cluster detection**: Seamlessly handles cluster vs single-node Redis
- **Hash tag support**: Co-locate related keys for transactions
- **High availability**: Built-in failover and replica support

### 4. Searchable Attributes
- Define searchable attributes for efficient querying
- Searchable attributes become part of the Redis key structure
- Format: `visits:id:{id}:ip:{ip}:user_id:{user_id}`
- **Important**: Searchable attributes should not be modified after production use

### 5. Type Casting
- Support for various data types: json, date, array, float, string, object, boolean, datetime, collection, enum, decimal
- Automatic type conversion when accessing attributes
- Custom cast support

### 6. Model Events
- Full event lifecycle: retrieved, creating, created, updating, updated, saving, saved, deleting, deleted
- Event listeners and observers
- Can cancel operations by returning false from event handlers

### 7. Timestamps
- Automatic `created_at` and `updated_at` timestamps
- Customizable timestamp column names
- Touch functionality for updating timestamps

### 8. Testing Support
- Array repository for testing without Redis
- Easy swap between Redis and array repositories
- Local Redis Cluster setup for development testing

## Testing

### Running Tests
```bash
# Run PHPUnit tests
vendor/bin/phpunit

# Run Pest tests
vendor/bin/pest

# Lint code
vendor/bin/pint
```

### Test Structure
- **Framework**: Pest (with PHPUnit support)
- **Test Directory**: `tests/`
- **Key Test Files**:
  - `ModelTest.php` - Core model functionality
  - `QueryTest.php` - Query builder tests
  - `ModelCastTest.php` - Type casting tests
  - `ModelEventTest.php` - Event system tests
  - `ModelSearchableTest.php` - Searchable attributes tests
  - Repository tests in `Repositories/` folder

### Testing Configuration
- Bootstrap: `vendor/autoload.php`
- Test namespace: `DirectoryTree\ActiveRedis\Tests\`
- Stubs available in `tests/Stubs/` for testing various model configurations

## Development Commands

### Linting & Code Style
```bash
# Format code with Laravel Pint
vendor/bin/pint
```

### Testing
```bash
# Run all tests
vendor/bin/phpunit

# Run with coverage
vendor/bin/phpunit --coverage-html coverage

# Run specific test
vendor/bin/phpunit tests/ModelTest.php

# Run with Predis client (for Redis cluster support)
REDIS_CLIENT=predis vendor/bin/pest

# Run cluster-specific tests (requires local cluster)
./scripts/setup-redis-cluster.sh
REDIS_CLIENT=predis vendor/bin/pest tests/ClusterRedisRepositoryTest.php
```

### Redis Cluster Development
```bash
# Start local Redis cluster
./scripts/setup-redis-cluster.sh

# Stop local Redis cluster  
docker-compose -f docker-compose.cluster.yml down

# Test cluster connectivity
docker exec redis-cluster-1 redis-cli -c -h redis-cluster-1 -p 7001 ping
```

## Common Development Patterns

### Creating a Model
```php
namespace App\Redis;

use DirectoryTree\ActiveRedis\Model;

class Visit extends Model
{
    // Define searchable attributes (part of Redis key)
    protected array $searchable = ['ip', 'user_id'];
    
    // Define type casts
    protected array $casts = [
        'user_id' => 'integer',
        'authenticated' => 'boolean',
    ];
    
    // Custom Redis connection
    protected ?string $connection = 'activeredis';
}
```

### Query Examples
```php
// Find by ID
$visit = Visit::find('some-id');

// Search by attributes
$visits = Visit::where('ip', '127.0.0.1')
               ->where('user_id', 1)
               ->get();

// Wildcard search
$visits = Visit::where('ip', '127.0.*')->get();

// Chunking for large datasets
Visit::chunk(100, function ($visits) {
    // Process chunk
});
```

### Model Operations
```php
// Create
$visit = Visit::create([
    'ip' => request()->ip(),
    'user_agent' => request()->userAgent(),
]);

// Update
$visit->update(['user_agent' => 'New Agent']);

// Delete
$visit->delete();

// Set expiry
$visit->setExpiry(now()->addMinutes(5));
```

## Important Considerations

1. **Searchable Attributes**: Once defined and in production, don't modify searchable attributes as it will break existing records
2. **Redis Key Patterns**: Avoid colons (:) and asterisks (*) in keys and searchable attribute values
3. **Connection Configuration**: Use dedicated Redis connection for ActiveRedis models for better performance
4. **Type Casting**: All Redis values are strings by default; use casts for proper type handling
5. **Testing**: Use array repository for unit tests to avoid Redis dependency

## Dependencies

### Required
- `illuminate/support`: ^9.0|^10.0|^11.0|^12.0
- `illuminate/contracts`: ^9.0|^10.0|^11.0|^12.0
- `illuminate/collections`: ^9.0|^10.0|^11.0|^12.0

### Development
- `predis/predis`: ^2.0
- `laravel/pint`: ^1.17
- `pestphp/pest`: ^1.0|^2.0|^3.0
- `orchestra/testbench`: ^7.0|^8.0|^9.0|^10.0

### Suggested
- `brick/math`: For decimal cast support

## Architecture Notes

- **Repository Pattern**: Clean separation between Redis and array implementations
- **Trait-based Design**: Modular functionality through concerns/traits
- **Event-driven**: Full model lifecycle events
- **Laravel Integration**: Native Laravel service provider and facades support
- **Type Safety**: Strong typing throughout with PHP 8.1+ features