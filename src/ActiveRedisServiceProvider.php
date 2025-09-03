<?php

namespace DirectoryTree\ActiveRedis;

use DirectoryTree\ActiveRedis\Repositories\ArrayRepository;
use DirectoryTree\ActiveRedis\Repositories\RepositoryFactory;
use Illuminate\Support\ServiceProvider;

class ActiveRedisServiceProvider extends ServiceProvider
{
    /**
     * Register the application services.
     */
    public function register(): void
    {
        Model::clearBootedModels();

        $this->app->singleton(ArrayRepository::class);
        $this->app->singleton(RepositoryFactory::class);
    }

    /**
     * Bootstrap the application services.
     */
    public function boot(): void
    {
        Model::setEventDispatcher($this->app['events']);
    }
}
