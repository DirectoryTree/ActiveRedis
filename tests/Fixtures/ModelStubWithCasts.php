<?php

namespace DirectoryTree\ActiveRedis\Tests\Fixtures;

use DirectoryTree\ActiveRedis\Model;

class ModelStubWithCasts extends Model
{
    protected array $casts = [
        'date' => 'date',
        'datetime' => 'datetime',
    ];
}
