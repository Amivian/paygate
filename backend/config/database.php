<?php

/**
 * Database Connection (PDO + MySQL)
 *
 * Returns a singleton PDO instance configured for the
 * payment platform. Uses environment variables for credentials.
 */

declare(strict_types=1);

require_once __DIR__ . '/env.php';

function getDatabase(): PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $host = env('DB_HOST', 'localhost');
    $port = env('DB_PORT', '3306');
    $name = env('DB_NAME', 'payment_platform');
    $user = env('DB_USER', 'root');
    $pass = env('DB_PASS', '');

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'status' => false,
            'message' => 'Database connection failed. Please check your configuration.',
        ]);
        error_log('DB Connection Error: ' . $e->getMessage());
        exit(1);
    }

    return $pdo;
}
