<?php

namespace DirectoryTree\ActiveRedis\Concerns;

use DirectoryTree\ActiveRedis\Model;
use DirectoryTree\ActiveRedis\Query;

/** @mixin \DirectoryTree\ActiveRedis\Model */
trait Routable
{
    /**
     * Get the value of the model's route key.
     */
    public function getRouteKey(): mixed
    {
        return $this->getAttribute($this->getRouteKeyName());
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return $this->getKeyName();
    }

    /**
     * Retrieve the model for a bound value.
     */
    public function resolveRouteBinding(mixed $value, mixed $field = null): ?Model
    {
        return $this->resolveRouteBindingQuery($this->newQuery(), $value, $field)->first();
    }

    /**
     * Retrieve the child model for a bound value.
     */
    public function resolveChildRouteBinding(mixed $childType, mixed $value, mixed $field): ?Model
    {
        return null; // Not supported.
    }

    /**
     * Retrieve the model for a bound value.
     */
    public function resolveRouteBindingQuery(mixed $query, mixed $value, mixed $field = null): Query
    {
        return $query->where($field ?? $this->getRouteKeyName(), $value);
    }
}
