<?php

namespace App\Service;

use App\Repository\CardRepository;
use App\Repository\GameRepository;
use App\Repository\MonsterRepository;
use App\Repository\PlayerRepository;

class GameService
{
    private GameRepository $gameRepo;
    private PlayerRepository $playerRepo;
    private CardRepository $cardRepo;
    private MonsterRepository $monsterRepo;

    public function __construct(
        GameRepository $gameRepo,
        PlayerRepository $playerRepo,
        CardRepository $cardRepo,
        MonsterRepository $monsterRepo
    ) {
        $this->gameRepo = $gameRepo;
        $this->playerRepo = $playerRepo;
        $this->cardRepo = $cardRepo;
        $this->monsterRepo = $monsterRepo;
    }

    public function getAllGames(): array
    {
        $games = $this->gameRepo->findAll();

        foreach ($games as &$game) {
            $game = $this->attachPlayers($game);
        }

        return $games;
    }

    public function getGame(string $gameId): ?array
    {
        $game = $this->gameRepo->findById($gameId);

        if (!$game) {
            return null;
        }

        return $this->attachPlayers($game);
    }

    public function createGame(string $name): array
    {
        $gameId = uniqid('game_', true);
        $mainDeck = $this->buildMainDeck();
        $monsterDeck = $this->monsterRepo->findAll();

        shuffle($mainDeck);
        shuffle($monsterDeck);

        $players = $this->buildPlayers($gameId);

        foreach ($players as &$player) {
            $player['hand'] = array_splice($mainDeck, 0, 5);
            $player['gameId'] = $gameId;
        }

        $game = [
            'id' => $gameId,
            'name' => $name,
            'players' => $players,
            'currentTurnPlayer' => $players[0],
            'currentTurnPlayerId' => $players[0]['id'],
            'actionPoints' => 3,
            'mainDeck' => $mainDeck,
            'discardPile' => [],
            'monsterDeck' => array_slice($monsterDeck, 3),
            'activeMonsters' => array_slice($monsterDeck, 0, 3),
            'lastMessage' => 'Game created. Player 1 starts. Win with 3 slain monsters or 5 heroes in your party.'
        ];

        $this->gameRepo->save($game);
        $this->playerRepo->saveMultiple($players);

        return $game;
    }

    public function drawCard(string $gameId, string $playerId): ?array
    {
        $game = $this->getGame($gameId);

        if (!$game || !$this->canUseAction($game, $playerId, 1)) {
            return null;
        }

        $playerIndex = $this->findPlayerIndexInGame($game, $playerId);

        if ($playerIndex === null) {
            return null;
        }

        $card = $this->drawOneCard($game);

        if (!$card) {
            $game['lastMessage'] = 'The main deck is empty.';
            $this->gameRepo->save($game);
            return null;
        }

        $game['players'][$playerIndex]['hand'][] = $card;
        $game['actionPoints'] -= 1;
        $game['lastMessage'] = $game['players'][$playerIndex]['name'] . ' drew ' . $card['name'] . '.';
        $game['currentTurnPlayer'] = $game['players'][$playerIndex];

        $this->playerRepo->save($game['players'][$playerIndex]);
        $this->gameRepo->save($game);

        return $card;
    }

    public function playCard(string $gameId, string $playerId, string $cardId): array
    {
        $game = $this->getGame($gameId);

        if (!$game) {
            return ['success' => false, 'message' => 'Game not found.'];
        }

        if (!$this->canUseAction($game, $playerId, 1)) {
            return ['success' => false, 'message' => $game['lastMessage'] ?? 'Action not allowed.'];
        }

        $playerIndex = $this->findPlayerIndexInGame($game, $playerId);

        if ($playerIndex === null) {
            return ['success' => false, 'message' => 'Player not found.'];
        }

        $cardIndex = $this->findCardIndex($game['players'][$playerIndex]['hand'], $cardId);

        if ($cardIndex === null) {
            return ['success' => false, 'message' => 'Card not found in hand.'];
        }

        $card = $game['players'][$playerIndex]['hand'][$cardIndex];
        array_splice($game['players'][$playerIndex]['hand'], $cardIndex, 1);

        $message = '';

        if ($card['type'] === 'hero') {
            if ($this->countCardsByType($game['players'][$playerIndex]['party'], 'hero') >= 5) {
                $game['players'][$playerIndex]['hand'][] = $card;
                return ['success' => false, 'message' => 'You can keep maximum 5 heroes in this simplified version.'];
            }

            $game['players'][$playerIndex]['party'][] = $card;
            $message = $game['players'][$playerIndex]['name'] . ' played hero ' . $card['name'] . '.';
        } elseif ($card['type'] === 'item') {
            $game['players'][$playerIndex]['party'][] = $card;
            $message = $game['players'][$playerIndex]['name'] . ' equipped ' . $card['name'] . '. It gives +1 when attacking monsters.';
        } elseif ($card['type'] === 'modifier') {
            $game['players'][$playerIndex]['party'][] = $card;
            $bonus = $this->getCardAttackBonus($card);
            $message = $game['players'][$playerIndex]['name'] . ' prepared ' . $card['name'] . '. It gives +' . $bonus . ' when attacking monsters.';
        } elseif ($card['type'] === 'magic') {
            $game['discardPile'][] = $card;
            $message = $this->applyMagicCard($game, $game['players'][$playerIndex], $card);
        } elseif ($card['type'] === 'challenge') {
            $game['discardPile'][] = $card;
            $message = $this->applyChallengeCard($game, $playerIndex, $card);
        } else {
            $game['discardPile'][] = $card;
            $message = $game['players'][$playerIndex]['name'] . ' played ' . $card['name'] . '.';
        }

        $game['actionPoints'] -= 1;
        $game['lastMessage'] = $this->appendWinMessage($game, $game['players'][$playerIndex], $message);
        $game['currentTurnPlayer'] = $game['players'][$playerIndex];

        foreach ($game['players'] as $player) {
            $this->playerRepo->save($player);
        }

        $this->gameRepo->save($game);

        return [
            'success' => true,
            'message' => $game['lastMessage'],
            'card' => $card,
            'game' => $game
        ];
    }

    public function attackMonster(string $monsterId, string $playerId, int $roll): array
    {
        $game = $this->getGameByPlayerId($playerId);

        if (!$game) {
            return ['success' => false, 'message' => 'Game not found.'];
        }

        if (!$this->canUseAction($game, $playerId, 2)) {
            return ['success' => false, 'message' => $game['lastMessage'] ?? 'Action not allowed.'];
        }

        $playerIndex = $this->findPlayerIndexInGame($game, $playerId);
        $monsterIndex = $this->findMonsterIndex($game['activeMonsters'], $monsterId);

        if ($playerIndex === null) {
            return ['success' => false, 'message' => 'Player not found.'];
        }

        if ($monsterIndex === null) {
            return ['success' => false, 'message' => 'Monster not found.'];
        }

        if ($roll < 2 || $roll > 12) {
            return ['success' => false, 'message' => 'Roll the dice before attacking.'];
        }

        $monster = $game['activeMonsters'][$monsterIndex];
        $bonus = $this->calculateAttackBonus($game['players'][$playerIndex]);
        $finalRoll = $roll + $bonus;
        $requiredRoll = $this->getRollRequirement($monster);

        $game['actionPoints'] -= 2;

        if ($finalRoll >= $requiredRoll) {
            $game['players'][$playerIndex]['slainMonsters'][] = $monster;
            array_splice($game['activeMonsters'], $monsterIndex, 1);

            if (!empty($game['monsterDeck'])) {
                $game['activeMonsters'][] = array_shift($game['monsterDeck']);
            }

            $rewardText = $this->applyMonsterReward($game, $game['players'][$playerIndex], $monster);
            $message = $game['players'][$playerIndex]['name'] . ' rolled ' . $roll . ' +' . $bonus . ' = ' . $finalRoll . ' and slayed ' . $monster['name'] . '. ' . $rewardText;
            $game['lastMessage'] = $this->appendWinMessage($game, $game['players'][$playerIndex], $message);
        } else {
            $penaltyText = $this->applyMonsterPenalty($game, $game['players'][$playerIndex], $monster);
            $game['lastMessage'] = $game['players'][$playerIndex]['name'] . ' rolled ' . $roll . ' +' . $bonus . ' = ' . $finalRoll . '. Needed ' . $requiredRoll . '. ' . $penaltyText;
        }

        $game['currentTurnPlayer'] = $game['players'][$playerIndex];

        $this->playerRepo->save($game['players'][$playerIndex]);
        $this->gameRepo->save($game);

        return [
            'success' => $finalRoll >= $requiredRoll,
            'message' => $game['lastMessage'],
            'roll' => $roll,
            'bonus' => $bonus,
            'finalRoll' => $finalRoll,
            'requiredRoll' => $requiredRoll,
            'game' => $game
        ];
    }

    public function endTurn(string $gameId): ?array
    {
        $game = $this->getGame($gameId);

        if (!$game) {
            return null;
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

        $this->gameRepo->save($game);

        return $game;
    }

    public function getAllPlayers(): array
    {
        return $this->playerRepo->findAll();
    }

    public function getAllMonsters(): array
    {
        return $this->monsterRepo->findAll();
    }

    public function getAllCards(): array
    {
        return $this->cardRepo->findAll();
    }

    public function rollDice(string $gameId, string $playerId, string $heroId): array
    {
        $game = $this->getGame($gameId);

        if (!$game) {
            return ['success' => false, 'message' => 'Game not found.'];
        }

        if ($game['currentTurnPlayerId'] !== $playerId) {
            return ['success' => false, 'message' => 'It is not this player turn.'];
        }

        $player = $this->findPlayerInGame($game, $playerId);

        if (!$player) {
            return ['success' => false, 'message' => 'Player not found.'];
        }

        $hero = null;

        foreach ($player['party'] as $partyCard) {
            if ($partyCard['id'] === $heroId && $partyCard['type'] === 'hero') {
                $hero = $partyCard;
                break;
            }
        }

        if (!$hero) {
            return ['success' => false, 'message' => 'Hero not in party.'];
        }

        $die1 = rand(1, 6);
        $die2 = rand(1, 6);
        $total = $die1 + $die2;
        $required = $this->getRollRequirement($hero);

        return [
            'success' => $total >= $required,
            'message' => 'Rolled ' . $die1 . ' + ' . $die2 . ' = ' . $total . '. Required ' . $required . '.',
            'roll' => $total,
            'dice' => [$die1, $die2]
        ];
    }

    public function useModifier(string $gameId, string $playerId, string $modifierId, int $modifierValue): array
    {
        return $this->playCard($gameId, $playerId, $modifierId);
    }

    public function challengeCard(string $gameId, string $challengerId, string $targetPlayerId, string $cardId): array
    {
        $game = $this->getGame($gameId);

        if (!$game) {
            return ['success' => false, 'message' => 'Game not found.'];
        }

        $challengerIndex = $this->findPlayerIndexInGame($game, $challengerId);
        $targetIndex = $this->findPlayerIndexInGame($game, $targetPlayerId);

        if ($challengerIndex === null || $targetIndex === null) {
            return ['success' => false, 'message' => 'Player not found.'];
        }

        $challengeIndex = null;

        foreach ($game['players'][$challengerIndex]['hand'] as $index => $card) {
            if ($card['type'] === 'challenge') {
                $challengeIndex = $index;
                break;
            }
        }

        if ($challengeIndex === null) {
            return ['success' => false, 'message' => 'No challenge card in hand.'];
        }

        $challengeCard = $game['players'][$challengerIndex]['hand'][$challengeIndex];
        array_splice($game['players'][$challengerIndex]['hand'], $challengeIndex, 1);
        $game['discardPile'][] = $challengeCard;

        $targetCardIndex = $this->findCardIndex($game['players'][$targetIndex]['hand'], $cardId);

        if ($targetCardIndex !== null) {
            $discarded = $game['players'][$targetIndex]['hand'][$targetCardIndex];
            array_splice($game['players'][$targetIndex]['hand'], $targetCardIndex, 1);
            $game['discardPile'][] = $discarded;
            $message = $game['players'][$targetIndex]['name'] . ' discarded ' . $discarded['name'] . '.';
        } else {
            $message = $game['players'][$targetIndex]['name'] . ' had no matching card to discard.';
        }

        $game['lastMessage'] = 'Challenge used. ' . $message;

        $this->playerRepo->save($game['players'][$challengerIndex]);
        $this->playerRepo->save($game['players'][$targetIndex]);
        $this->gameRepo->save($game);

        return ['success' => true, 'message' => $game['lastMessage']];
    }

    public function discardAndDraw(string $gameId, string $playerId): array
    {
        $game = $this->getGame($gameId);

        if (!$game) {
            return ['success' => false, 'message' => 'Game not found.'];
        }

        if (!$this->canUseAction($game, $playerId, 3)) {
            return ['success' => false, 'message' => $game['lastMessage'] ?? 'Action not allowed.'];
        }

        $playerIndex = $this->findPlayerIndexInGame($game, $playerId);

        if ($playerIndex === null) {
            return ['success' => false, 'message' => 'Player not found.'];
        }

        $discardedCount = count($game['players'][$playerIndex]['hand']);

        foreach ($game['players'][$playerIndex]['hand'] as $card) {
            $game['discardPile'][] = $card;
        }

        $game['players'][$playerIndex]['hand'] = [];

        $drawnCards = [];

        for ($i = 0; $i < 5; $i++) {
            $card = $this->drawOneCard($game);

            if (!$card) {
                break;
            }

            $game['players'][$playerIndex]['hand'][] = $card;
            $drawnCards[] = $card;
        }

        $game['actionPoints'] -= 3;
        $game['lastMessage'] = $game['players'][$playerIndex]['name'] . ' discarded ' . $discardedCount . ' cards and drew ' . count($drawnCards) . ' new cards.';
        $game['currentTurnPlayer'] = $game['players'][$playerIndex];

        $this->playerRepo->save($game['players'][$playerIndex]);
        $this->gameRepo->save($game);

        return [
            'success' => true,
            'message' => $game['lastMessage'],
            'discarded' => $discardedCount,
            'drawn' => $drawnCards,
            'game' => $game
        ];
    }

    private function attachPlayers(array $game): array
    {
        $game['players'] = $this->playerRepo->findByGameId($game['id']);
        $game['currentTurnPlayer'] = $this->findPlayerInGame($game, $game['currentTurnPlayerId']);
        return $game;
    }

    private function buildMainDeck(): array
    {
        $baseCards = $this->cardRepo->findAll();
        $deck = [];

        for ($copy = 1; $copy <= 4; $copy++) {
            foreach ($baseCards as $card) {
                $newCard = $card;
                $newCard['id'] = $card['id'] . '_copy_' . $copy;
                $deck[] = $newCard;
            }
        }

        return $deck;
    }

    private function canUseAction(array &$game, string $playerId, int $requiredActionPoints): bool
    {
        if ($game['currentTurnPlayerId'] !== $playerId) {
            $game['lastMessage'] = 'It is not this player turn.';
            $this->gameRepo->save($game);
            return false;
        }

        if ($game['actionPoints'] < $requiredActionPoints) {
            $game['lastMessage'] = 'Not enough action points.';
            $this->gameRepo->save($game);
            return false;
        }

        return true;
    }

    private function drawOneCard(array &$game): ?array
    {
        if (empty($game['mainDeck']) && !empty($game['discardPile'])) {
            $game['mainDeck'] = $game['discardPile'];
            $game['discardPile'] = [];
            shuffle($game['mainDeck']);
        }

        if (empty($game['mainDeck'])) {
            return null;
        }

        return array_shift($game['mainDeck']);
    }

    private function applyMagicCard(array &$game, array &$player, array $card): string
    {
        if ($card['name'] === 'Fire Spell') {
            if (empty($game['activeMonsters'])) {
                return $player['name'] . ' used Fire Spell, but there was no active monster.';
            }

            $removedMonster = array_shift($game['activeMonsters']);

            if (!empty($game['monsterDeck'])) {
                $game['activeMonsters'][] = array_shift($game['monsterDeck']);
            } else {
                $game['activeMonsters'][] = $removedMonster;
            }

            return $player['name'] . ' used Fire Spell and replaced ' . $removedMonster['name'] . '.';
        }

        if ($card['name'] === 'Healing Spell') {
            $drawn = $this->drawOneCard($game);

            if ($drawn) {
                $player['hand'][] = $drawn;
                return $player['name'] . ' used Healing Spell and drew ' . $drawn['name'] . '.';
            }

            return $player['name'] . ' used Healing Spell, but the deck was empty.';
        }

        return $player['name'] . ' used ' . $card['name'] . '.';
    }

    private function applyChallengeCard(array &$game, int $playerIndex, array $card): string
    {
        $targetIndex = ($playerIndex + 1) % count($game['players']);
        $target = &$game['players'][$targetIndex];

        if (empty($target['hand'])) {
            return $game['players'][$playerIndex]['name'] . ' used ' . $card['name'] . ', but ' . $target['name'] . ' had no cards.';
        }

        $discarded = array_shift($target['hand']);
        $game['discardPile'][] = $discarded;

        return $game['players'][$playerIndex]['name'] . ' used ' . $card['name'] . '. ' . $target['name'] . ' discarded ' . $discarded['name'] . '.';
    }

    private function applyMonsterReward(array &$game, array &$player, array $monster): string
    {
        $reward = strtolower($monster['reward'] ?? '');
        $drawCount = 0;

        if (str_contains($reward, 'two')) {
            $drawCount = 2;
        } elseif (str_contains($reward, 'one') || str_contains($reward, 'draw')) {
            $drawCount = 1;
        }

        $drawnNames = [];

        for ($i = 0; $i < $drawCount; $i++) {
            $card = $this->drawOneCard($game);

            if (!$card) {
                break;
            }

            $player['hand'][] = $card;
            $drawnNames[] = $card['name'];
        }

        if (empty($drawnNames)) {
            return 'Reward: ' . ($monster['reward'] ?? 'none');
        }

        return 'Reward: drew ' . implode(', ', $drawnNames) . '.';
    }

    private function applyMonsterPenalty(array &$game, array &$player, array $monster): string
    {
        $penalty = strtolower($monster['penalty'] ?? '');

        if (str_contains($penalty, 'discard your hand')) {
            $count = count($player['hand']);

            foreach ($player['hand'] as $card) {
                $game['discardPile'][] = $card;
            }

            $player['hand'] = [];
            return 'Penalty: discarded the entire hand (' . $count . ' cards).';
        }

        if (str_contains($penalty, 'discard two')) {
            return $this->discardCardsFromHand($game, $player, 2);
        }

        if (str_contains($penalty, 'discard one')) {
            return $this->discardCardsFromHand($game, $player, 1);
        }

        if (str_contains($penalty, 'sacrifice')) {
            foreach ($player['party'] as $index => $card) {
                if (($card['type'] ?? '') === 'hero') {
                    $removed = $card;
                    array_splice($player['party'], $index, 1);
                    $game['discardPile'][] = $removed;
                    return 'Penalty: sacrificed ' . $removed['name'] . '.';
                }
            }

            return 'Penalty: no hero to sacrifice.';
        }

        if (str_contains($penalty, 'item')) {
            foreach ($player['party'] as $index => $card) {
                if (($card['type'] ?? '') === 'item') {
                    $removed = $card;
                    array_splice($player['party'], $index, 1);
                    $game['discardPile'][] = $removed;
                    return 'Penalty: destroyed item ' . $removed['name'] . '.';
                }
            }

            return 'Penalty: no item to destroy.';
        }

        return 'Penalty: ' . ($monster['penalty'] ?? 'none');
    }

    private function discardCardsFromHand(array &$game, array &$player, int $count): string
    {
        $discarded = [];

        for ($i = 0; $i < $count && !empty($player['hand']); $i++) {
            $card = array_shift($player['hand']);
            $game['discardPile'][] = $card;
            $discarded[] = $card['name'];
        }

        if (empty($discarded)) {
            return 'Penalty: no cards to discard.';
        }

        return 'Penalty: discarded ' . implode(', ', $discarded) . '.';
    }

    private function calculateAttackBonus(array $player): int
    {
        $bonus = 0;

        foreach ($player['party'] as $card) {
            if (($card['type'] ?? '') === 'hero') {
                $bonus += 1;
            }

            if (($card['type'] ?? '') === 'item') {
                $bonus += 1;
            }

            if (($card['type'] ?? '') === 'modifier') {
                $bonus += $this->getCardAttackBonus($card);
            }
        }

        return $bonus;
    }

    private function getCardAttackBonus(array $card): int
    {
        if (str_contains(strtolower($card['name'] ?? ''), 'plus two')) {
            return 2;
        }

        return 1;
    }

    private function appendWinMessage(array $game, array $player, string $message): string
    {
        $heroes = $this->countCardsByType($player['party'], 'hero');
        $slain = count($player['slainMonsters']);

        if ($slain >= 3) {
            return $message . ' ' . $player['name'] . ' wins with 3 slain monsters!';
        }

        if ($heroes >= 5) {
            return $message . ' ' . $player['name'] . ' wins with 5 heroes in the party!';
        }

        return $message;
    }

    private function getGameByPlayerId(string $playerId): ?array
    {
        $player = $this->playerRepo->findById($playerId);

        if (!$player || !$player['gameId']) {
            return null;
        }

        return $this->getGame($player['gameId']);
    }

    private function findPlayerInGame(array $game, string $playerId): ?array
    {
        foreach ($game['players'] as $player) {
            if ($player['id'] === $playerId) {
                return $player;
            }
        }

        return null;
    }

    private function findPlayerIndexInGame(array $game, string $playerId): ?int
    {
        foreach ($game['players'] as $index => $player) {
            if ($player['id'] === $playerId) {
                return $index;
            }
        }

        return null;
    }

    private function findCardIndex(array $cards, string $cardId): ?int
    {
        foreach ($cards as $index => $card) {
            if (($card['id'] ?? '') === $cardId) {
                return $index;
            }
        }

        return null;
    }

    private function findMonsterIndex(array $monsters, string $monsterId): ?int
    {
        foreach ($monsters as $index => $monster) {
            if (($monster['id'] ?? '') === $monsterId) {
                return $index;
            }
        }

        return null;
    }

    private function countCardsByType(array $cards, string $type): int
    {
        $count = 0;

        foreach ($cards as $card) {
            if (($card['type'] ?? '') === $type) {
                $count++;
            }
        }

        return $count;
    }

    private function getRollRequirement(array $card): int
    {
        if (isset($card['rollRequirement'])) {
            return (int) $card['rollRequirement'];
        }

        if (isset($card['roll_requirement'])) {
            return (int) $card['roll_requirement'];
        }

        return 7;
    }

    private function buildPlayers(string $gameId): array
    {
        return [
            [
                'id' => $gameId . '_p1',
                'name' => 'Player 1',
                'partyLeader' => 'Blade Leader',
                'partyLeaderClass' => 'fighter',
                'hand' => [],
                'party' => [],
                'slainMonsters' => [],
                'gameId' => $gameId,
                'role' => 'player'
            ],
            [
                'id' => $gameId . '_p2',
                'name' => 'Player 2',
                'partyLeader' => 'Shadow Leader',
                'partyLeaderClass' => 'thief',
                'hand' => [],
                'party' => [],
                'slainMonsters' => [],
                'gameId' => $gameId,
                'role' => 'player'
            ],
            [
                'id' => $gameId . '_p3',
                'name' => 'Player 3',
                'partyLeader' => 'Mystic Leader',
                'partyLeaderClass' => 'wizard',
                'hand' => [],
                'party' => [],
                'slainMonsters' => [],
                'gameId' => $gameId,
                'role' => 'player'
            ],
            [
                'id' => $gameId . '_p4',
                'name' => 'Player 4',
                'partyLeader' => 'Forest Leader',
                'partyLeaderClass' => 'ranger',
                'hand' => [],
                'party' => [],
                'slainMonsters' => [],
                'gameId' => $gameId,
                'role' => 'player'
            ]
        ];
    }
}