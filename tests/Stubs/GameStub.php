<?php

namespace DirectoryTree\ActiveRedis\Tests\Stubs;

use DirectoryTree\ActiveRedis\Model;

class GameStub extends Model
{
    protected static string $repository = 'indexed';

    protected string $key = 'game_id';

    protected array $searchable = ['game_id', 'status', 'category'];

    protected array $casts = [
        'game_id' => 'integer',
        'total_wager' => 'float',
        'match_count' => 'integer',
    ];
}
