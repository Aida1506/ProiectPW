<?php

namespace App\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;

class Database
{
    private Connection $connection;

    public function __construct(array $config)
    {
        $this->connection = DriverManager::getConnection($config);
    }

    public function getConnection(): Connection
    {
        return $this->connection;
    }

    public function executeQuery(string $sql, array $params = []): array
    {
        try {
            $stmt = $this->connection->executeQuery($sql, $params);
            return $stmt->fetchAllAssociative();
        } catch (Exception $e) {
            throw new \RuntimeException('Database query failed: ' . $e->getMessage());
        }
    }

    public function executeStatement(string $sql, array $params = []): int
    {
        try {
            return $this->connection->executeStatement($sql, $params);
        } catch (Exception $e) {
            throw new \RuntimeException('Database statement failed: ' . $e->getMessage());
        }
    }

    public function lastInsertId(): string
    {
        return $this->connection->lastInsertId();
    }
}