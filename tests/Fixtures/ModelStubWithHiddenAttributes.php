<?php

namespace DirectoryTree\ActiveRedis\Tests\Fixtures;

use DirectoryTree\ActiveRedis\Model;

class ModelStubWithHiddenAttributes extends Model
{
    protected array $hidden = ['hidden'];
}
