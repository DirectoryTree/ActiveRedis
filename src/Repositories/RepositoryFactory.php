<?php

namespace DirectoryTree\ActiveRedis\Repositories;

use DirectoryTree\ActiveRedis\Model;

class RepositoryFactory
{
    /**
     * The resolved repository instances.
     */
    protected array $resolved = [];

    /**
     * Create a new repository instance.
     */
    public function make(Model $model): Repository
    {
        $repository = $model::getRepository();

        $connection = $model->getConnectionName();

        return $this->resolved[$repository][$connection ?? 'default'] ??= match ($repository) {
            'redis' => new RedisRepository($model::resolveConnection($connection)),
            'array' => new ArrayRepository,
            default => new $repository($model),
        };
    }
}
