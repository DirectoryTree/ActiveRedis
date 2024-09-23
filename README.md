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

> [!important] Values you assign to model attributes are always stored as strings in Redis.

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

> [!important] Do not generate keys with colons (:) or asterisks (*). They are reserved characters in Redis.

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

> [!important] Timestamp attributes will be returned as `Carbon` instances.

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

You may delete models using the `delete()` method:

```php
$visit->delete();
```

### Expiring Models

### Retrieving Models

