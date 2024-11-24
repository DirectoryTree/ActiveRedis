<?php

namespace DirectoryTree\ActiveRedis\Tests\Stubs;

use DirectoryTree\ActiveRedis\Model;

class ModelStubWithHiddenAttributes extends Model
{
    protected array $hidden = ['hidden'];
}
