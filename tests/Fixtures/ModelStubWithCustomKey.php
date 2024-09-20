<?php

namespace DirectoryTree\ActiveRedis\Tests\Fixtures;

use DirectoryTree\ActiveRedis\Model;

class ModelStubWithCustomKey extends Model
{
    protected string $primaryKey = 'custom';
}