<?php

namespace DirectoryTree\ActiveRedis\Tests\Stubs;

trait ModelTraitStub
{
    public static bool $traitInitialized = false;

    protected static function bootModelTraitStub(): void
    {
        static::$traitInitialized = true;
    }
}
