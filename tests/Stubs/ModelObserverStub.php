<?php

namespace DirectoryTree\ActiveRedis\Tests\Stubs;

use DirectoryTree\ActiveRedis\Model;

class ModelObserverStub
{
    public static array $eventsCalled = [];

    public function retrieved(Model $model): void
    {
        static::$eventsCalled[] = 'retrieved';
    }

    public function creating(Model $model): void
    {
        static::$eventsCalled[] = 'creating';
    }

    public function created(Model $model): void
    {
        static::$eventsCalled[] = 'created';
    }

    public function updating(Model $model): void
    {
        static::$eventsCalled[] = 'updating';
    }

    public function updated(Model $model): void
    {
        static::$eventsCalled[] = 'updated';
    }

    public function saving(Model $model): void
    {
        static::$eventsCalled[] = 'saving';
    }

    public function saved(Model $model): void
    {
        static::$eventsCalled[] = 'saved';
    }

    public function deleted(Model $model): void
    {
        static::$eventsCalled[] = 'deleted';
    }
}
