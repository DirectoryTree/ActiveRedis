<?php

namespace DirectoryTree\ActiveRedis;

use BackedEnum;
use Closure;
use DirectoryTree\ActiveRedis\Exceptions\AttributeNotSearchableException;
use DirectoryTree\ActiveRedis\Exceptions\ModelNotFoundException;
use DirectoryTree\ActiveRedis\Repositories\Repository;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use UnitEnum;

class Query
{
    /**
     * The where constraints for the query.
     */
    protected array $wheres = [];

    /**
     * Constructor.
     */
    public function __construct(
        protected Model $model,
        protected Repository $cache,
    ) {}

    /**
     * Find a model by its key.
     */
    public function find(?string $id): ?Model
    {
        if (is_null($id)) {
            return null;
        }

        if (! empty($this->model->getSearchable())) {
            return $this->whereKey($id)->first();
        }

        if (! $this->cache->exists($hash = $this->model->getBaseHashWithKey($id))) {
            return null;
        }

        return $this->newModelFromHash($hash);
    }

    /**
     * Find a model by its key or call a callback.
     */
    public function findOr(?string $id, Closure $callback): mixed
    {
        if (! is_null($model = $this->find($id))) {
            return $model;
        }

        return $callback();
    }

    /**
     * Find a model by its key or throw an exception.
     */
    public function findOrFail(?string $id): Model
    {
        if (! $model = $this->find($id)) {
            throw (new ModelNotFoundException)->setModel(get_class($this->model), $id);
        }

        return $model;
    }

    /**
     * Create a new model.
     */
    public function create(array $attributes = [], bool $force = false): Model
    {
        $instance = $this->model->newInstance($attributes);

        $instance->save($force);

        return $instance;
    }

    /**
     * Create a new model or return the existing one.
     */
    public function firstOrCreate(array $attributes = [], array $values = []): Model
    {
        if (! is_null($instance = (clone $this)->where($attributes)->first())) {
            return $instance;
        }

        return $this->create($attributes + $values);
    }

    /**
     * Update a model or create a new one.
     */
    public function updateOrCreate(array $attributes, array $values = [], bool $force = true): Model
    {
        if (! is_null($instance = (clone $this)->where($attributes)->first())) {
            $instance->fill($values)->save($force);

            return $instance;
        }

        return $this->create($attributes + $values, $force);
    }

    /**
     * Add a where clause to the query.
     */
    public function where(array|string $attribute, mixed $value = null): self
    {
        $wheres = is_string($attribute) ? [$attribute => $value] : $attribute;

        $searchable = $this->getSearchableAttributes();

        foreach ($wheres as $key => $value) {
            if (! in_array($key, $searchable)) {
                $model = get_class($this->model);

                throw new AttributeNotSearchableException(
                    "The attribute [{$key}] is not searchable on the model [{$model}]."
                );
            }

            $this->wheres[$key] = $this->prepareWhereValue($value);
        }

        return $this;
    }

    /**
     * Get the searchable attributes for the model.
     */
    protected function getSearchableAttributes(): array
    {
        return [
            $this->model->getKeyName(),
            ...$this->model->getSearchable(),
        ];
    }

    /**
     * Prepare a value for a where clause.
     */
    protected function prepareWhereValue(mixed $value): string
    {
        if ($value instanceof UnitEnum) {
            return $value instanceof BackedEnum
                ? $value->value
                : $value->name;
        }

        return (string) $value;
    }

    /**
     * Add a where key clause to the query.
     */
    public function whereKey(?string $value): self
    {
        return $this->where($this->model->getKeyName(), $value);
    }

    /**
     * Execute the query and get the first result.
     */
    public function first(): ?Model
    {
        $instance = null;

        $this->each(function (Model $model) use (&$instance) {
            $instance = $model;

            return false;
        }, 1);

        return $instance;
    }

    /**
     * Execute the query and get the first result or throw an exception.
     */
    public function firstOrFail(): Model
    {
        if (! $model = $this->first()) {
            throw (new ModelNotFoundException)->setModel(get_class($this->model));
        }

        return $model;
    }

    /**
     * Get all the models from the cache.
     */
    public function get(int $chunk = 1000): Collection
    {
        $models = $this->model->newCollection();

        $this->each(fn (Model $model) => $models->add($model), $chunk);

        return $models;
    }

    /**
     * Determine if any models exist for the current query.
     */
    public function exists(): bool
    {
        return $this->first() !== null;
    }

    /**
     * Execute a callback over each item.
     */
    public function each(Closure $callback, int $count = 1000): void
    {
        $this->chunk($count, function (Collection $models) use ($callback) {
            foreach ($models as $key => $model) {
                if ($callback($model, $key) === false) {
                    return false;
                }
            }
        });
    }

    /**
     * Chunk the results of the query.
     */
    public function chunk(int $count, Closure $callback): void
    {
        foreach ($this->cache->chunk($this->getQuery(), $count) as $chunk) {
            $models = $this->model->newCollection();

            foreach ($chunk as $hash) {
                $models->add($this->newModelFromHash($hash));
            }

            if ($callback($models) === false) {
                return;
            }
        }
    }

    /**
     * Create a new model instance from the given hash.
     */
    protected function newModelFromHash(string $hash): Model
    {
        return $this->model->newFromBuilder([
            ...$this->cache->getAttributes($hash),
            $this->model->getKeyName() => $this->getKeyValue($hash),
        ]);
    }

    /**
     * Get the model key's value from the given hash.
     */
    protected function getKeyValue(string $hash): string
    {
        return Str::match(sprintf('/%s:([^:]+)/', $this->model->getBaseHash()), $hash);
    }

    /**
     * Get the query pattern to execute.
     */
    public function getQuery(): string
    {
        $searchable = $this->model->getSearchable();

        asort($searchable);

        $attributes = [$this->model->getKeyName(), ...$searchable];

        $pattern = '';

        foreach ($attributes as $attribute) {
            $value = $this->wheres[$attribute] ?? '*';

            $pattern .= sprintf('%s:%s:', $attribute, $value);
        }

        return sprintf('%s:%s', $this->model->getHashPrefix(), rtrim($pattern, ':'));
    }

    /**
     * Insert or update a record in the cache.
     */
    public function insertOrUpdate(string $hash, array $attributes = []): void
    {
        $this->cache->transaction(function () use ($hash, $attributes) {
            if (! empty($delete = array_keys($attributes, null, true))) {
                $this->cache->deleteAttributes($hash, $delete);
            }

            if (! empty($update = array_diff_key($attributes, array_flip($delete)))) {
                $this->cache->setAttributes($hash, $update);
            }
        });
    }

    /**
     * Set a model's time-to-live.
     */
    public function expire(string $hash, int $seconds): void
    {
        $this->cache->setExpiry($hash, $seconds);
    }

    /**
     * Get a model's time-to-live (in seconds).
     */
    public function expiry(string $hash): ?int
    {
        return $this->cache->getExpiry($hash);
    }

    /**
     * Delete a model from the cache.
     */
    public function destroy(string $hash): void
    {
        $this->cache->delete($hash);
    }
}
