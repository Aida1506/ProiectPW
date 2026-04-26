<?php

namespace App\Repository;

use App\Database\Database;

class CardRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
        $this->createTable();
        $this->seedCards();
    }

    private function createTable(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS cards (
                id VARCHAR(255) PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                type VARCHAR(50) NOT NULL,
                class VARCHAR(50),
                description TEXT,
                roll_requirement INT
            )
        ";

        $this->db->executeStatement($sql);
    }

    private function seedCards(): void
    {
        $count = (int) $this->db->executeQuery("SELECT COUNT(*) as count FROM cards")[0]['count'];

        if ($count > 0) {
            return;
        }

        foreach ($this->getDefaultCards() as $card) {
            $this->db->executeStatement(
                "INSERT INTO cards (id, name, type, class, description, roll_requirement) VALUES (?, ?, ?, ?, ?, ?)",
                [
                    $card['id'],
                    $card['name'],
                    $card['type'],
                    $card['class'],
                    $card['description'],
                    $card['rollRequirement']
                ]
            );
        }
    }

    public function findAll(): array
    {
        $rows = $this->db->executeQuery("SELECT * FROM cards ORDER BY id ASC");
        return array_map([$this, 'hydrateCard'], $rows);
    }

    public function findByType(string $type): array
    {
        $rows = $this->db->executeQuery("SELECT * FROM cards WHERE type = ? ORDER BY id ASC", [$type]);
        return array_map([$this, 'hydrateCard'], $rows);
    }

    private function hydrateCard(array $row): array
    {
        return [
            'id' => $row['id'],
            'name' => $row['name'],
            'type' => $row['type'],
            'class' => $row['class'] ?? '',
            'description' => $row['description'] ?? '',
            'rollRequirement' => isset($row['roll_requirement']) ? (int) $row['roll_requirement'] : null
        ];
    }

    private function getDefaultCards(): array
    {
        return [
            ['id' => 'c1', 'name' => 'Brave Fighter', 'type' => 'hero', 'class' => 'fighter', 'description' => 'Roll 5+ to draw a card.', 'rollRequirement' => 5],
            ['id' => 'c2', 'name' => 'Shield Guardian', 'type' => 'hero', 'class' => 'guardian', 'description' => 'Roll 6+ to protect a hero.', 'rollRequirement' => 6],
            ['id' => 'c3', 'name' => 'Forest Ranger', 'type' => 'hero', 'class' => 'ranger', 'description' => 'Roll 7+ to search the deck.', 'rollRequirement' => 7],
            ['id' => 'c4', 'name' => 'Sneaky Thief', 'type' => 'hero', 'class' => 'thief', 'description' => 'Roll 8+ to steal a card.', 'rollRequirement' => 8],
            ['id' => 'c5', 'name' => 'Spark Wizard', 'type' => 'hero', 'class' => 'wizard', 'description' => 'Roll 6+ to use magic power.', 'rollRequirement' => 6],
            ['id' => 'c6', 'name' => 'Lucky Bard', 'type' => 'hero', 'class' => 'bard', 'description' => 'Roll 5+ to add a bonus.', 'rollRequirement' => 5],
            ['id' => 'c7', 'name' => 'Iron Sword', 'type' => 'item', 'class' => 'item', 'description' => '+1 bonus when attacking monsters.', 'rollRequirement' => null],
            ['id' => 'c8', 'name' => 'Cursed Helmet', 'type' => 'item', 'class' => 'item', 'description' => '+1 bonus when attacking monsters.', 'rollRequirement' => null],
            ['id' => 'c9', 'name' => 'Fire Spell', 'type' => 'magic', 'class' => 'magic', 'description' => 'Replace one active monster.', 'rollRequirement' => null],
            ['id' => 'c10', 'name' => 'Healing Spell', 'type' => 'magic', 'class' => 'magic', 'description' => 'Draw one card from the deck.', 'rollRequirement' => null],
            ['id' => 'c11', 'name' => 'Plus Two', 'type' => 'modifier', 'class' => 'modifier', 'description' => '+2 bonus when attacking monsters.', 'rollRequirement' => null],
            ['id' => 'c12', 'name' => 'Minus Two', 'type' => 'modifier', 'class' => 'modifier', 'description' => '+1 bonus when attacking monsters.', 'rollRequirement' => null],
            ['id' => 'c13', 'name' => 'Challenge', 'type' => 'challenge', 'class' => 'challenge', 'description' => 'The next player discards one card.', 'rollRequirement' => null],
            ['id' => 'c14', 'name' => 'Heroic Guardian', 'type' => 'hero', 'class' => 'guardian', 'description' => 'Roll 7+ to draw two cards.', 'rollRequirement' => 7],
            ['id' => 'c15', 'name' => 'Wild Bard', 'type' => 'hero', 'class' => 'bard', 'description' => 'Roll 8+ to play another card.', 'rollRequirement' => 8],
            ['id' => 'c16', 'name' => 'Arcane Wizard', 'type' => 'hero', 'class' => 'wizard', 'description' => 'Roll 7+ to discard an enemy item.', 'rollRequirement' => 7],
            ['id' => 'c17', 'name' => 'Fast Thief', 'type' => 'hero', 'class' => 'thief', 'description' => 'Roll 6+ to look at a hand.', 'rollRequirement' => 6],
            ['id' => 'c18', 'name' => 'Heavy Fighter', 'type' => 'hero', 'class' => 'fighter', 'description' => 'Roll 8+ to attack with bonus.', 'rollRequirement' => 8]
        ];
    }
}