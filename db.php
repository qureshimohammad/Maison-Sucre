<?php
/**
 * Database connection (PDO).
 * Change these four values if your MySQL setup is different.
 * XAMPP default: user "root" with an EMPTY password.
 */
define('DB_HOST', 'localhost');
define('DB_NAME', 'maison_sucre');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // throw errors instead of failing silently
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,                    // real prepared statements (safer)
        ]
    );
    // Keep MySQL timestamps in Indian Standard Time so bills show the right time.
    $pdo->exec("SET time_zone = '+05:30'");
} catch (PDOException $e) {
    http_response_code(500);
    exit('Could not connect to the database. Make sure MySQL is running, schema.sql is imported, and the values in db.php are correct.');
}
