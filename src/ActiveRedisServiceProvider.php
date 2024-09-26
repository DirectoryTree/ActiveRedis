<?php

namespace DirectoryTree\ActiveRedis;

use Illuminate\Support\ServiceProvider;

class ActiveRedisServiceProvider extends ServiceProvider
{
    /**
     * Register the application services.
     */
    public function register(): void
    {
        Model::clearBootedModels();
    }

    /**
     * Bootstrap the application services.
     */
    public function boot(): void
    {
        Model::setEventDispatcher($this->app['events']);
    }
}
