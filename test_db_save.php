<?php

require 'vendor/autoload.php';

use App\Database\DatabaseConfig;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Repository\CardRepository;
use App\Repository\MonsterRepository;

try {
    $config = DatabaseConfig::getConfig();
    $db = new App\Database\Database($config);
    echo 'Database connected' . PHP_EOL;

    $gameRepo = new GameRepository($db);
    $playerRepo = new PlayerRepository($db);
    $cardRepo = new CardRepository($db);
    $monsterRepo = new MonsterRepository($db);
    echo 'Repositories created' . PHP_EOL;

    $game = [
        'id' => 'test_game_' . time(),
        'name' => 'Test Game',
        'currentTurnPlayerId' => 'p1',
        'actionPoints' => 3,
        'mainDeck' => [],
        'discardPile' => [],
        'monsterDeck' => [],
        'activeMonsters' => [],
        'lastMessage' => 'Test'
    ];

    $gameRepo->save($game);
    echo 'Game saved' . PHP_EOL;

    $player = [
        'id' => 'test_player_' . time(),
        'name' => 'Test Player',
        'partyLeader' => 'Test Leader',
        'partyLeaderClass' => 'fighter',
        'hand' => [],
        'party' => [],
        'slainMonsters' => [],
        'gameId' => $game['id'],
        'role' => 'player'
    ];

    $playerRepo->save($player);
    echo 'Player saved' . PHP_EOL;

    echo 'Test successful' . PHP_EOL;
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
    echo 'File: ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL;
}