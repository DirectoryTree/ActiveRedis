<?php

namespace DirectoryTree\ActiveRedis;

use Illuminate\Support\ServiceProvider;

class ActiveRedisServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(Repository::class, RedisRepository::class);
    }
}