<?php

namespace DirectoryTree\ActiveRedis;

use ArrayAccess;
use Carbon\CarbonInterface;
use DirectoryTree\ActiveRedis\Concerns\Bootable;
use DirectoryTree\ActiveRedis\Concerns\HasAttributes;
use DirectoryTree\ActiveRedis\Concerns\HasCasts;
use DirectoryTree\ActiveRedis\Concerns\HasEvents;
use DirectoryTree\ActiveRedis\Concerns\HasTimestamps;
use DirectoryTree\ActiveRedis\Exceptions\DuplicateKeyException;
use DirectoryTree\ActiveRedis\Exceptions\InvalidKeyException;
use DirectoryTree\ActiveRedis\Repositories\RedisRepository;
use DirectoryTree\ActiveRedis\Repositories\Repository;
use Illuminate\Contracts\Redis\Connection;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Redis\RedisManager;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\ForwardsCalls;

abstract class Model implements Arrayable, ArrayAccess
{
    use Bootable;
    use ForwardsCalls;
    use HasAttributes;
    use HasCasts;
    use HasEvents;
    use HasTimestamps;

    /**
     * The name of the "created at" column.
     *
     * @var string|null
     */
    public const CREATED_AT = 'created_at';

    /**
     * The name of the "updated at" column.
     *
     * @var string|null
     */
    public const UPDATED_AT = 'updated_at';

    /**
     * Indicates if the model exists.
     */
    public bool $exists = false;

    /**
     * Indicates if the model was inserted during the object's lifecycle.
     */
    public bool $wasRecentlyCreated = false;

    /**
     * The key attribute for the model.
     */
    protected string $key = 'id';

    /**
     * The prefix associated with the model.
     */
    protected ?string $prefix = null;

    /**
     * The attributes that are searchable.
     */
    protected array $searchable = [];

    /**
     * The connection name for the model.
     */
    protected ?string $connection = null;

    /**
     * Handle dynamic method calls into the model.
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->forwardCallTo($this->query(), $method, $parameters);
    }

    /**
     * Handle dynamic static method calls into the model.
     */
    public static function __callStatic(string $method, array $parameters): mixed
    {
        return (new static)->$method(...$parameters);
    }

    /**
     * Get all the models from the cache.
     */
    public static function all(): Collection
    {
        return static::query()->get();
    }

    /**
     * Create a new model instance.
     */
    public function __construct(array $attributes = [])
    {
        $this->bootIfNotBooted();

        $this->fill($attributes);
    }

    /**
     * Create a new instance of the model.
     */
    public function newInstance(array $attributes = [], bool $exists = false): static
    {
        $model = new static($attributes);

        $model->exists = $exists;

        return $model;
    }

    /**
     * Create a new model instance that is existing.
     */
    public function newFromBuilder(array $attributes = []): static
    {
        $model = $this->newInstance([], true);

        $model->setRawAttributes($attributes, true);

        $model->fireModelEvent('retrieved', false);

        return $model;
    }

    /**
     * Reload a fresh model instance from the cache.
     */
    public function fresh(): ?static
    {
        if (! $this->exists) {
            return null;
        }

        return $this->newQuery()->find(
            $this->getKey()
        );
    }

    /**
     * Reload the current model instance with fresh attributes from the cache.
     */
    public function refresh(): static
    {
        if (! $this->exists) {
            return $this;
        }

        if (! $model = $this->newQuery()->findOrFail($this->getKey())) {
            return $this;
        }

        $this->setRawAttributes($model->attributes, true);

        return $this;
    }

    /**
     * Fill the model with an array of attributes.
     */
    public function fill(array $attributes): static
    {
        ksort($attributes);

        foreach ($attributes as $key => $value) {
            $this->setAttribute($key, $value);
        }

        return $this;
    }

    /**
     * Determine if two models have the same hash key.
     */
    public function is(Model $model): bool
    {
        return $this->getHashKey() === $model->getHashKey();
    }

    /**
     * Determine if two models are not the same.
     */
    public function isNot(Model $model): bool
    {
        return ! $this->is($model);
    }

    /**
     * Set the time-to-live of the model.
     */
    public function setExpiry(CarbonInterface|int $seconds): void
    {
        if ($seconds instanceof CarbonInterface) {
            $seconds = Date::now()->diffInSeconds($seconds);
        }

        $this->newQuery()->expire($this->getHashKey(), $seconds);
    }

    /**
     * Get the time-to-live of the model.
     */
    public function getExpiry(): ?CarbonInterface
    {
        $expiry = $this->newQuery()->expiry($this->getHashKey());

        return $expiry ? Date::now()->addSeconds($expiry) : null;
    }

    /**
     * Update the model with the given attributes.
     */
    public function update(array $attributes): void
    {
        $this->fill($attributes)->save();
    }

    /**
     * Save the model.
     */
    public function save(): void
    {
        if ($this->fireModelEvent('saving') === false) {
            return;
        }

        $saved = $this->exists
            ? $this->performUpdate()
            : $this->performInsert();

        if ($saved) {
            $this->syncOriginal();

            $this->fireModelEvent('saved', false);
        }
    }

    /**
     * Perform a model insert operation.
     */
    protected function performInsert(): bool
    {
        if (is_null($this->getKey())) {
            $this->setKey($this->getNewKey());
        }

        if ($this->fireModelEvent('creating') === false) {
            return false;
        }

        if ($this->usesTimestamps()) {
            $this->updateTimestamps();
        }

        if (empty($key = trim($this->getKey()))) {
            throw new InvalidKeyException('A key is required to create a model.');
        }

        if ($this->newQuery()->find($key)) {
            throw new DuplicateKeyException("A model with the key [{$key}] already exists.");
        }

        $this->newQuery()->insertOrUpdate(
            $this->getHashKey(),
            Arr::except($this->getAttributes(), $this->getKeyName()),
        );

        $this->syncOriginal();

        $this->exists = true;

        $this->wasRecentlyCreated = true;

        $this->fireModelEvent('created', false);

        return true;
    }

    /**
     * Perform a model update operation.
     */
    protected function performUpdate(): bool
    {
        if ($this->fireModelEvent('updating') === false) {
            return false;
        }

        if (! $this->isDirty()) {
            return false;
        }

        if ($this->usesTimestamps()) {
            $this->updateTimestamps();
        }

        // If the models key has been changed, we need to delete
        // the original model from cache and create a new one,
        // containing all the models current attributes.
        if ($this->isDirty([$this->getKeyName(), ...$this->searchable])) {
            $this->newQuery()->destroy($this->getOriginalHashKey());

            $attributes = Arr::except($this->getAttributes(), $this->getKeyName());
        }
        // Otherwise, we can just update the model in cache with the
        // models dirty attributes, since the existing model will
        // already have a copy of the all original attributes.
        else {
            $attributes = Arr::except($this->getDirty(), $this->getKeyName());
        }

        $this->newQuery()->insertOrUpdate(
            $this->getHashKey(),
            $attributes
        );

        $this->syncChanges();

        $this->fireModelEvent('updated', false);

        return true;
    }

    /**
     * Destroy the models for the given IDs.
     */
    public static function destroy(array|string $ids): int
    {
        $deleted = 0;

        foreach ((array) $ids as $id) {
            if (! $model = static::find($id)) {
                continue;
            }

            $model->delete();

            $deleted++;
        }

        return $deleted;
    }

    /**
     * Delete the model from the cache.
     */
    public function delete(): void
    {
        if (! $this->exists) {
            return;
        }

        if ($this->fireModelEvent('deleting') === false) {
            return;
        }

        $this->newQuery()->destroy($this->getHashKey());

        $this->exists = false;

        $this->fireModelEvent('deleted', false);
    }

    /**
     * Begin querying the model.
     */
    public static function query(): Query
    {
        return (new static)->newQuery();
    }

    /**
     * Begin querying the model.
     */
    public function newQuery(): Query
    {
        return $this->newBuilder();
    }

    /**
     * Create a new query builder for the model's prefix.
     */
    public function newBuilder(): Query
    {
        return new Query($this, $this->newRepository());
    }

    /**
     * Create a new repository instance.
     */
    protected function newRepository(): Repository
    {
        return new RedisRepository($this->getConnection());
    }

    /**
     * Create a new Collection instance.
     */
    public function newCollection(): Collection
    {
        return new Collection;
    }

    /**
     * Get the current connection name for the model.
     */
    public function getConnectionName(): string
    {
        return $this->connection;
    }

    /**
     * Get the connection for the model.
     */
    protected function getConnection(): Connection
    {
        return static::resolveConnection($this->connection);
    }

    /**
     * Resolve a connection instance.
     */
    public static function resolveConnection(?string $connection = null): Connection
    {
        return app(RedisManager::class)->connection($connection);
    }

    /**
     * Get the model's hash key.
     */
    public function getHashKey(): string
    {
        return implode(':', array_filter([
            $this->getBaseHashWithKey($this->getKey() ?? 'null'),
            $this->getSearchableHashPath($this->getAttributes()),
        ]));
    }

    /**
     * Get the model's original hash key.
     */
    public function getOriginalHashKey(): string
    {
        return implode(':', array_filter([
            $this->getBaseHashWithKey($this->getOriginal($this->getKeyName())),
            $this->getSearchableHashPath($this->getOriginal()),
        ]));
    }

    /**
     * Get the queryable attributes for the model.
     */
    public function getSearchable(): array
    {
        return $this->searchable;
    }

    /**
     * Get the base hash key for the model.
     */
    public function getBaseHash(): string
    {
        return sprintf('%s:%s', $this->getHashPrefix(), $this->getKeyName());
    }

    /**
     * Get the hash prefix associated with the model.
     */
    public function getHashPrefix(): string
    {
        return $this->prefix ?? Str::plural(Str::snake(class_basename($this)));
    }

    /**
     * Get the base hash key for the model.
     */
    public function getBaseHashWithKey(?string $key): string
    {
        return sprintf('%s:%s', $this->getBaseHash(), $key);
    }

    /**
     * Get the hash path for the searchable attributes.
     */
    protected function getSearchableHashPath(array $attributes): ?string
    {
        if (empty($searchable = $this->getSearchable())) {
            return null;
        }

        $values = [];

        foreach ($searchable as $attribute) {
            $value = $attributes[$attribute] ?? '';

            $values[$attribute] = trim($value) ?: 'null';
        }

        ksort($values);

        $key = null;

        foreach ($values as $field => $value) {
            $key .= sprintf('%s:%s:', $field, $value);
        }

        return $key ? trim(rtrim($key, ':')) : null;
    }

    /**
     * Get the value of the model's key.
     */
    public function getKey(): ?string
    {
        return $this->getAttribute($this->getKeyName());
    }

    /**
     * Set the value of the model's key.
     */
    public function setKey(string $value): void
    {
        $this->setAttribute($this->getKeyName(), $value);
    }

    /**
     * Get the key for the model.
     */
    public function getKeyName(): string
    {
        return $this->key;
    }

    /**
     * Get a new unique key for the model.
     */
    protected function getNewKey(): string
    {
        return Str::uuid();
    }

    /**
     * Get the instance as an array.
     */
    public function toArray(): array
    {
        return $this->getAttributes();
    }

    /**
     * Dynamically retrieve attributes on the model.
     */
    public function __get(string $key): mixed
    {
        return $this->getAttribute($key);
    }

    /**
     * Dynamically set attributes on the model.
     */
    public function __set(string $key, mixed $value): void
    {
        $this->setAttribute($key, $value);
    }

    /**
     * Determine if the given attribute exists.
     */
    public function offsetExists(mixed $offset): bool
    {
        return ! is_null($this->getAttribute($offset));
    }

    /**
     * Get the value for a given offset.
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->getAttribute($offset);
    }

    /**
     * Set the value for a given offset.
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->setAttribute($offset, $value);
    }

    /**
     * Unset the value for a given offset.
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->attributes[$offset]);
    }

    /**
     * Determine if an attribute or relation exists on the model.
     */
    public function __isset(string $key): bool
    {
        return $this->offsetExists($key);
    }

    /**
     * Unset an attribute on the model.
     */
    public function __unset(string $key): void
    {
        $this->offsetUnset($key);
    }
}
