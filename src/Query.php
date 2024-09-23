<?php

namespace DirectoryTree\ActiveRedis;

use Closure;
use DirectoryTree\ActiveRedis\Exceptions\AttributeNotSearchableException;
use DirectoryTree\ActiveRedis\Exceptions\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

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
     * Find a model by its primary key.
     */
    public function find(string $id): ?Model
    {
        return $this->whereKey($id)->first();
    }

    /**
     * Find a model by its primary key or throw an exception.
     */
    public function findOrFail(string $id): Model
    {
        if (! $model = $this->find($id)) {
            throw (new ModelNotFoundException)->setModel(get_class($this->model), $id);
        }

        return $model;
    }

    /**
     * Create a new model.
     */
    public function create(array $attributes = []): Model
    {
        $instance = $this->model->newInstance($attributes);

        $instance->save();

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
     * Add a where clause to the query.
     */
    public function where(array|string $attribute, mixed $value = null): self
    {
        $wheres = is_string($attribute) ? [$attribute => $value] : $attribute;

        $searchable = [
            $this->model->getKeyName(),
            ...$this->model->getSearchable(),
        ];

        foreach ($wheres as $key => $value) {
            if (! in_array($key, $searchable)) {
                $model = get_class($this->model);

                throw new AttributeNotSearchableException(
                    "The attribute [{$key}] is not searchable on the model [{$model}]."
                );
            }

            $this->wheres[$key] = (string) $value;
        }

        return $this;
    }

    /**
     * Add a where key clause to the query.
     */
    public function whereKey(string $value): self
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
    public function get(): Collection
    {
        $models = $this->model->newCollection();

        $this->each($models->push(...));

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
    public function each(Closure $callback, int $size = 100): void
    {
        $this->chunk($size, $callback);
    }

    /**
     * Chunk the results of the query.
     */
    public function chunk(int $size, Closure $callback): void
    {
        foreach ($this->cache->chunk($this->getQuery(), $size) as $hash) {
            $value = $callback($this->model->newInstance([
                ...$this->cache->getAttributes($hash),
                $this->model->getKeyName() => $this->getKeyValue($hash),
            ], true));

            if ($value === false) {
                break;
            }
        }
    }

    /**
     * Get the model's primary key value from the given hash.
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
            foreach ($attributes as $field => $value) {
                $this->cache->setAttribute($hash, $field, $value);

                is_null($value)
                    ? $this->cache->deleteAttribute($hash, $field)
                    : $this->cache->setAttribute($hash, $field, $value);
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
