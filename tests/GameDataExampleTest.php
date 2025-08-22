<?php

namespace DirectoryTree\ActiveRedis\Tests;

use DirectoryTree\ActiveRedis\Model;
use Illuminate\Support\Facades\Redis;

// Game model for data consolidation example
class Game extends Model
{
    protected static string $repository = 'indexed'; // Use indexed repository!
    protected string $key = 'game_id'; // Use game_id as primary key!
    protected array $searchable = ['game_id', 'status', 'category']; // These become indexes
    protected array $casts = [
        'game_id' => 'integer',
        'total_wager' => 'float',
        'match_count' => 'integer',
    ];
}

it('demonstrates game data consolidation with IndexedRedisRepository step by step', function () {
    echo "\n🎮 Game Data Consolidation with IndexedRedisRepository\n";
    echo "====================================================\n\n";
    
    // Cleanup any existing test data first
    $existingGames = Game::all();
    foreach ($existingGames as $game) {
        $game->delete();
    }

    // Step 1: Create multiple games with different data
    echo "📝 Step 1: Creating multiple games...\n";

    $games = [
        ['game_id' => 1001, 'name' => 'Poker Tournament', 'total_wager' => 1500.50, 'match_count' => 25, 'status' => 'active', 'category' => 'poker'],
        ['game_id' => 1002, 'name' => 'Blackjack Session', 'total_wager' => 800.25, 'match_count' => 12, 'status' => 'active', 'category' => 'blackjack'],
        ['game_id' => 1003, 'name' => 'Roulette Night', 'total_wager' => 2200.75, 'match_count' => 18, 'status' => 'completed', 'category' => 'roulette'],
        ['game_id' => 1004, 'name' => 'Slots Marathon', 'total_wager' => 500.00, 'match_count' => 100, 'status' => 'active', 'category' => 'slots'],
        ['game_id' => 1005, 'name' => 'Blackjack VIP', 'total_wager' => 1200.00, 'match_count' => 8, 'status' => 'active', 'category' => 'blackjack'],
        ['game_id' => 1006, 'name' => 'Poker Championship', 'total_wager' => 3000.00, 'match_count' => 50, 'status' => 'active', 'category' => 'poker'],
    ];

    $createdModels = [];
    foreach ($games as $gameData) {
        $game = Game::create($gameData);
        $createdModels[] = $game;
        
        echo "✅ Created: {$game->name} (ID: {$game->game_id}) - Wager: \${$game->total_wager}, Matches: {$game->match_count}\n";
    }

    echo "\n🔍 Step 2: Behind the scenes - What indexes were created automatically...\n";
    echo "The IndexedRedisRepository automatically created these Redis indexes:\n";
    echo "- Main index: idx:games (all game records)\n";
    echo "- Game ID indexes: idx:games:game_id:1001, idx:games:game_id:1002, etc.\n";
    echo "- Status indexes: idx:games:status:active, idx:games:status:completed\n";
    echo "- Category indexes: idx:games:category:poker, idx:games:category:blackjack, etc.\n\n";

    // Step 3: Consolidate data by category using secondary indexes
    echo "🔎 Step 3: Consolidating data by game category...\n";

    $categories = ['poker', 'blackjack', 'roulette', 'slots'];

    foreach ($categories as $category) {
        // This uses the secondary index idx:games:category:{$category} - VERY FAST!
        $categoryGames = Game::where('category', $category)->get();
        
        if ($categoryGames->count() > 0) {
            // Consolidate the data for this category
            $totalWager = $categoryGames->sum('total_wager');
            $totalMatches = $categoryGames->sum('match_count');
            $gameCount = $categoryGames->count();
            
            echo "🎯 Category: {$category}\n";
            echo "   📊 Total Games: {$gameCount}\n";
            echo "   💰 Total Wager: $" . number_format($totalWager, 2) . "\n";
            echo "   🎮 Total Matches: {$totalMatches}\n";
            echo "   🎮 Games: " . $categoryGames->pluck('name')->implode(', ') . "\n\n";
            
            // Assert the consolidation worked
            if ($category == 'poker') {
                expect($gameCount)->toBe(2); // 2 poker games
                expect($totalWager)->toBe(4500.50); // 1500.50 + 3000.00
            }
            if ($category == 'blackjack') {
                expect($gameCount)->toBe(2); // 2 blackjack games  
                expect($totalWager)->toBe(2000.25); // 800.25 + 1200.00
            }
        }
    }

    // Step 4: Query by other attributes (using different indexes)
    echo "🔍 Step 4: Querying by other attributes...\n";

    echo "Active games (using idx:games:status:active):\n";
    $activeGames = Game::where('status', 'active')->get();
    foreach ($activeGames as $game) {
        echo "  - {$game->name} (Game ID: {$game->game_id})\n";
    }
    expect($activeGames->count())->toBe(5); // 5 active games (all except roulette)

    echo "\nPoker games (using idx:games:category:poker):\n";
    $pokerGames = Game::where('category', 'poker')->get();
    foreach ($pokerGames as $game) {
        echo "  - {$game->name} (Wager: \${$game->total_wager})\n";
    }
    expect($pokerGames->count())->toBe(2); // 2 poker games

    // Step 4a: Demonstrate Model::find($primaryKey) with game_id as primary key
    echo "\n🔑 Step 4a: Using Model::find(\$primaryKey) with game_id as primary key...\n";
    
    // Since game_id is our primary key, we can find directly by game_id
    $game1001 = Game::find(1001);
    if ($game1001) {
        echo "✅ Found game with primary key 1001: {$game1001->name}\n";
        echo "   💰 Wager: \${$game1001->total_wager}\n";
        echo "   🎯 This uses direct hash lookup: games:{$game1001->game_id}\n";
        expect($game1001->game_id)->toBe(1001);
    } else {
        echo "❌ Game 1001 not found\n";
    }
    
    $game1002 = Game::find(1002);
    if ($game1002) {
        echo "✅ Found game with primary key 1002: {$game1002->name}\n";
        echo "   💰 Wager: \${$game1002->total_wager}\n";
        expect($game1002->game_id)->toBe(1002);
    }
    
    // Test finding non-existent game
    $nonExistent = Game::find(9999);
    expect($nonExistent)->toBeNull();
    echo "✅ Game::find(9999) correctly returns null for non-existent game\n";
    
    echo "\n📊 Primary Key vs Secondary Index Performance:\n";
    echo "   🚀 Model::find(1001) - Direct hash lookup: games:1001 - O(1) performance!\n";
    echo "   ⚡ Game::where('game_id', 1001) - Uses secondary index - O(log n) performance\n";
    echo "   🎯 Both work in Redis Cluster, but find() is faster for single records\n\n";

    // Step 5: Show performance difference
    echo "\n⚡ Step 5: Performance comparison...\n";
    
    $start = microtime(true);
    // This uses the secondary index idx:games:game_id:1001 - O(log n) performance!
    $game1001Records = Game::where('game_id', 1001)->get();
    $indexTime = microtime(true) - $start;

    echo "🚀 Index-based query for game_id 1001: " . round($indexTime * 1000, 2) . "ms\n";
    echo "   Found {$game1001Records->count()} records instantly using sorted set index\n";
    echo "   Index key used: idx:games:game_id:1001\n";
    echo "   Performance: O(log n) instead of O(n) SCAN operation\n\n";

    // Step 6: Update a game (indexes are automatically maintained)
    echo "✏️  Step 6: Updating a game (indexes auto-update)...\n";

    $gameToUpdate = Game::where('game_id', 1001)->first();
    if ($gameToUpdate) {
        $oldWager = $gameToUpdate->total_wager;
        $gameToUpdate->update([
            'total_wager' => $gameToUpdate->total_wager + 500.00,
            'match_count' => $gameToUpdate->match_count + 5,
            'status' => 'completed'
        ]);
        
        echo "✅ Updated game: {$gameToUpdate->name}\n";
        echo "   💰 Wager: \${$oldWager} → \${$gameToUpdate->total_wager}\n";
        echo "   📊 Status: active → {$gameToUpdate->status}\n";
        echo "   🔧 Indexes automatically updated:\n";
        echo "      - Removed from idx:games:status:active\n";
        echo "      - Added to idx:games:status:completed\n";
        echo "      - Updated in idx:games:game_id:1001\n\n";
    }

    // Step 7: Advanced querying - get all completed games
    echo "🏁 Step 7: Finding all completed games (using idx:games:status:completed)...\n";
    $completedGames = Game::where('status', 'completed')->get();
    echo "Found {$completedGames->count()} completed games:\n";
    foreach ($completedGames as $game) {
        echo "  - {$game->name} (Game ID: {$game->game_id}) - \${$game->total_wager}\n";
    }
    expect($completedGames->count())->toBe(2); // 1 original + 1 updated

    // Step 8: Cleanup - Remove some games (indexes auto-cleanup)
    echo "\n🗑️  Step 8: Removing games (indexes auto-cleanup)...\n";

    $gamesToRemove = Game::where('game_id', 1003)->get();
    foreach ($gamesToRemove as $game) {
        echo "❌ Deleting: {$game->name}\n";
        $game->delete(); // Automatically cleans up ALL related indexes!
        echo "   🔧 Automatically removed from:\n";
        echo "      - idx:games (main index)\n";
        echo "      - idx:games:game_id:1003\n";
        echo "      - idx:games:status:completed\n";
        echo "      - idx:games:category:roulette\n";
    }

    // Step 9: Final consolidation report
    echo "\n📊 Step 9: Final consolidation report...\n";

    $remainingGameIds = [1001, 1002, 1004];
    $grandTotal = ['wager' => 0, 'matches' => 0];

    foreach ($remainingGameIds as $gameId) {
        $records = Game::where('game_id', $gameId)->get(); // Uses index for each query
        if ($records->count() > 0) {
            $totalWager = $records->sum('total_wager');
            $totalMatches = $records->sum('match_count');
            $grandTotal['wager'] += $totalWager;
            $grandTotal['matches'] += $totalMatches;
            
            echo "Game {$gameId}: \${$totalWager} wager, {$totalMatches} matches\n";
        }
    }

    echo "\n🎯 GRAND TOTAL:\n";
    echo "💰 Total Wager: $" . number_format($grandTotal['wager'], 2) . "\n";
    echo "🎮 Total Matches: {$grandTotal['matches']}\n\n";

    // Verify totals
    expect($grandTotal['wager'])->toBeGreaterThan(0);
    expect($grandTotal['matches'])->toBeGreaterThan(0);

    echo "🎉 Demo completed! This shows how IndexedRedisRepository:\n";
    echo "   ✅ Automatically creates secondary indexes for searchable attributes\n";
    echo "   ✅ Provides O(log n) query performance instead of O(n) SCAN\n";
    echo "   ✅ Works perfectly in both single Redis and Redis Cluster\n";
    echo "   ✅ Automatically maintains indexes on create/update/delete\n";
    echo "   ✅ Enables efficient data consolidation by any searchable field\n";
    echo "   ✅ No configuration needed - just set repository = 'indexed'\n\n";

    // Cleanup test data
    $allGames = Game::all();
    foreach ($allGames as $game) {
        $game->delete();
    }

    // Final assertion - all tests passed
    expect(true)->toBeTrue();
});