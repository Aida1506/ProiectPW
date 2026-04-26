<?php

require 'vendor/autoload.php';

try {
    $config = \App\Database\DatabaseConfig::getConfig();
    echo "Database config:\n";
    print_r($config);

    echo "\nTesting connection...\n";
    $connected = \App\Database\DatabaseConfig::testConnection();
    echo "Connection: " . ($connected ? "SUCCESS" : "FAILED") . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}