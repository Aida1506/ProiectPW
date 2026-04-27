<?php

namespace App\Repository;

use App\Database\Database;

class PlayerRepository
{
    private Database $db;

    /**
     * Primeste conexiunea la baza de date si creeaza tabela players daca lipseste.
     */
    public function __construct(Database $db)
    {
        // Se pastreaza conexiunea pentru toate operatiile repository-ului.
        $this->db = $db;
        // Tabela este creata automat cand repository-ul este initializat.
        $this->createTable();
    }

    /**
     * Creeaza tabela pentru jucatori.
     * Mana, party-ul si monstrii invinsi sunt salvate ca JSON pentru flexibilitate.
     */
    private function createTable(): void
    {
        // Tabela players contine si referinta game_id catre joc.
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

        // Ruleaza comanda de creare tabela.
        $this->db->executeStatement($sql);
    }

    /**
     * Returneaza toti jucatorii din baza de date.
     */
    public function findAll(): array
    {
        // Citim toti jucatorii, cei mai noi primii.
        $sql = "SELECT * FROM players ORDER BY created_at DESC";
        $rows = $this->db->executeQuery($sql);

        // Convertim fiecare rand SQL in structura folosita de API.
        return array_map([$this, 'hydratePlayer'], $rows);
    }

    /**
     * Returneaza jucatorii care apartin unui anumit joc.
     * Este folosit cand GameService ataseaza jucatorii la obiectul game.
     */
    public function findByGameId(string $gameId): array
    {
        // Cautam jucatorii dupa game_id pentru a reconstrui starea jocului.
        $sql = "SELECT * FROM players WHERE game_id = ? ORDER BY id ASC";
        $rows = $this->db->executeQuery($sql, [$gameId]);

        // Intoarcem lista hidratata.
        return array_map([$this, 'hydratePlayer'], $rows);
    }

    /**
     * Cauta un jucator dupa id si intoarce null daca nu exista.
     */
    public function findById(string $id): ?array
    {
        // Cautare directa dupa cheia primara a jucatorului.
        $sql = "SELECT * FROM players WHERE id = ?";
        $rows = $this->db->executeQuery($sql, [$id]);

        if (empty($rows)) {
            // null semnaleaza catre service ca jucatorul nu exista.
            return null;
        }

        // Hidratam primul rand gasit.
        return $this->hydratePlayer($rows[0]);
    }

    /**
     * Salveaza un jucator nou sau actualizeaza unul existent.
     */
    public function save(array $player): void
    {
        // Pregatim datele pentru baza de date.
        $data = $this->dehydratePlayer($player);

        if ($this->findById($player['id'])) {
            // Actualizare pentru jucator existent.
            $this->update($data);
            return;
        }

        // Inserare pentru jucator nou.
        $this->insert($data);
    }

    /**
     * Salveaza mai multi jucatori consecutiv.
     * Este folosit la crearea unui joc, cand sunt generati cei patru jucatori.
     */
    public function saveMultiple(array $players): void
    {
        // Iteram fiecare jucator si refolosim metoda save.
        foreach ($players as $player) {
            $this->save($player);
        }
    }

    /**
     * Sterge toti jucatorii unui joc.
     * Este apelat inainte de stergerea jocului, ca sa ramana baza de date curata.
     */
    public function deleteByGameId(string $gameId): int
    {
        // Stergem toti jucatorii care apartin jocului sters.
        $sql = "DELETE FROM players WHERE game_id = ?";
        return $this->db->executeStatement($sql, [$gameId]);
    }

    /**
     * Insereaza un jucator nou in tabela players.
     */
    private function insert(array $data): void
    {
        // INSERT pentru toate campurile jucatorului.
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

        // Parametrii sunt trimisi separat pentru siguranta.
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

    /**
     * Actualizeaza datele unui jucator existent.
     */
    private function update(array $data): void
    {
        // UPDATE pastreaza acelasi id, dar rescrie datele si zonele jucatorului.
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

        // Ultimul parametru este id-ul folosit in WHERE.
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

    /**
     * Converteste un rand SQL intr-un array PHP cu nume de campuri folosite de aplicatie.
     */
    private function hydratePlayer(array $row): array
    {
        // Converteste campurile din DB in structura folosita de frontend.
        return [
            'id' => $row['id'],
            'name' => $row['name'],
            'partyLeader' => $row['party_leader'],
            'partyLeaderClass' => $row['party_leader_class'],
            // Mana, party-ul si slainMonsters sunt salvate ca JSON.
            'hand' => json_decode($row['hand'] ?? '[]', true) ?: [],
            'party' => json_decode($row['party'] ?? '[]', true) ?: [],
            'slainMonsters' => json_decode($row['slain_monsters'] ?? '[]', true) ?: [],
            'gameId' => $row['game_id'],
            'role' => $row['role']
        ];
    }

    /**
     * Converteste array-ul intern al jucatorului in format de baza de date.
     */
    private function dehydratePlayer(array $player): array
    {
        // Converteste structura din aplicatie in formatul asteptat de DB.
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
