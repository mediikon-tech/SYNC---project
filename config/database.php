<?php
/**
 * Sync database connection.
 *
 * Local XAMPP defaults are for development only. Change these values for your
 * own MySQL setup. Never commit production credentials to source control.
 */

declare(strict_types=1);

$dbHost = getenv('SYNC_DB_HOST') ?: '127.0.0.1';
$dbName = getenv('SYNC_DB_NAME') ?: 'sync_db';
$dbUser = getenv('SYNC_DB_USER') ?: 'root';
$dbPass = getenv('SYNC_DB_PASS') ?: '';

$dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $exception) {
    // Log details on the server; avoid exposing credentials or database internals.
    error_log('Sync database connection failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('Sync cannot connect to its database. Check the local database setup and try again.');
}
