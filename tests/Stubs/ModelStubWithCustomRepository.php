<?php

namespace DirectoryTree\ActiveRedis\Tests\Stubs;

use DirectoryTree\ActiveRedis\Model;

class ModelStubWithCustomRepository extends Model
{
    protected static string $repository = NullRepository::class;
}
