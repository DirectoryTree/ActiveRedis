<?php

namespace DirectoryTree\ActiveRedis\Tests\Stubs;

use DirectoryTree\ActiveRedis\Model;

class ModelStubWithEventListeners extends Model
{
    public static array $eventsCalled = [];

    public static function boot(): void
    {
        parent::boot();

        static::retrieved(function ($model) {
            static::$eventsCalled[] = 'retrieved';
        });

        static::creating(function ($model) {
            static::$eventsCalled[] = 'creating';
        });

        static::created(function ($model) {
            static::$eventsCalled[] = 'created';
        });

        static::updating(function ($model) {
            static::$eventsCalled[] = 'updating';
        });

        static::updated(function ($model) {
            static::$eventsCalled[] = 'updated';
        });

        static::saving(function ($model) {
            static::$eventsCalled[] = 'saving';
        });

        static::saved(function ($model) {
            static::$eventsCalled[] = 'saved';
        });

        static::deleting(function ($model) {
            static::$eventsCalled[] = 'deleting';
        });

        static::deleted(function ($model) {
            static::$eventsCalled[] = 'deleted';
        });
    }
}
