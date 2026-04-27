<?php

namespace App\Database;

class DatabaseConfig
{
    /**
     * Alege automat driverul de baza de date disponibil.
     * Prioritizeaza SQLite pentru rulare locala simpla, apoi incearca MySQL cu setarile implicite XAMPP.
     */
    public static function detectDatabase(): array
    {
        // Force SQLite for testing
        if (extension_loaded('pdo_sqlite')) {
            // SQLite este ales primul deoarece nu necesita server separat.
            return [
                'driver' => 'pdo_sqlite',
                // Baza de date este tinuta in storage/database.sqlite.
                'path' => __DIR__ . '/../../storage/database.sqlite'
            ];
        }

        // Check for MySQL (XAMPP default)
        if (extension_loaded('pdo_mysql')) {
            try {
                // Test MySQL connection without database
                // Configurare implicita pentru MySQL din XAMPP: root fara parola.
                $testConfig = [
                    'driver' => 'pdo_mysql',
                    'host' => 'localhost',
                    'port' => 3306,
                    'user' => 'root',
                    'password' => '',
                    'charset' => 'utf8mb4'
                ];
                $pdo = new \PDO(
                    // Conectare la server fara dbname, pentru a putea crea baza daca lipseste.
                    "mysql:host={$testConfig['host']};port={$testConfig['port']};charset={$testConfig['charset']}",
                    $testConfig['user'],
                    $testConfig['password']
                );
                // Create database if it doesn't exist
                // Creeaza baza heretoslay doar daca serverul MySQL este disponibil.
                $pdo->exec("CREATE DATABASE IF NOT EXISTS heretoslay");
                // Returneaza configurarea finala cu numele bazei de date inclus.
                return array_merge($testConfig, ['dbname' => 'heretoslay']);
            } catch (\Exception $e) {
                // Fall back to SQLite
                // Daca MySQL nu raspunde, functia continua si va ajunge la eroarea finala.
            }
        }

        // Fara driver PDO suportat, aplicatia nu poate porni.
        throw new \RuntimeException('No supported database driver found. Please install PDO MySQL or SQLite.');
    }

    /**
     * Incarca fisierul config/database.php daca exista.
     * Daca fisierul lipseste, detecteaza baza de date si salveaza configurarea pentru rularile urmatoare.
     */
    public static function getConfig(): array
    {
        // Fisierul de configurare este tinut in folderul config din radacina proiectului.
        $configFile = __DIR__ . '/../../config/database.php';

        if (file_exists($configFile)) {
            // require intoarce array-ul scris in config/database.php.
            $config = require $configFile;
            return $config;
        }

        // Auto-detect and create config
        // Daca nu exista fisier, detectam automat driverul disponibil.
        $config = self::detectDatabase();
        // Salvam configurarea ca sa nu repetam detectia.
        self::saveConfig($config);

        return $config;
    }

    /**
     * Scrie configurarea bazei de date intr-un fisier PHP care returneaza un array.
     * Astfel aplicatia nu trebuie sa redetecteze driverul la fiecare pornire.
     */
    public static function saveConfig(array $config): void
    {
        // Directorul config trebuie sa existe inainte sa scriem fisierul.
        $configDir = __DIR__ . '/../../config';
        if (!is_dir($configDir)) {
            // Creeaza folderul recursiv daca lipseste.
            mkdir($configDir, 0755, true);
        }

        // var_export produce cod PHP valid pentru array-ul de configurare.
        $content = "<?php\n\nreturn " . var_export($config, true) . ";\n";
        // Scrie fisierul config/database.php.
        file_put_contents($configDir . '/database.php', $content);
    }

    /**
     * Verifica rapid daca aplicatia se poate conecta la baza de date.
     * Ruleaza SELECT 1 si intoarce false daca driverul sau conexiunea nu functioneaza.
     */
    public static function testConnection(): bool
    {
        try {
            // Creeaza o conexiune folosind configurarea curenta.
            $db = new Database(self::getConfig());
            // SELECT 1 este o interogare minima, buna pentru test de conectivitate.
            $db->executeQuery('SELECT 1');
            return true;
        } catch (\Exception $e) {
            // Orice eroare de driver/conexiune este raportata simplu ca false.
            return false;
        }
    }
}
