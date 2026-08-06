<?php
/**
 * Database connection (PDO, MySQL/MariaDB).
 *
 * Provides a single shared PDO instance via db(). All queries throughout
 * the site must use prepared statements through this connection — never
 * concatenate user input into SQL.
 */

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (!defined('DB_HOST')) {
        throw new RuntimeException('Database is not configured yet. Please run install.php.');
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . (defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4');

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $e) {
        error_log('Database connection failed: ' . $e->getMessage());
        if (defined('APP_ENV') && APP_ENV === 'production') {
            http_response_code(500);
            die('Sorry, the website is temporarily unavailable. Please try again shortly.');
        }
        die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
    }

    return $pdo;
}
