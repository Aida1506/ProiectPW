<?php

namespace App\Database;

class DatabaseConfig
{
    public static function detectDatabase(): array
    {
        // Force SQLite for testing
        if (extension_loaded('pdo_sqlite')) {
            return [
                'driver' => 'pdo_sqlite',
                'path' => __DIR__ . '/../../storage/database.sqlite'
            ];
        }

        // Check for MySQL (XAMPP default)
        if (extension_loaded('pdo_mysql')) {
            try {
                // Test MySQL connection without database
                $testConfig = [
                    'driver' => 'pdo_mysql',
                    'host' => 'localhost',
                    'port' => 3306,
                    'user' => 'root',
                    'password' => '',
                    'charset' => 'utf8mb4'
                ];
                $pdo = new \PDO(
                    "mysql:host={$testConfig['host']};port={$testConfig['port']};charset={$testConfig['charset']}",
                    $testConfig['user'],
                    $testConfig['password']
                );
                // Create database if it doesn't exist
                $pdo->exec("CREATE DATABASE IF NOT EXISTS heretoslay");
                return array_merge($testConfig, ['dbname' => 'heretoslay']);
            } catch (\Exception $e) {
                // Fall back to SQLite
            }
        }

        throw new \RuntimeException('No supported database driver found. Please install PDO MySQL or SQLite.');
    }

    public static function getConfig(): array
    {
        $configFile = __DIR__ . '/../../config/database.php';

        if (file_exists($configFile)) {
            $config = require $configFile;
            return $config;
        }

        // Auto-detect and create config
        $config = self::detectDatabase();
        self::saveConfig($config);

        return $config;
    }

    public static function saveConfig(array $config): void
    {
        $configDir = __DIR__ . '/../../config';
        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }

        $content = "<?php\n\nreturn " . var_export($config, true) . ";\n";
        file_put_contents($configDir . '/database.php', $content);
    }

    public static function testConnection(): bool
    {
        try {
            $db = new Database(self::getConfig());
            $db->executeQuery('SELECT 1');
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}