<p align="center">
<img src="https://github.com/DirectoryTree/ActiveRedis/blob/master/art/logo.svg" width="250">
</p>

<p align="center">
An Active Record implementation for Redis hashes in Laravel.
</p>

<p align="center">
<a href="https://github.com/directorytree/activeredis/actions" target="_blank"><img src="https://img.shields.io/github/actions/workflow/status/directorytree/activeredis/run-tests.yml?branch=master&style=flat-square"/></a>
<a href="https://packagist.org/packages/directorytree/activeredis" target="_blank"><img src="https://img.shields.io/packagist/v/directorytree/activeredis.svg?style=flat-square"/></a>
<a href="https://packagist.org/packages/directorytree/activeredis" target="_blank"><img src="https://img.shields.io/packagist/dt/directorytree/activeredis.svg?style=flat-square"/></a>
<a href="https://packagist.org/packages/directorytree/activeredis" target="_blank"><img src="https://img.shields.io/packagist/l/directorytree/activeredis.svg?style=flat-square"/></a>
</p>

---

ActiveRedis provides you simple and efficient way to interact with Redis hashes using an Eloquent-like API.

## Index

- [Requirements](#requirements)
- [Installation](#installation)
- [Usage](#usage)
  - [Creating Models](#creating-models)
    - [Model Identifiers](#model-identifiers)
    - [Model Timestamps](#model-timestamps)
    - [Model Casts](#model-casts)
    - [Model Events](#model-events)
    - [Model Connection](#model-connection)
  - [Updating Models](#updating-models)
  - [Deleting Models](#deleting-models)
  - [Expiring Models](#expiring-models)
  - [Querying Models](#querying-models)
    - [Chunking](#chunking)
    - [Searching](#searching)

## Requirements

- PHP >= 8.1
- Redis >= 3.0
- Laravel >= 9.0

## Installation

You can install the package via composer:

```bash
composer require directorytree/activeredis
```

## Usage

### Creating Models

To get started, define a new ActiveRedis model:

```php
namespace App\Redis;

use DirectoryTree\ActiveRedis\Model;

class Visit extends Model {}
```

Then, create models with whatever data you'd like:

> [!important]
> Without [model casts](#model-casts) defined, all values you assign to model attributes
> will be cast to strings, as that is their true storage type in Redis.

```php
use App\Redis\Visit;

$visit = Visit::create([
    'ip' => request()->ip(),
    'url' => request()->url(),
    'user_agent' => request()->userAgent(),
]);
```

This will create a new Redis hash in below format:

```
{plural_model_name}:{key_name}:{key_value}
```

For example:

```
visits:id:f195637b-7d48-43ab-abab-86e93dfc9410
```

Access attributes as you would expect on the model instance:

```php
$visit->ip; // xxx.xxx.xxx.xxx
$visit->url; // https://example.com
$visit->user_agent; // Mozilla/5.0 ...
```

> [!important]
> Before you begin using your model in production, consider possible [searchable attributes](#searching) that
> you may like to be part of your model schema, as these should not be modified once you have existing records.

#### Model Identifiers

When creating models, ActiveRedis will automatically generate a UUID for each model, assigned to the `id` attribute:

```php
$visit->id; // "f195637b-7d48-43ab-abab-86e93dfc9410"
$visit->getKey(); // "f195637b-7d48-43ab-abab-86e93dfc9410"
$visit->getHashKey(); // "visits:id:f195637b-7d48-43ab-abab-86e93dfc9410"

$visit->getKeyName(); // "id"
$visit->getBaseHash(); // "visits:id"
$visit->getHashPrefix(): // "visits"
```

You may provide your own ID if you'd prefer:

> [!important]
> Redis keys are **case-sensitive**. Be mindful of this, as it impacts queries, discussed below.

```php
$visit = Visit::create([
    'id' => 'custom-id',
    // ...
]);

$visit->id; // "custom-id"
$visit->getHashKey(); // "visits:id:custom-id"
```

Attempting to create a model with an ID that already exists will result in a `DuplicateKeyException`:

```php
Visit::create(['id' => 'custom-id']);

// DuplicateKeyException: A model with the key 'custom-id' already exists.
Visit::create(['id' => 'custom-id']);
```

Similarly, attempting to create a model with an empty ID will throw a `InvalidKeyException`:

```php
// InvalidKeyException: A key is required to create a model.
Visit::create(['id' => '']);
```

To change the name of the field in which the model key is stored, override the `key` property:

```php
namespace App\Redis;

use DirectoryTree\ActiveRedis\Model;

class Visit extends Model
{
    /**
     * The key name for the model.
     */
    protected string $key = 'custom_key';
}
```

ActiveRedis will always generate a new UUID in the key's attribute if you do not provide one.

To change this behaviour or generate your own unique keys, you may override the `getNewKey()` method:

> [!important]
> **Do not** generate keys with colons (:) or asterisks (*). They are reserved characters in Redis.
> 
> This also applies to values of [searchable](#searching) attributes.

```php
namespace App\Redis;

use Illuminate\Support\Str;
use DirectoryTree\ActiveRedis\Model;

class Visit extends Model
{    
    /**
     * Generate a new key for the model.
     */
    protected function getNewKey(): string
    {
        return Str::uuid();
    }
}
```

#### Model Timestamps

Models will also maintain `created_at` and `updated_at` attributes:

> [!important]
> Timestamp attributes will be returned as `Carbon` instances when accessed.

```php
$visit->created_at; // \Carbon\Carbon('2024-01-01 00:00:00')
$visit->updated_at; // \Carbon\Carbon('2024-01-01 00:00:00')
```

To only update a models `updated_at` timestamp, you may call the `touch()` method:

```php
$visit->touch();
```

You may provide a timestamp attribute to touch as well:

```php
$visit->touch('created_at');
```

To disable timestamps, you may override the `timestamps` property and set it to `false`:

```php
class Visit extends Model
{
    /**
     * Indicates if the model should be timestamped.
     */
    public bool $timestamps = false;
}
```

If you need to customize the names of the columns used to store the timestamps, you may define `CREATED_AT` and `UPDATED_AT` constants on your model:

```php
class Visit extends Model
{
    const CREATED_AT = 'creation_date';
    const UPDATED_AT = 'updated_date';
}
```

#### Model Casts

To cast model attributes to a specific type, you may define a `casts` property on the model:

```php
class Visit extends Model
{
    /**
     * The attributes that should be cast to native types.
     */
    protected array $casts = [
        'user_id' => 'integer',
        'authenticated' => 'boolean',
    ];
}
```

When you access the attribute, it will be cast to the specified type:

```php
$visit = new Visit([
    'user_id' => '1',
    'authenticated' => '1',
    // ...
]);

$visit->user_id; // (int) 1
$visit->authenticated; // (bool) true

$visit->getAttributes(); // ['user_id' => '1', 'authenticated' => '1'],
```

Here is a list of all supported casts:

- `json`
- `date`
- `real`
- `array`
- `float`
- `string`
- `object`
- `double`
- `integer`
- `boolean`
- `datetime`
- `timestamp`
- `collection`
- `immutable_date`
- `immutable_datetime`
- `decimal:<precision>`

Enum casts are also available:

```php
use App\Enums\VisitType;

class Visit extends Model
{
    /**
     * The attributes that should be cast to native types.
     */
    protected array $casts = [
        'type' => VisitType::class,
    ];
}
```

```php
$visit = Visit::create(['type' => VisitType::Unique]);
// Or:
$visit = Visit::create(['type' => 'unique']);

$visit->type; // (enum) VisitType::Unique
```

#### Model Events

ActiveRedis models dispatch several events, allowing you to hook into the following moments in a model's lifecycle:
`retrieved`, `creating`, `created`, `updating`, `updated`, `saving`, `saved`, `deleting`, and `deleted`.

You may register listeners for these methods inside your model's `booted` method:

> You may return `false` from an event listener on the `creating`, `updating`, or `deleting` events to cancel the operation.

```php
class Visit extends Model
{
    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::creating(function (Visit $visit) {
            // ...
        });
        
        // ...
    }
}
```

If you prefer, you may also create a model observer:

```php
class VisitObserver
{
    /**
     * Handle the "creating" event.
     */
    public function creating(Visit $visit): void
    {
        // ...
    }
}
```

And register it using the `observe` method:

```php
use App\Redis\Visit;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Visit::observe(VisitObserver::class);
    }
}
```

#### Model Connection

By default, models will use the default Redis connection defined in your Laravel configuration.

To use a different connection, you may override the `connection` property on the model:

```php
class Visit extends Model
{
    /**
     * The Redis connection to use.
     */
    protected ?string $connection = 'visits';
}
```

### Updating Models

You may update models using the `update()` method:

```php
$visit->update([
    'ip' => 'xxx.xxx.xxx.xxx',
    'url' => 'https://example.com',
    'user_agent' => 'Mozilla/5.0 ...',
]);
```

Or by setting model attributes and calling the `save()` method:

```php
$visit->ip = 'xxx.xxx.xxx.xxx';

$visit->save();
```

### Deleting Models

To delete models, use the `delete()` method on the model instance:

```php
$visit->delete();
```

Or, you may also delete models by their ID:

```php
$deleted = Visit::destroy('f195637b-7d48-43ab-abab-86e93dfc9410');

echo $deleted; // 1
```

You may delete multiple models by providing an array of IDs:

```php
$deleted = Visit::destroy(['f195637b...', 'a195637b...']);

echo $deleted; // 2
```

### Expiring Models

To expire models after a certain amount of time, use the `setExpiry()` method:

```php
$visit->setExpiry(now()->addMinutes(5));
```

After 5 minutes have elapsed, the model will be automatically deleted from Redis.

To retrieve a model's expiry time, use the `getExpiry()` method:

> [!important]
> The value returned will be a `Carbon` instance, or `null` if the model does not expire.

```php
$visit->getExpiry(); // \Carbon\Carbon|null
```

### Querying Models

Querying models uses Redis' `SCAN` command to iterate over all keys in the model's hash set.

For example, when the Visit model is queried, the pattern `visits:id:*` is used:

```
SCAN {cursor} MATCH visits:id:* COUNT {count}
```

To begin querying models, you may call the `query()` method on the model:

```php
$visits = Visit::query()->get();
```

This will iterate over all keys in the model's hash set and return a collection of models matching the pattern.

Missing model methods will be forwarded to the query builder, so you may call query methods dynamically on the model if you prefer.

```php
$visits = Visit::get();
```

#### Finding

To retrieve specific models, you may use the `find` method:

```php
$visit = Visit::find('f195637b-7d48-43ab-abab-86e93dfc9410');
```

If you would like to throw an exception when the model is not found, you may use the `findOrFail` method:

```php
Visit::findOrFail('missing'); // ModelNotFoundException
```

#### Chunking

You may chunk query results using the `chunk()` method:

> [!important]
> Redis does not guarantee the exact number of records returned in each SCAN iteration.
> See https://redis.io/docs/latest/commands/scan/#the-count-option for more information.

```php
use App\Redis\Visit;
use Illuminate\Support\Collection;

Visit::chunk(100, function (Collection $visits) {
    $visits->each(function ($visit) {
        // ...
    });
});
```

Or call the `each` method:

```php
use App\Redis\Visit;

Visit::each(function (Visit $visit) {
    // ...
}, 100);
```

You may return `false` in the callback to stop the chunking query:

```php
use App\Redis\Visit;

Visit::each(function (Visit $visit) {
    if ($visit->ip === 'xxx.xxx.xxx.xxx') {
        return false;
    }
});
```

#### Searching

Before attempting to search models, you must define which attributes you would like to be searchable on the model:

```php
namespace App\Redis;

class Visit extends Model
{
    /**
     * The attributes that are searchable.
     */
    protected array $searchable = ['ip'];
}
```

> [!important]
> Consider searchable attributes to be part of your model schema. They should be defined before 
> you begin using your model. **Do not change these while you have existing records**. Doing 
> so will lead to models that cannot be retrieved without interacting with Redis manually.

When you define these attributes, they will be stored as a part of the hash key in the below format:

```
visits:id:{id}:ip:{ip}
```

For example:

```
visits:id:f195637b-7d48-43ab-abab-86e93dfc9410:ip:127.0.0.1
```

If you do not provide a value for a searchable attribute, literal string `null` will be used as the value in the key:

```
visits:id:f195637b-7d48-43ab-abab-86e93dfc9410:ip:null
```

When multiple searchable attributes are defined, they will be stored in alphabetical order from left to right in the hash key:

```php
class Visit extends Model
{
    /**
     * The attributes that are searchable.
     */
    protected array $searchable = ['user_id', 'ip'];
}
```

For example:

```
visits:id:{id}:ip:{ip}:user_id:{user_id}
```

> [!tip]
> Because searchable attributes should not be modified while you have existing records, you may find it 
> useful name your models in a way that references the searchable attributes. For example `UserVisit`.

```php
$visit = UserVisit::create([
    'user_id' => 1,
    'ip' => request()->ip(),
]);

$visit->getHashKey(); // "user_visits:id:f195637b-7d48-43ab-abab-86e93dfc9410:ip:127.0.0.1:user_id:1"
```

Once the searchable attributes have been defined, you may begin querying for them using the `where()` method:

```php
// SCAN ... MATCH visits:id:*:ip:127.0.0.1:user_id:1
$visits = Visit::query()
    ->where('user_id', 1)
    ->where('ip', '127.0.0.1')
    ->get();
```

You may omit searchable attributes from the query and an asterisk will be inserted automatically:

```php
// SCAN ... MATCH visits:id:*:ip:127.0.0.1:user_id:*
$visit = Visit::where('ip', '127.0.0.1')->first();
```

You may also use asterisks in your where clauses to perform wildcard searches:

```php
// SCAN ... MATCH visits:id:*:ip:127.0.*:user_id:*
$visit = Visit::where('ip', '127.0.*')->first();
```

You may also use a string literal `'null'` as a search value to query for models where the attribute is `null`:

```php
// SCAN ... MATCH visits:id:*:ip:null:user_id:*
$visit = Visit::where('ip', 'null')->first();
```

Searchable attribute values may be updated at any time on models. If they have been changed, 
the existing model instance is deleted in Redis, and a new one is saved automatically:

```php
$visit = Visit::create(['user_id' => 1]);

// HDEL visits:id:f195637b-7d48-43ab-abab-86e93dfc9410:ip:127.0.0.1:user_id:1
// HSET visits:id:f195637b-7d48-43ab-abab-86e93dfc9410:ip:127.0.0.1:user_id:2
$visit->update(['user_id' => 2]);
```
