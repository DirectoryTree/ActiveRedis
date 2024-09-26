<?php

namespace DirectoryTree\ActiveRedis\Tests\Fixtures;

trait ModelTraitStub
{
    public static bool $traitInitialized = false;

    protected static function bootModelTraitStub(): void
    {
        static::$traitInitialized = true;
    }
}
