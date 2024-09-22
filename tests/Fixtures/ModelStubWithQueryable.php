<?php

namespace DirectoryTree\ActiveRedis\Tests\Fixtures;

use DirectoryTree\ActiveRedis\Model;

class ModelStubWithQueryable extends Model
{
    protected array $queryable = ['user_id', 'company_id'];
}
