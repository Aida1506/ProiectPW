<?php

require __DIR__ . '/vendor/autoload.php';

use App\Database\DatabaseConfig;
use App\Database\Database;

// Script de initializare: incarca configurarea, testeaza conexiunea si creeaza repository-urile.
// Constructorii repository-urilor creeaza tabelele si insereaza datele implicite unde este nevoie.
echo "Setting up database...\n";

// Get database config
try {
    // Incarca sau detecteaza configurarea bazei de date.
    $config = DatabaseConfig::getConfig();
    echo "Database config loaded.\n";
} catch (Exception $e) {
    // Daca nu exista driver sau configurarea este invalida, oprim scriptul.
    echo "Error loading database config: " . $e->getMessage() . "\n";
    exit(1);
}

// Test connection
if (!DatabaseConfig::testConnection()) {
    // Fara conexiune, tabelele nu pot fi create.
    echo "Cannot connect to database. Please check your configuration.\n";
    exit(1);
}

echo "Database connection successful.\n";

// Initialize database and repositories to create tables
// Crearea obiectului Database deschide conexiunea.
$db = new Database($config);

// Constructorii repository-urilor creeaza tabelele necesare.
$gameRepo = new \App\Repository\GameRepository($db);
$playerRepo = new \App\Repository\PlayerRepository($db);
// CardRepository si MonsterRepository fac si seed pentru datele statice.
$cardRepo = new \App\Repository\CardRepository($db);
$monsterRepo = new \App\Repository\MonsterRepository($db);

echo "Database tables created and seeded.\n";
echo "Setup complete!\n";
