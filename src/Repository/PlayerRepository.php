<?php

namespace App\Repository;

use App\Database\Database;

class PlayerRepository
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
            CREATE TABLE IF NOT EXISTS players (
                id VARCHAR(255) PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                party_leader VARCHAR(255),
                party_leader_class VARCHAR(255),
                hand TEXT,
                party TEXT,
                slain_monsters TEXT,
                game_id VARCHAR(255),
                role VARCHAR(50) DEFAULT 'player',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE
            )
        ";

        $this->db->executeStatement($sql);
    }

    public function findAll(): array
    {
        $sql = "SELECT * FROM players ORDER BY created_at DESC";
        $rows = $this->db->executeQuery($sql);

        return array_map([$this, 'hydratePlayer'], $rows);
    }

    public function findByGameId(string $gameId): array
    {
        $sql = "SELECT * FROM players WHERE game_id = ? ORDER BY id ASC";
        $rows = $this->db->executeQuery($sql, [$gameId]);

        return array_map([$this, 'hydratePlayer'], $rows);
    }

    public function findById(string $id): ?array
    {
        $sql = "SELECT * FROM players WHERE id = ?";
        $rows = $this->db->executeQuery($sql, [$id]);

        if (empty($rows)) {
            return null;
        }

        return $this->hydratePlayer($rows[0]);
    }

    public function save(array $player): void
    {
        $data = $this->dehydratePlayer($player);

        if ($this->findById($player['id'])) {
            $this->update($data);
            return;
        }

        $this->insert($data);
    }

    public function saveMultiple(array $players): void
    {
        foreach ($players as $player) {
            $this->save($player);
        }
    }

    private function insert(array $data): void
    {
        $sql = "
            INSERT INTO players (
                id,
                name,
                party_leader,
                party_leader_class,
                hand,
                party,
                slain_monsters,
                game_id,
                role
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";

        $this->db->executeStatement($sql, [
            $data['id'],
            $data['name'],
            $data['party_leader'],
            $data['party_leader_class'],
            $data['hand'],
            $data['party'],
            $data['slain_monsters'],
            $data['game_id'],
            $data['role']
        ]);
    }

    private function update(array $data): void
    {
        $sql = "
            UPDATE players
            SET
                name = ?,
                party_leader = ?,
                party_leader_class = ?,
                hand = ?,
                party = ?,
                slain_monsters = ?,
                game_id = ?,
                role = ?
            WHERE id = ?
        ";

        $this->db->executeStatement($sql, [
            $data['name'],
            $data['party_leader'],
            $data['party_leader_class'],
            $data['hand'],
            $data['party'],
            $data['slain_monsters'],
            $data['game_id'],
            $data['role'],
            $data['id']
        ]);
    }

    private function hydratePlayer(array $row): array
    {
        return [
            'id' => $row['id'],
            'name' => $row['name'],
            'partyLeader' => $row['party_leader'],
            'partyLeaderClass' => $row['party_leader_class'],
            'hand' => json_decode($row['hand'] ?? '[]', true) ?: [],
            'party' => json_decode($row['party'] ?? '[]', true) ?: [],
            'slainMonsters' => json_decode($row['slain_monsters'] ?? '[]', true) ?: [],
            'gameId' => $row['game_id'],
            'role' => $row['role']
        ];
    }

    private function dehydratePlayer(array $player): array
    {
        return [
            'id' => $player['id'],
            'name' => $player['name'],
            'party_leader' => $player['partyLeader'] ?? '',
            'party_leader_class' => $player['partyLeaderClass'] ?? '',
            'hand' => json_encode($player['hand'] ?? []),
            'party' => json_encode($player['party'] ?? []),
            'slain_monsters' => json_encode($player['slainMonsters'] ?? []),
            'game_id' => $player['gameId'] ?? null,
            'role' => $player['role'] ?? 'player'
        ];
    }
}