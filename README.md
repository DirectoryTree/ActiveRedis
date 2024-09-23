<p align="center">
<img src="https://github.com/DirectoryTree/ActiveRedis/blob/master/art/logo.svg" width="250">
</p>

<p align="center">
An Active Record implementation for Redis in Laravel using hashes.
</p>

<p align="center">
<a href="https://github.com/directorytree/activeredis/actions" target="_blank"><img src="https://img.shields.io/github/actions/workflow/status/directorytree/activeredis/run-tests.yml?branch=master&style=flat-square"/></a>
<a href="https://packagist.org/packages/directorytree/activeredis" target="_blank"><img src="https://img.shields.io/packagist/v/directorytree/activeredis.svg?style=flat-square"/></a>
<a href="https://packagist.org/packages/directorytree/activeredis" target="_blank"><img src="https://img.shields.io/packagist/dt/directorytree/activeredis.svg?style=flat-square"/></a>
<a href="https://packagist.org/packages/directorytree/activeredis" target="_blank"><img src="https://img.shields.io/packagist/l/directorytree/activeredis.svg?style=flat-square"/></a>
</p>

---

ActiveRedis uses Redis hashes to store and retrieve model data, providing a simple and efficient way to interact with Redis in Laravel.

## Index

- [Requirements](#requirements)
- [Installation](#installation)
- [Usage](#usage)
  - [Creating Models](#creating-models)
    - [Model Identifiers](#model-identifiers)
    - [Model Timestamps](#model-timestamps)
  - [Updating Models](#updating-models)
  - [Deleting Models](#deleting-models)
  - [Expiring Models](#expiring-models)
  - [Querying Models](#querying-models)
    - [Chunking](#chunking)
    - [Filtering](#filtering)
  - [Retrieving Models](#retrieving-models)

## Requirements

- PHP >= 8.1
- Predis >= 2.0
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
> Values you assign to model attributes are always stored as strings in Redis.

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
{plural_model_name}:{key_attribute}:{key_value}
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

#### Model Identifiers

When creating models, ActiveRedis will automatically generate a UUID for each model, assigned to the `id` attribute:

```php
$visit->id; // "f195637b-7d48-43ab-abab-86e93dfc9410"
$visit->getKey(); // "f195637b-7d48-43ab-abab-86e93dfc9410"
$visit->getHashKey(); // "visits:id:f195637b-7d48-43ab-abab-86e93dfc9410"

$visit->getKeyName(): // "id"
$visit->getBaseHash(); // "visits:id"
$visit->getHashPrefix(): // "visits"
```

You may provide your own ID if you'd prefer:

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
    protected string $key = 'custom_key';
}
```

ActiveRedis will always generate a new UUID in the key's attribute if you do not provide one.

To change this behaviour or generate your own unique keys, you may override the `getNewKey()` method:

> [!important]
> Do not generate keys with colons (:) or asterisks (*). They are reserved characters in Redis.

```php
namespace App\Redis;

use Illuminate\Support\Str;
use DirectoryTree\ActiveRedis\Model;

class Visit extends Model
{
    // ...
    
    protected function getNewKey(): string
    {
        return Str::orderedUuid();
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

#### Chunking

You may chunk query results using the `chunk()` method:

```php
Visit::chunk(100, function ($visits) {
    $visits->each(function ($visit) {
        // ...
    });
});
```

Or call the `each` method:

```php
Visit::each(100, function ($visit) {
    // ...
});
```

You may return `false` in the callback to stop the chunking query:

```php
Visit::each(function ($visit) {
    if ($visit->ip === 'xxx.xxx.xxx.xxx') {
        return false;
    }
});
```

#### Filtering

Before attempting to filter models, you must define which attributes you would like to be queryable on the model:

```php
class Visit extends Model
{
    protected array $queryable = ['ip'];
}
```

When you define these attributes, they will be stored as a part of the hash key in the below format:

```
visits:id:{id}:ip:{ip}
```

For example:

```
visits:id:f195637b-7d48-43ab-abab-86e93dfc9410:ip:127.0.0.1
```

When multiple queryable attributes are defined, they will be stored in alphabetical order. For example:

```php
class Metric extends Model
{
    protected array $queryable = ['user_id', 'company_id'];
}

// ...

$metric = Metric::create([
    'user_id' => 1,
    'company_id' => 1,
]);

$metric->getHashKey(); // "metrics:id:{uuid}:company_id:1:user_id:1"
```

Once the queryable attributes have been defined, you may begin querying for them using the `where()` method:

```php
// SCAN ... MATCH visits:id:*:ip:127.0.0.1
$visit = Visit::where('ip', '127.0.0.1')->first();
```

You may also use asterisks in your where clauses to perform wildcard searches:

```php
// SCAN ... MATCH visits:id:*:ip:127.0.*
$visit = Visit::where('ip', '127.0.*')->first();
```

### Retrieving Models

To retrieve models, you may use the `find` method:

```php
$visit = Visit::find('f195637b-7d48-43ab-abab-86e93dfc9410');
```

If you would like to throw an exception when the model is not found, you may use the `findOrFail` method:

```php
Visit::findOrFail('missing'); // ModelNotFoundException
```
