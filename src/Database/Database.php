<?php

namespace App\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;

class Database
{
    private Connection $connection;

    /**
     * Creeaza conexiunea Doctrine DBAL pe baza configurarii primite.
     * Configurarea poate fi pentru SQLite sau MySQL, iar DBAL ascunde diferentele dintre drivere.
     */
    public function __construct(array $config)
    {
        // DriverManager interpreteaza array-ul de configurare si creeaza conexiunea corecta.
        $this->connection = DriverManager::getConnection($config);
    }

    /**
     * Returneaza conexiunea bruta DBAL pentru cazurile in care alte clase au nevoie de acces direct.
     */
    public function getConnection(): Connection
    {
        // Returnam aceeasi conexiune, nu cream una noua.
        return $this->connection;
    }

    /**
     * Ruleaza un SELECT si intoarce toate randurile ca array asociativ.
     * Parametrii sunt trimisi separat pentru a evita concatenarea nesigura de SQL.
     */
    public function executeQuery(string $sql, array $params = []): array
    {
        try {
            // executeQuery este folosit pentru SELECT-uri si accepta parametri bind-uiti.
            $stmt = $this->connection->executeQuery($sql, $params);
            // fetchAllAssociative intoarce randurile ca array-uri cu chei dupa numele coloanelor.
            return $stmt->fetchAllAssociative();
        } catch (Exception $e) {
            // Transformam exceptia DBAL intr-o RuntimeException mai simpla pentru restul aplicatiei.
            throw new \RuntimeException('Database query failed: ' . $e->getMessage());
        }
    }

    /**
     * Ruleaza o comanda care modifica baza de date, de exemplu INSERT, UPDATE, DELETE sau CREATE TABLE.
     * Intoarce numarul de randuri afectate, util la stergeri si update-uri.
     */
    public function executeStatement(string $sql, array $params = []): int
    {
        try {
            // executeStatement este folosit pentru comenzi care modifica structura sau datele.
            return $this->connection->executeStatement($sql, $params);
        } catch (Exception $e) {
            // Mesajul pastreaza cauza reala, utila la debug.
            throw new \RuntimeException('Database statement failed: ' . $e->getMessage());
        }
    }

    /**
     * Intoarce ultimul id generat de baza de date.
     * In proiectul curent id-urile sunt generate manual, dar metoda ramane utila pentru extensii.
     */
    public function lastInsertId(): string
    {
        // DBAL delega apelul catre driverul real al bazei de date.
        return $this->connection->lastInsertId();
    }
}
