<?php

namespace App\Repository;

use App\Database\Database;

class MonsterRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
        $this->createTable();
        $this->seedMonsters();
    }

    private function createTable(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS monsters (
                id VARCHAR(255) PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                roll_requirement INT NOT NULL,
                penalty TEXT,
                reward TEXT
            )
        ";

        $this->db->executeStatement($sql);
    }

    private function seedMonsters(): void
    {
        $count = (int) $this->db->executeQuery("SELECT COUNT(*) as count FROM monsters")[0]['count'];

        if ($count > 0) {
            return;
        }

        foreach ($this->getDefaultMonsters() as $monster) {
            $this->db->executeStatement(
                "INSERT INTO monsters (id, name, roll_requirement, penalty, reward) VALUES (?, ?, ?, ?, ?)",
                [
                    $monster['id'],
                    $monster['name'],
                    $monster['rollRequirement'],
                    $monster['penalty'],
                    $monster['reward']
                ]
            );
        }
    }

    public function findAll(): array
    {
        $rows = $this->db->executeQuery("SELECT * FROM monsters ORDER BY id ASC");
        return array_map([$this, 'hydrateMonster'], $rows);
    }

    private function hydrateMonster(array $row): array
    {
        return [
            'id' => $row['id'],
            'name' => $row['name'],
            'rollRequirement' => (int) $row['roll_requirement'],
            'penalty' => $row['penalty'] ?? '',
            'reward' => $row['reward'] ?? ''
        ];
    }

    private function getDefaultMonsters(): array
    {
        return [
            ['id' => 'm1', 'name' => 'Dark Bat', 'rollRequirement' => 7, 'penalty' => 'Sacrifice a hero.', 'reward' => 'Draw two cards.'],
            ['id' => 'm2', 'name' => 'Forest Beast', 'rollRequirement' => 8, 'penalty' => 'Discard two cards.', 'reward' => 'Gain +1 on attacks.'],
            ['id' => 'm3', 'name' => 'Crystal Dragon', 'rollRequirement' => 9, 'penalty' => 'Destroy one item.', 'reward' => 'Draw one card.'],
            ['id' => 'm4', 'name' => 'Ancient Slime', 'rollRequirement' => 6, 'penalty' => 'Discard one card.', 'reward' => 'You may reroll once.'],
            ['id' => 'm5', 'name' => 'Storm Wolf', 'rollRequirement' => 8, 'penalty' => 'Sacrifice a hero.', 'reward' => 'Play one extra hero.'],
            ['id' => 'm6', 'name' => 'Shadow Giant', 'rollRequirement' => 10, 'penalty' => 'Discard your hand.', 'reward' => 'Count as one slain monster.']
        ];
    }
}