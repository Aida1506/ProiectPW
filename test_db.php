<?php

require 'vendor/autoload.php';

try {
    // Incarca configurarea detectata/salvata si o afiseaza pentru debug.
    $config = \App\Database\DatabaseConfig::getConfig();
    echo "Database config:\n";
    print_r($config);

    // Ruleaza testul SELECT 1 prin DatabaseConfig::testConnection.
    echo "\nTesting connection...\n";
    $connected = \App\Database\DatabaseConfig::testConnection();
    echo "Connection: " . ($connected ? "SUCCESS" : "FAILED") . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
