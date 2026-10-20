<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Component A: Identity, Access and Administration
 *
 * Shared Database Connection (PDO Singleton)
 * Accessible by Components A, B, and C
 */

declare(strict_types=1);

class Database
{
    private static ?PDO $instance = null;
    private static ?array $config = null;

    /**
     * Private constructor to prevent direct instantiation (Singleton pattern).
     */
    private function __construct()
    {
    }

    /**
     * Prevent cloning of the instance.
     */
    private function __clone()
    {
    }

    /**
     * Load application configuration.
     * Looks for config.local.php first (gitignored), then config.php, then config.sample.php.
     */
    public static function loadConfig(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        $baseDir = dirname(__DIR__);
        $localConfig = $baseDir . '/config/config.local.php';
        $mainConfig  = $baseDir . '/config/config.php';
        $sampleConfig = $baseDir . '/config/config.sample.php';

        if (file_exists($localConfig)) {
            self::$config = require $localConfig;
        } elseif (file_exists($mainConfig)) {
            self::$config = require $mainConfig;
        } elseif (file_exists($sampleConfig)) {
            self::$config = require $sampleConfig;
        } else {
            throw new RuntimeException('No configuration file found in config directory.');
        }

        return self::$config;
    }

    /**
     * Retrieve the shared PDO database connection instance.
     *
     * @return PDO
     * @throws PDOException
     */
    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $config = self::loadConfig();
            $db = $config['db'] ?? [];

            $host    = $db['host'] ?? 'localhost';
            $dbname  = $db['name'] ?? 'urbaneats_db';
            $user    = $db['user'] ?? 'root';
            $pass    = $db['pass'] ?? '';
            $charset = $db['charset'] ?? 'utf8mb4';
            $port    = $db['port'] ?? 3306;

            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_TIMEOUT            => 5,
            ];

            try {
                self::$instance = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                // Log the technical error without exposing sensitive credentials
                error_log(sprintf(
                    '[%s] Database connection error: %s (DSN: mysql:host=%s;dbname=%s)',
                    date('Y-m-d H:i:s'),
                    $e->getMessage(),
                    $host,
                    $dbname
                ));

                // Re-throw so upstream error handler or caller can present a friendly error page
                throw $e;
            }
        }

        return self::$instance;
    }
}

/**
 * Functional helper to retrieve the PDO connection.
 * Primary API exposed to Components A, B, and C.
 *
 * @return PDO
 */
function get_db_connection(): PDO
{
    return Database::getConnection();
}
