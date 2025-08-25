<?php

use DirectoryTree\ActiveRedis\Tests\Stubs\GameStub;
use Illuminate\Support\Facades\Redis;

beforeEach(fn () => Redis::flushall());

it('can create games with indexed repository', function () {
    $game = GameStub::create([
        'game_id' => 1001,
        'name' => 'Poker Tournament',
        'total_wager' => 1500.50,
        'match_count' => 25,
        'status' => 'active',
        'category' => 'poker',
    ]);

    expect($game->exists)->toBeTrue();
    expect($game->game_id)->toBe(1001);
    expect($game->name)->toBe('Poker Tournament');
    expect($game->total_wager)->toBe(1500.50);
    expect($game->match_count)->toBe(25);
    expect($game->status)->toBe('active');
    expect($game->category)->toBe('poker');
});

it('can find games by primary key game_id', function () {
    $game = GameStub::create([
        'game_id' => 1001,
        'name' => 'Poker Tournament',
        'total_wager' => 1500.50,
        'status' => 'active',
        'category' => 'poker',
    ]);

    $found = GameStub::find(1001);

    expect($found)->not->toBeNull();
    expect($found->is($game))->toBeTrue();
    expect($found->game_id)->toBe(1001);
    expect($found->name)->toBe('Poker Tournament');
});

it('returns null when finding non-existent game', function () {
    $game = GameStub::find(9999);

    expect($game)->toBeNull();
});

it('can query games by searchable category attribute', function () {
    GameStub::create(['game_id' => 1001, 'name' => 'Poker Game', 'category' => 'poker']);
    GameStub::create(['game_id' => 1002, 'name' => 'Blackjack Game', 'category' => 'blackjack']);
    GameStub::create(['game_id' => 1003, 'name' => 'Another Poker', 'category' => 'poker']);

    $pokerGames = GameStub::where('category', 'poker')->get();
    $blackjackGames = GameStub::where('category', 'blackjack')->get();

    expect($pokerGames->count())->toBe(2);
    expect($blackjackGames->count())->toBe(1);
    expect($pokerGames->pluck('name')->toArray())->toContain('Poker Game', 'Another Poker');
});

it('can query games by searchable status attribute', function () {
    GameStub::create(['game_id' => 1001, 'name' => 'Game 1', 'status' => 'active']);
    GameStub::create(['game_id' => 1002, 'name' => 'Game 2', 'status' => 'completed']);
    GameStub::create(['game_id' => 1003, 'name' => 'Game 3', 'status' => 'active']);

    $activeGames = GameStub::where('status', 'active')->get();
    $completedGames = GameStub::where('status', 'completed')->get();

    expect($activeGames->count())->toBe(2);
    expect($completedGames->count())->toBe(1);
    expect($completedGames->first()->name)->toBe('Game 2');
});

it('can query games by searchable game_id attribute', function () {
    GameStub::create(['game_id' => 1001, 'name' => 'Game 1001']);
    GameStub::create(['game_id' => 1002, 'name' => 'Game 1002']);

    $game = GameStub::where('game_id', 1001)->first();

    expect($game)->not->toBeNull();
    expect($game->name)->toBe('Game 1001');
    expect($game->game_id)->toBe(1001);
});

it('can update games and maintain indexes', function () {
    $game = GameStub::create([
        'game_id' => 1001,
        'name' => 'Poker Game',
        'total_wager' => 1000.00,
        'status' => 'active',
        'category' => 'poker',
    ]);

    $game->update([
        'total_wager' => 1500.00,
        'status' => 'completed',
    ]);

    expect($game->total_wager)->toBe(1500.00);
    expect($game->status)->toBe('completed');

    $activeGames = GameStub::where('status', 'active')->get();
    $completedGames = GameStub::where('status', 'completed')->get();

    expect($activeGames->count())->toBe(0);
    expect($completedGames->count())->toBe(1);
    expect($completedGames->first()->is($game))->toBeTrue();
});

it('can delete games and clean up indexes', function () {
    $game1 = GameStub::create(['game_id' => 1001, 'status' => 'active', 'category' => 'poker']);
    $game2 = GameStub::create(['game_id' => 1002, 'status' => 'active', 'category' => 'poker']);

    expect(GameStub::where('category', 'poker')->get())->toHaveCount(2);

    $game1->delete();

    expect(GameStub::where('category', 'poker')->get())->toHaveCount(1);
    expect(GameStub::find(1001))->toBeNull();
    expect(GameStub::find(1002))->not->toBeNull();
});

it('can consolidate data by category using indexed queries', function () {
    GameStub::create(['game_id' => 1001, 'total_wager' => 1500.50, 'match_count' => 25, 'category' => 'poker']);
    GameStub::create(['game_id' => 1002, 'total_wager' => 800.25, 'match_count' => 12, 'category' => 'blackjack']);
    GameStub::create(['game_id' => 1003, 'total_wager' => 3000.00, 'match_count' => 50, 'category' => 'poker']);
    GameStub::create(['game_id' => 1004, 'total_wager' => 1200.00, 'match_count' => 8, 'category' => 'blackjack']);

    $pokerGames = GameStub::where('category', 'poker')->get();
    $pokerTotalWager = $pokerGames->sum('total_wager');
    $pokerTotalMatches = $pokerGames->sum('match_count');

    expect($pokerGames->count())->toBe(2);
    expect($pokerTotalWager)->toBe(4500.50);
    expect($pokerTotalMatches)->toBe(75);

    $blackjackGames = GameStub::where('category', 'blackjack')->get();
    $blackjackTotalWager = $blackjackGames->sum('total_wager');
    $blackjackTotalMatches = $blackjackGames->sum('match_count');

    expect($blackjackGames->count())->toBe(2);
    expect($blackjackTotalWager)->toBe(2000.25);
    expect($blackjackTotalMatches)->toBe(20);
});

it('can use first or create with searchable attributes', function () {
    $game = GameStub::firstOrCreate([
        'category' => 'poker',
        'status' => 'active',
    ], [
        'game_id' => 1001,
        'name' => 'Poker Tournament',
        'total_wager' => 1500.00,
    ]);

    expect($game->game_id)->toBe(1001);
    expect($game->name)->toBe('Poker Tournament');
    expect($game->category)->toBe('poker');
    expect($game->status)->toBe('active');

    $retrieved = GameStub::firstOrCreate([
        'category' => 'poker',
        'status' => 'active',
    ], [
        'game_id' => 1002,
        'name' => 'Different Name',
        'total_wager' => 2000.00,
    ]);

    expect($retrieved->is($game))->toBeTrue();
    expect($retrieved->game_id)->toBe(1001);
    expect($retrieved->name)->toBe('Poker Tournament');
});

it('can use update or create with searchable attributes', function () {
    $game = GameStub::updateOrCreate([
        'game_id' => 1001,
    ], [
        'name' => 'Poker Tournament',
        'category' => 'poker',
        'status' => 'active',
    ]);

    expect($game->name)->toBe('Poker Tournament');
    expect($game->category)->toBe('poker');
    expect($game->game_id)->toBe(1001);

    $updated = GameStub::updateOrCreate([
        'game_id' => 1001,
    ], [
        'name' => 'Updated Poker Tournament',
        'category' => 'poker',
        'status' => 'completed',
    ]);

    expect($updated->game_id)->toBe(1001);
    expect($updated->name)->toBe('Updated Poker Tournament');
    expect($updated->status)->toBe('completed');
    expect(GameStub::get())->toHaveCount(1);

    $fresh = GameStub::find(1001);
    expect($fresh->name)->toBe('Updated Poker Tournament');
    expect($fresh->status)->toBe('completed');
});

it('demonstrates comprehensive game data consolidation workflow', function () {
    $games = [
        ['game_id' => 1001, 'name' => 'Poker Tournament', 'total_wager' => 1500.50, 'match_count' => 25, 'status' => 'active', 'category' => 'poker'],
        ['game_id' => 1002, 'name' => 'Blackjack Session', 'total_wager' => 800.25, 'match_count' => 12, 'status' => 'active', 'category' => 'blackjack'],
        ['game_id' => 1003, 'name' => 'Roulette Night', 'total_wager' => 2200.75, 'match_count' => 18, 'status' => 'completed', 'category' => 'roulette'],
        ['game_id' => 1004, 'name' => 'Slots Marathon', 'total_wager' => 500.00, 'match_count' => 100, 'status' => 'active', 'category' => 'slots'],
        ['game_id' => 1005, 'name' => 'Blackjack VIP', 'total_wager' => 1200.00, 'match_count' => 8, 'status' => 'active', 'category' => 'blackjack'],
        ['game_id' => 1006, 'name' => 'Poker Championship', 'total_wager' => 3000.00, 'match_count' => 50, 'status' => 'active', 'category' => 'poker'],
    ];

    foreach ($games as $gameData) {
        GameStub::create($gameData);
    }

    $categories = ['poker', 'blackjack', 'roulette', 'slots'];

    foreach ($categories as $category) {
        $categoryGames = GameStub::where('category', $category)->get();

        if ($categoryGames->count() > 0) {
            $totalWager = $categoryGames->sum('total_wager');
            $totalMatches = $categoryGames->sum('match_count');
            $gameCount = $categoryGames->count();

            if ($category == 'poker') {
                expect($gameCount)->toBe(2);
                expect($totalWager)->toBe(4500.50);
                expect($totalMatches)->toBe(75);
            }

            if ($category == 'blackjack') {
                expect($gameCount)->toBe(2);
                expect($totalWager)->toBe(2000.25);
                expect($totalMatches)->toBe(20);
            }
        }
    }

    $activeGames = GameStub::where('status', 'active')->get();
    $completedGames = GameStub::where('status', 'completed')->get();

    expect($activeGames->count())->toBe(5);
    expect($completedGames->count())->toBe(1);

    $game1001 = GameStub::find(1001);
    $game1001Query = GameStub::where('game_id', 1001)->first();

    expect($game1001)->not->toBeNull();
    expect($game1001Query)->not->toBeNull();
    expect($game1001->is($game1001Query))->toBeTrue();

    $gameToUpdate = GameStub::where('game_id', 1001)->first();
    $oldWager = $gameToUpdate->total_wager;

    $gameToUpdate->update([
        'total_wager' => $oldWager + 500.00,
        'match_count' => $gameToUpdate->match_count + 5,
        'status' => 'completed',
    ]);

    $newActiveGames = GameStub::where('status', 'active')->get();
    $newCompletedGames = GameStub::where('status', 'completed')->get();

    expect($newActiveGames->count())->toBe(4);
    expect($newCompletedGames->count())->toBe(2);

    $gameToDelete = GameStub::where('game_id', 1003)->first();
    expect($gameToDelete)->not->toBeNull();

    $gameToDelete->delete();

    expect(GameStub::find(1003))->toBeNull();
    expect(GameStub::where('category', 'roulette')->get())->toHaveCount(0);

    expect(GameStub::get())->toHaveCount(5);
});
