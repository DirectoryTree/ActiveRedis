<?php

namespace DirectoryTree\ActiveRedis\Tests\Fixtures;

use DirectoryTree\ActiveRedis\Model;

class ModelStubWithVisibleAttributes extends Model
{
    protected array $visible = ['visible'];
}
