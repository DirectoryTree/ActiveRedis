<?php

namespace DirectoryTree\ActiveRedis\Tests\Stubs;

use DirectoryTree\ActiveRedis\Model;

class ModelStubWithCustomKey extends Model
{
    protected string $key = 'custom';
}
