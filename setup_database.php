<?php

require __DIR__ . '/vendor/autoload.php';

use App\Database\DatabaseConfig;
use App\Database\Database;

echo "Setting up database...\n";

// Get database config
try {
    $config = DatabaseConfig::getConfig();
    echo "Database config loaded.\n";
} catch (Exception $e) {
    echo "Error loading database config: " . $e->getMessage() . "\n";
    exit(1);
}

// Test connection
if (!DatabaseConfig::testConnection()) {
    echo "Cannot connect to database. Please check your configuration.\n";
    exit(1);
}

echo "Database connection successful.\n";

// Initialize database and repositories to create tables
$db = new Database($config);

$gameRepo = new \App\Repository\GameRepository($db);
$playerRepo = new \App\Repository\PlayerRepository($db);
$cardRepo = new \App\Repository\CardRepository($db);
$monsterRepo = new \App\Repository\MonsterRepository($db);

echo "Database tables created and seeded.\n";
echo "Setup complete!\n";