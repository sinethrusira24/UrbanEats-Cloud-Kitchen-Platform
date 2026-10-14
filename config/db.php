<?php
/**
 * Database connection singleton using PDO.
 * Credentials are read from environment variables or config.sample.php defaults.
 * NEVER commit real credentials; copy config.sample.php to config.local.php and
 * fill in your values, then add config.local.php to .gitignore.
 */

define('DB_HOST',   getenv('URBANEATS_DB_HOST')   ?: 'localhost');
define('DB_PORT',   getenv('URBANEATS_DB_PORT')   ?: '3306');
define('DB_NAME',   getenv('URBANEATS_DB_NAME')   ?: 'urbaneats_db');
define('DB_USER',   getenv('URBANEATS_DB_USER')   ?: 'root');
define('DB_PASS',   getenv('URBANEATS_DB_PASS')   ?: '');
define('DB_CHARSET','utf8mb4');

function get_db(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Log the real error but show a friendly message
            error_log('[UrbanEats] DB connection failed: ' . $e->getMessage());
            http_response_code(500);
            include __DIR__ . '/../includes/errors/500.php';
            exit;
        }
    }

    return $pdo;
}
