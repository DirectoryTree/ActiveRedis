<?php

namespace DirectoryTree\ActiveRedis\Tests\Fixtures;

use DirectoryTree\ActiveRedis\Model;

class ModelStubWithCustomPrefix extends Model
{
    protected ?string $prefix = 'foo_bar';
}
