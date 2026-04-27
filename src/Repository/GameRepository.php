<?php

namespace App\Repository;

use App\Database\Database;

class GameRepository
{
    private Database $db;

    /**
     * Primeste wrapperul de baza de date si se asigura ca tabela games exista.
     */
    public function __construct(Database $db)
    {
        // Repository-ul primeste obiectul Database deja configurat.
        $this->db = $db;
        // La prima folosire, ne asiguram ca tabela exista.
        $this->createTable();
    }

    /**
     * Creeaza tabela pentru jocuri daca nu exista.
     * Campurile de tip deck/monsters/discard sunt salvate ca JSON pentru a pastra usor starea jocului.
     */
    private function createTable(): void
    {
        // SQL-ul este compatibil cu SQLite si MySQL pentru campurile folosite aici.
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

        // executeStatement ruleaza CREATE TABLE si nu returneaza randuri.
        $this->db->executeStatement($sql);
    }

    /**
     * Returneaza toate jocurile, cele mai noi primele.
     * Fiecare rand SQL este convertit in structura folosita de service si frontend.
     */
    public function findAll(): array
    {
        // Citim jocurile in ordine descrescatoare dupa data crearii.
        $sql = "SELECT * FROM games ORDER BY created_at DESC";
        $rows = $this->db->executeQuery($sql);

        // Fiecare rand este hidratat in formatul asteptat de service.
        return array_map([$this, 'hydrateGame'], $rows);
    }

    /**
     * Cauta un joc dupa id.
     * Intoarce null cand jocul nu exista, ca rutele sa poata raspunde cu 404.
     */
    public function findById(string $id): ?array
    {
        // Folosim placeholder ? pentru id ca sa evitam SQL injection.
        $sql = "SELECT * FROM games WHERE id = ?";
        $rows = $this->db->executeQuery($sql, [$id]);

        if (empty($rows)) {
            // Lista goala inseamna ca jocul nu exista.
            return null;
        }

        // Pentru id unic exista cel mult un rand, deci luam primul.
        return $this->hydrateGame($rows[0]);
    }

    /**
     * Salveaza un joc indiferent daca este nou sau existent.
     * Daca id-ul exista face UPDATE, altfel face INSERT.
     */
    public function save(array $game): void
    {
        // Convertim structura de joc in format SQL inainte de salvare.
        $data = $this->dehydrateGame($game);

        if ($this->findById($game['id'])) {
            // Daca jocul exista deja, actualizam randul.
            $this->update($data);
            return;
        }

        // Daca nu exista, inseram un rand nou.
        $this->insert($data);
    }

    /**
     * Sterge jocul din tabela games si intoarce true daca un rand a fost afectat.
     */
    public function delete(string $id): bool
    {
        // Stergem dupa cheia primara.
        $sql = "DELETE FROM games WHERE id = ?";
        // Intoarcem true doar daca baza a sters cel putin un rand.
        return $this->db->executeStatement($sql, [$id]) > 0;
    }

    /**
     * Insereaza un joc nou in baza de date.
     * Primeste deja datele in format dehydratat, adica pregatit pentru SQL.
     */
    private function insert(array $data): void
    {
        // INSERT include toate campurile persistente ale jocului.
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

        // Ordinea parametrilor trebuie sa corespunda ordinii placeholderelor din SQL.
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

    /**
     * Actualizeaza toate campurile persistente ale unui joc existent.
     */
    private function update(array $data): void
    {
        // UPDATE rescrie starea completa a jocului dupa fiecare actiune.
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

        // Id-ul este ultimul parametru pentru clauza WHERE.
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

    /**
     * Converteste un rand din baza de date in structura folosita in cod.
     * Decodeaza campurile JSON si normalizeaza numele campurilor in camelCase.
     */
    private function hydrateGame(array $row): array
    {
        // Transformam snake_case din DB in camelCase pentru codul PHP/JS.
        return [
            'id' => $row['id'],
            'name' => $row['name'],
            'players' => [],
            'currentTurnPlayer' => null,
            'currentTurnPlayerId' => $row['current_turn_player_id'],
            'actionPoints' => (int) $row['action_points'],
            // json_decode transforma textul salvat in lista de carti.
            'mainDeck' => json_decode($row['main_deck'] ?? '[]', true) ?: [],
            'discardPile' => json_decode($row['discard_pile'] ?? '[]', true) ?: [],
            'monsterDeck' => json_decode($row['monster_deck'] ?? '[]', true) ?: [],
            'activeMonsters' => json_decode($row['active_monsters'] ?? '[]', true) ?: [],
            'lastMessage' => $row['last_message'] ?? ''
        ];
    }

    /**
     * Converteste structura interna a jocului in valori potrivite pentru SQL.
     * Listele complexe sunt codate JSON inainte de salvare.
     */
    private function dehydrateGame(array $game): array
    {
        // Structurile complexe se transforma in JSON, deoarece coloanele sunt TEXT.
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
