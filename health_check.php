<?php

// Simple health check script
echo "Here to Slay API Health Check\n";
echo "==============================\n\n";

// Check PHP version
echo "PHP Version: " . PHP_VERSION . "\n";

// Check required extensions
$requiredExtensions = ['pdo', 'json', 'mbstring'];
foreach ($requiredExtensions as $ext) {
    echo "Extension {$ext}: " . (extension_loaded($ext) ? '✓' : '✗') . "\n";
}

// Check database
echo "\nDatabase Check:\n";
try {
    require __DIR__ . '/vendor/autoload.php';
    $config = \App\Database\DatabaseConfig::getConfig();
    $connected = \App\Database\DatabaseConfig::testConnection();
    echo "Database Connection: " . ($connected ? '✓' : '✗') . "\n";
    echo "Database Type: " . ($config['driver'] ?? 'unknown') . "\n";
} catch (Exception $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
}

// Check files
$files = [
    'vendor/autoload.php',
    'src/Database/Database.php',
    'src/Service/GameService.php',
    'public/index.php'
];

echo "\nFile Check:\n";
foreach ($files as $file) {
    echo "File {$file}: " . (file_exists($file) ? '✓' : '✗') . "\n";
}

echo "\nSetup complete! Access the API at http://localhost/proiectpw/\n";