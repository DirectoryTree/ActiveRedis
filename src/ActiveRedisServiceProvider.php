<?php

namespace DirectoryTree\ActiveRedis;

use DirectoryTree\ActiveRedis\Repositories\ArrayRepository;
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
    }

    /**
     * Bootstrap the application services.
     */
    public function boot(): void
    {
        Model::setEventDispatcher($this->app['events']);
    }
}
