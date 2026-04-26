<?php

namespace App;

class GameStore
{
    private string $file;

    public function __construct()
    {
        $this->file = __DIR__ . '/../storage/games.json';
    }

    public function allGames(): array
    {
        return $this->read();
    }

    public function getGame(string $gameId): ?array
    {
        foreach ($this->read() as $game) {
            if ($game['id'] === $gameId) {
                return $game;
            }
        }

        return null;
    }

    public function createGame(string $name): array
    {
        $games = $this->read();

        $mainDeck = $this->buildMainDeck();
        shuffle($mainDeck);

        $monsterDeck = $this->buildMonsterDeck();
        shuffle($monsterDeck);

        $players = $this->buildPlayers();

        foreach ($players as &$player) {
            $player['hand'] = array_splice($mainDeck, 0, 5);
        }

        $game = [
            'id' => uniqid('game_', true),
            'name' => $name,
            'players' => $players,
            'currentTurnPlayer' => $players[0],
            'currentTurnPlayerId' => $players[0]['id'],
            'actionPoints' => 3,
            'mainDeck' => $mainDeck,
            'discardPile' => [],
            'monsterDeck' => array_slice($monsterDeck, 3),
            'activeMonsters' => array_slice($monsterDeck, 0, 3),
            'lastMessage' => 'Game created. Player 1 starts.'
        ];

        $games[] = $game;
        $this->write($games);

        return $game;
    }

    public function drawCard(string $gameId, string $playerId): ?array
    {
        $games = $this->read();

        foreach ($games as &$game) {
            if ($game['id'] !== $gameId) {
                continue;
            }

            if ($game['currentTurnPlayerId'] !== $playerId) {
                $game['lastMessage'] = 'It is not this player turn.';
                $this->write($games);
                return null;
            }

            if ($game['actionPoints'] < 1) {
                $game['lastMessage'] = 'Not enough action points.';
                $this->write($games);
                return null;
            }

            if (count($game['mainDeck']) === 0) {
                $game['lastMessage'] = 'The main deck is empty.';
                $this->write($games);
                return null;
            }

            $card = array_shift($game['mainDeck']);

            foreach ($game['players'] as &$player) {
                if ($player['id'] === $playerId) {
                    $player['hand'][] = $card;
                    break;
                }
            }

            $game['actionPoints'] -= 1;
            $game['currentTurnPlayer'] = $this->findPlayer($game, $playerId);
            $game['lastMessage'] = $card['name'] . ' was drawn.';

            $this->write($games);
            return $card;
        }

        return null;
    }

    public function attackMonster(string $monsterId, string $playerId, int $roll): array
    {
        $games = $this->read();

        foreach ($games as &$game) {
            if ($game['currentTurnPlayerId'] !== $playerId) {
                return [
                    'success' => false,
                    'message' => 'It is not this player turn.'
                ];
            }

            if ($game['actionPoints'] < 2) {
                return [
                    'success' => false,
                    'message' => 'Not enough action points.'
                ];
            }

            foreach ($game['activeMonsters'] as $monsterIndex => $monster) {
                if ($monster['id'] !== $monsterId) {
                    continue;
                }

                $game['actionPoints'] -= 2;

                if ($roll >= $monster['rollRequirement']) {
                    foreach ($game['players'] as &$player) {
                        if ($player['id'] === $playerId) {
                            $player['slainMonsters'][] = $monster;
                            break;
                        }
                    }

                    array_splice($game['activeMonsters'], $monsterIndex, 1);

                    if (count($game['monsterDeck']) > 0) {
                        $game['activeMonsters'][] = array_shift($game['monsterDeck']);
                    }

                    $game['currentTurnPlayer'] = $this->findPlayer($game, $playerId);
                    $game['lastMessage'] = 'Monster slain with roll ' . $roll . '.';
                    $this->write($games);

                    return [
                        'success' => true,
                        'message' => 'You rolled ' . $roll . ' and slayed ' . $monster['name'] . '.'
                    ];
                }

                $game['currentTurnPlayer'] = $this->findPlayer($game, $playerId);
                $game['lastMessage'] = 'Attack failed with roll ' . $roll . '.';
                $this->write($games);

                return [
                    'success' => false,
                    'message' => 'You rolled ' . $roll . '. You needed ' . $monster['rollRequirement'] . ' or higher.'
                ];
            }
        }

        return [
            'success' => false,
            'message' => 'Monster not found.'
        ];
    }

    public function endTurn(string $gameId): ?array
    {
        $games = $this->read();

        foreach ($games as &$game) {
            if ($game['id'] !== $gameId) {
                continue;
            }

            $players = $game['players'];
            $currentIndex = 0;

            foreach ($players as $index => $player) {
                if ($player['id'] === $game['currentTurnPlayerId']) {
                    $currentIndex = $index;
                    break;
                }
            }

            $nextIndex = ($currentIndex + 1) % count($players);

            $game['currentTurnPlayerId'] = $players[$nextIndex]['id'];
            $game['currentTurnPlayer'] = $players[$nextIndex];
            $game['actionPoints'] = 3;
            $game['lastMessage'] = $players[$nextIndex]['name'] . ' turn started.';

            $this->write($games);
            return $game;
        }

        return null;
    }

    public function allPlayers(): array
    {
        $games = $this->read();

        if (count($games) === 0) {
            return [];
        }

        return $games[0]['players'];
    }

    public function allCards(): array
    {
        return $this->buildMainDeck();
    }

    public function allMonsters(): array
    {
        return $this->buildMonsterDeck();
    }

    private function read(): array
    {
        if (!file_exists($this->file)) {
            return [];
        }

        $content = file_get_contents($this->file);
        $data = json_decode($content, true);

        return is_array($data) ? $data : [];
    }

    private function write(array $games): void
    {
        file_put_contents($this->file, json_encode($games, JSON_PRETTY_PRINT));
    }

    private function findPlayer(array $game, string $playerId): ?array
    {
        foreach ($game['players'] as $player) {
            if ($player['id'] === $playerId) {
                return $player;
            }
        }

        return null;
    }

    private function buildPlayers(): array
    {
        return [
            [
                'id' => 'p1',
                'name' => 'Player 1',
                'partyLeader' => 'Blade Leader',
                'partyLeaderClass' => 'fighter',
                'hand' => [],
                'party' => [],
                'slainMonsters' => []
            ],
            [
                'id' => 'p2',
                'name' => 'Player 2',
                'partyLeader' => 'Shadow Leader',
                'partyLeaderClass' => 'thief',
                'hand' => [],
                'party' => [],
                'slainMonsters' => []
            ],
            [
                'id' => 'p3',
                'name' => 'Player 3',
                'partyLeader' => 'Mystic Leader',
                'partyLeaderClass' => 'wizard',
                'hand' => [],
                'party' => [],
                'slainMonsters' => []
            ],
            [
                'id' => 'p4',
                'name' => 'Player 4',
                'partyLeader' => 'Forest Leader',
                'partyLeaderClass' => 'ranger',
                'hand' => [],
                'party' => [],
                'slainMonsters' => []
            ]
        ];
    }

    private function buildMainDeck(): array
    {
        return [
            $this->card('c1', 'Brave Fighter', 'hero', 'fighter', 'Roll 5+ to draw a card.', 5),
            $this->card('c2', 'Shield Guardian', 'hero', 'guardian', 'Roll 6+ to protect a hero.', 6),
            $this->card('c3', 'Forest Ranger', 'hero', 'ranger', 'Roll 7+ to search the deck.', 7),
            $this->card('c4', 'Sneaky Thief', 'hero', 'thief', 'Roll 8+ to steal a card.', 8),
            $this->card('c5', 'Spark Wizard', 'hero', 'wizard', 'Roll 6+ to use magic power.', 6),
            $this->card('c6', 'Lucky Bard', 'hero', 'bard', 'Roll 5+ to add a bonus.', 5),
            $this->card('c7', 'Iron Sword', 'item', 'item', '+1 to a hero roll.', null),
            $this->card('c8', 'Cursed Helmet', 'item', 'item', '-1 to an enemy hero roll.', null),
            $this->card('c9', 'Fire Spell', 'magic', 'magic', 'Destroy a weak enemy card.', null),
            $this->card('c10', 'Healing Spell', 'magic', 'magic', 'Return a card from discard.', null),
            $this->card('c11', 'Plus Two', 'modifier', 'modifier', '+2 to any roll.', null),
            $this->card('c12', 'Minus Two', 'modifier', 'modifier', '-2 to any roll.', null),
            $this->card('c13', 'Challenge', 'challenge', 'challenge', 'Stop another player from playing a card.', null),
            $this->card('c14', 'Heroic Guardian', 'hero', 'guardian', 'Roll 7+ to draw two cards.', 7),
            $this->card('c15', 'Wild Bard', 'hero', 'bard', 'Roll 8+ to play another card.', 8),
            $this->card('c16', 'Arcane Wizard', 'hero', 'wizard', 'Roll 7+ to discard an enemy item.', 7),
            $this->card('c17', 'Fast Thief', 'hero', 'thief', 'Roll 6+ to look at a hand.', 6),
            $this->card('c18', 'Heavy Fighter', 'hero', 'fighter', 'Roll 8+ to attack with bonus.', 8)
        ];
    }

    private function buildMonsterDeck(): array
    {
        return [
            $this->monster('m1', 'Dark Bat', 7, 'Sacrifice a hero.', 'Draw two cards.'),
            $this->monster('m2', 'Forest Beast', 8, 'Discard two cards.', 'Gain +1 on attacks.'),
            $this->monster('m3', 'Crystal Dragon', 9, 'Destroy one item.', 'Draw one card each turn.'),
            $this->monster('m4', 'Ancient Slime', 6, 'Discard one card.', 'You may reroll once.'),
            $this->monster('m5', 'Storm Wolf', 8, 'Sacrifice a hero.', 'Play one extra hero.'),
            $this->monster('m6', 'Shadow Giant', 10, 'Discard your hand.', 'Count as one slain monster.')
        ];
    }

    private function card(string $id, string $name, string $type, string $class, string $description, ?int $rollRequirement): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'type' => $type,
            'class' => $class,
            'description' => $description,
            'rollRequirement' => $rollRequirement
        ];
    }

    private function monster(string $id, string $name, int $rollRequirement, string $penalty, string $reward): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'rollRequirement' => $rollRequirement,
            'penalty' => $penalty,
            'reward' => $reward
        ];
    }
}