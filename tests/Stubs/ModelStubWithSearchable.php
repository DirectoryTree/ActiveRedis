<?php

namespace DirectoryTree\ActiveRedis\Tests\Stubs;

use DirectoryTree\ActiveRedis\Model;

class ModelStubWithSearchable extends Model
{
    protected array $searchable = ['user_id', 'company_id'];
}
