<?php

namespace DirectoryTree\ActiveRedis\Tests\Fixtures;

use DirectoryTree\ActiveRedis\Model;

class ModelStubWithCasts extends Model
{
    protected array $casts = [
        'enum' => ModelEnumStub::class,
        'json' => 'json',
        'array' => 'array',
        'date' => 'date',
        'string' => 'string',
        'object' => 'object',
        'decimal' => 'decimal:2',
        'timestamp' => 'timestamp',
        'collection' => 'collection',
        'integer' => 'integer',
        'boolean' => 'boolean',
        'float' => 'float',
        'datetime' => 'datetime',
        'custom_datetime' => 'datetime:Y-m-d',
        'immutable_date' => 'immutable_date',
        'immutable_datetime' => 'immutable_datetime',
    ];
}
