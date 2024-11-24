<?php

namespace DirectoryTree\ActiveRedis\Tests\Stubs;

use DirectoryTree\ActiveRedis\Model;

class ModelStubWithVisibleAttributes extends Model
{
    protected array $visible = ['visible'];
}
