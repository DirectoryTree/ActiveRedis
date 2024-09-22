<?php

namespace DirectoryTree\ActiveRedis;

use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\ForwardsCalls;
use RuntimeException;

abstract class Model
{
    use ForwardsCalls;
    use HasAttributes;
    use HasTimestamps;

    /**
     * The name of the "created at" column.
     *
     * @var string|null
     */
    const CREATED_AT = 'created_at';

    /**
     * The name of the "updated at" column.
     *
     * @var string|null
     */
    const UPDATED_AT = 'updated_at';

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
     * The "type" of key for the model.
     */
    protected string $keyType = 'uuid';

    /**
     * The prefix associated with the model.
     */
    protected ?string $prefix = null;

    /**
     * The attributes that are queryable.
     */
    protected array $queryable = [];

    /**
     * The storage format of the model's date columns.
     */
    protected string $dateFormat = 'Y-m-d H:i:s';

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
     * Create a new model instance.
     */
    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    /**
     * Create a new instance of the given model.
     */
    public function newInstance(array $attributes = [], bool $exists = false): static
    {
        $model = new static($attributes);

        $model->exists = $exists;

        if ($exists) {
            $model->syncOriginal();
        }

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

        return $this->newQuery()->find($this->getKey());
    }

    /**
     * Reload the current model instance with fresh attributes from the cache.
     */
    public function refresh(): static
    {
        if (! $this->exists) {
            return $this;
        }

        $this->fill(
            $this->newQuery()
                ->find($this->getKey())
                ->getAttributes()
        );

        $this->syncOriginal();

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
     * Determine if the model is equal to the given model.
     */
    public function is(Model $model): bool
    {
        return $this->getHashKey() === $model->getHashKey();
    }

    /**
     * Set the time-to-live of the model.
     */
    public function setExpiry(CarbonInterface|int $seconds): void
    {
        if ($seconds instanceof CarbonInterface) {
            $seconds = now()->diffInSeconds($seconds);
        }

        $this->newQuery()->expire($this->getHashKey(), $seconds);
    }

    /**
     * Get the time-to-live of the model.
     */
    public function getExpiry(): ?CarbonInterface
    {
        $expiry = $this->newQuery()->expiry($this->getHashKey());

        return $expiry ? now()->addSeconds($expiry) : null;
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
        $this->exists
            ? $this->performUpdate()
            : $this->performInsert();

        $this->syncOriginal();
    }

    /**
     * Perform a model update operation.
     */
    protected function performUpdate(): void
    {
        if (! $this->isDirty()) {
            return;
        }

        if ($this->usesTimestamps()) {
            $this->updateTimestamps();
        }

        // If the models key has been changed, we need to delete
        // the original model from cache and create a new one,
        // containing all the models current attributes.
        if ($this->isDirty([$this->getKeyName(), ...$this->queryable])) {
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
    }

    /**
     * Perform a model insert operation.
     */
    protected function performInsert(): void
    {
        if ($this->usesTimestamps()) {
            $this->updateTimestamps();
        }

        if (is_null($this->getKey())) {
            $this->setKey($this->getNewKey());
        }

        if (is_null($key = $this->getKey())) {
            throw new KeyMissingException('A key is required to insert a new model.');
        }

        if (str_contains($key, ':')) {
            throw new InvalidKeyException('A model key cannot contain a colon.');
        }

        if ($this->newQuery()->find($key)) {
            throw new DuplicateKeyException("A model with the key [{$key}] already exists.");
        }

        $this->newQuery()->insertOrUpdate(
            $this->getHashKey(),
            Arr::except($this->getAttributes(), $this->getKeyName()),
        );

        $this->wasRecentlyCreated = true;

        $this->syncOriginal();

        $this->exists = true;
    }

    /**
     * Delete the model from the cache.
     */
    public function delete(): void
    {
        if (! $this->exists) {
            return;
        }

        $this->newQuery()->destroy($this->getHashKey());

        $this->exists = false;
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
     * Create a new query builder for the model's table.
     */
    public function newBuilder(): Query
    {
        return new Query($this, app(Repository::class));
    }

    /**
     * Get the model's hash key.
     */
    public function getHashKey(): string
    {
        return implode(':', array_filter([
            $this->getBaseHashWithKey($this->getKey() ?? 'null'),
            $this->getQueryableHashPath($this->getAttributes()),
        ]));
    }

    /**
     * Get the model's original hash key.
     */
    public function getOriginalHashKey(): string
    {
        return implode(':', array_filter([
            $this->getBaseHashWithKey($this->getOriginal($this->getKeyName())),
            $this->getQueryableHashPath($this->getOriginal()),
        ]));
    }

    /**
     * Get the queryable attributes for the model.
     */
    public function getQueryable(): array
    {
        return $this->queryable;
    }

    /**
     * Get the base hash key for the model.
     */
    public function getBaseHash(): string
    {
        return sprintf('%s:%s', $this->getHashPrefix(), $this->getKeyName());
    }

    /**
     * Get the table associated with the model.
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
     * Get the hash path for the queryable attributes.
     */
    protected function getQueryableHashPath(array $attributes): ?string
    {
        if (empty($queryable = $this->getQueryable())) {
            return null;
        }

        $values = [];

        foreach ($queryable as $attribute) {
            $values[$attribute] = $attributes[$attribute] ?? 'null';
        }

        ksort($values);

        $key = null;

        foreach ($values as $field => $value) {
            $key .= sprintf('%s:%s:', $field, $value);
        }

        return $key ? trim(rtrim($key, ':')) : null;
    }

    /**
     * Get the value of the model's primary key.
     */
    public function getKey(): ?string
    {
        return $this->getAttribute($this->getKeyName());
    }

    /**
     * Set the value of the model's primary key.
     */
    public function setKey(string $value): void
    {
        $this->setAttribute($this->getKeyName(), $value);
    }

    /**
     * Get the primary key for the model.
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
        return match ($this->keyType) {
            'uuid' => $this->getNewUuid(),
            default => throw new RuntimeException(
                "Model key type [{$this->keyType}] cannot be generated automatically."
                ." It must be provided in the model's attributes."
            ),
        };
    }

    /**
     * Get a new UUID for the model.
     */
    protected function getNewUuid(): string
    {
        return Str::orderedUuid();
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
    public function offsetSet(string $offset, mixed $value): void
    {
        $this->setAttribute($offset, $value);
    }

    /**
     * Unset the value for a given offset.
     */
    public function offsetUnset(string $offset): void
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
