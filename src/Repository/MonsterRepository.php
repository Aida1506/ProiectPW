<?php

namespace App\Repository;

use App\Database\Database;

class MonsterRepository
{
    private Database $db;

    /**
     * Initializeaza repository-ul, creeaza tabela monsters si insereaza monstrii impliciti.
     */
    public function __construct(Database $db)
    {
        // Retinem conexiunea la baza de date.
        $this->db = $db;
        // Cream tabela daca lipseste.
        $this->createTable();
        // Adaugam monstrii impliciti la prima rulare.
        $this->seedMonsters();
    }

    /**
     * Creeaza tabela monsters daca nu exista.
     * Fiecare monstru are cerinta de zar, penalizare si recompensa.
     */
    private function createTable(): void
    {
        // Tabela monsters pastreaza datele statice ale monstrilor.
        $sql = "
            CREATE TABLE IF NOT EXISTS monsters (
                id VARCHAR(255) PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                roll_requirement INT NOT NULL,
                penalty TEXT,
                reward TEXT
            )
        ";

        // Executa SQL-ul de creare.
        $this->db->executeStatement($sql);
    }

    /**
     * Insereaza monstrii impliciti doar cand tabela este goala.
     */
    private function seedMonsters(): void
    {
        // Numarul de monstri existenti decide daca mai facem seed.
        $count = (int) $this->db->executeQuery("SELECT COUNT(*) as count FROM monsters")[0]['count'];

        if ($count > 0) {
            // Evitam duplicarea monstrilor la fiecare pornire.
            return;
        }

        // Inseram monstrii impliciti unul cate unul.
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

    /**
     * Returneaza toti monstrii disponibili pentru deck-ul jocului.
     */
    public function findAll(): array
    {
        // Selecteaza toti monstrii disponibili pentru joc.
        $rows = $this->db->executeQuery("SELECT * FROM monsters ORDER BY id ASC");
        // Normalizeaza randurile SQL.
        return array_map([$this, 'hydrateMonster'], $rows);
    }

    /**
     * Converteste un rand SQL intr-un array de monstru folosit de API.
     */
    private function hydrateMonster(array $row): array
    {
        // Convertim roll_requirement in int ca frontend-ul sa primeasca numar, nu string.
        return [
            'id' => $row['id'],
            'name' => $row['name'],
            'rollRequirement' => (int) $row['roll_requirement'],
            'penalty' => $row['penalty'] ?? '',
            'reward' => $row['reward'] ?? ''
        ];
    }

    /**
     * Defineste setul initial de monstri pentru joc.
     */
    private function getDefaultMonsters(): array
    {
        // Setul de monstri initial folosit la seed.
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
