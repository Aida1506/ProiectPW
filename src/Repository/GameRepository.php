<?php

namespace App\Repository;

use App\Database\Database;

class GameRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
        $this->createTable();
    }

    private function createTable(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS games (
                id VARCHAR(255) PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                current_turn_player_id VARCHAR(255),
                action_points INT DEFAULT 3,
                main_deck TEXT,
                discard_pile TEXT,
                monster_deck TEXT,
                active_monsters TEXT,
                last_message TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ";

        $this->db->executeStatement($sql);
    }

    public function findAll(): array
    {
        $sql = "SELECT * FROM games ORDER BY created_at DESC";
        $rows = $this->db->executeQuery($sql);

        return array_map([$this, 'hydrateGame'], $rows);
    }

    public function findById(string $id): ?array
    {
        $sql = "SELECT * FROM games WHERE id = ?";
        $rows = $this->db->executeQuery($sql, [$id]);

        if (empty($rows)) {
            return null;
        }

        return $this->hydrateGame($rows[0]);
    }

    public function save(array $game): void
    {
        $data = $this->dehydrateGame($game);

        if ($this->findById($game['id'])) {
            $this->update($data);
            return;
        }

        $this->insert($data);
    }

    private function insert(array $data): void
    {
        $sql = "
            INSERT INTO games (
                id,
                name,
                current_turn_player_id,
                action_points,
                main_deck,
                discard_pile,
                monster_deck,
                active_monsters,
                last_message
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";

        $this->db->executeStatement($sql, [
            $data['id'],
            $data['name'],
            $data['current_turn_player_id'],
            $data['action_points'],
            $data['main_deck'],
            $data['discard_pile'],
            $data['monster_deck'],
            $data['active_monsters'],
            $data['last_message']
        ]);
    }

    private function update(array $data): void
    {
        $sql = "
            UPDATE games
            SET
                name = ?,
                current_turn_player_id = ?,
                action_points = ?,
                main_deck = ?,
                discard_pile = ?,
                monster_deck = ?,
                active_monsters = ?,
                last_message = ?
            WHERE id = ?
        ";

        $this->db->executeStatement($sql, [
            $data['name'],
            $data['current_turn_player_id'],
            $data['action_points'],
            $data['main_deck'],
            $data['discard_pile'],
            $data['monster_deck'],
            $data['active_monsters'],
            $data['last_message'],
            $data['id']
        ]);
    }

    private function hydrateGame(array $row): array
    {
        return [
            'id' => $row['id'],
            'name' => $row['name'],
            'players' => [],
            'currentTurnPlayer' => null,
            'currentTurnPlayerId' => $row['current_turn_player_id'],
            'actionPoints' => (int) $row['action_points'],
            'mainDeck' => json_decode($row['main_deck'] ?? '[]', true) ?: [],
            'discardPile' => json_decode($row['discard_pile'] ?? '[]', true) ?: [],
            'monsterDeck' => json_decode($row['monster_deck'] ?? '[]', true) ?: [],
            'activeMonsters' => json_decode($row['active_monsters'] ?? '[]', true) ?: [],
            'lastMessage' => $row['last_message'] ?? ''
        ];
    }

    private function dehydrateGame(array $game): array
    {
        return [
            'id' => $game['id'],
            'name' => $game['name'],
            'current_turn_player_id' => $game['currentTurnPlayerId'] ?? null,
            'action_points' => $game['actionPoints'] ?? 3,
            'main_deck' => json_encode($game['mainDeck'] ?? []),
            'discard_pile' => json_encode($game['discardPile'] ?? []),
            'monster_deck' => json_encode($game['monsterDeck'] ?? []),
            'active_monsters' => json_encode($game['activeMonsters'] ?? []),
            'last_message' => $game['lastMessage'] ?? ''
        ];
    }
}