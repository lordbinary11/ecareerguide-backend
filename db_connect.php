<?php
// db_connect.php - Database connection file (PostgreSQL)

// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Detect local development
$is_local = getenv('APP_ENV') === 'local' || empty(getenv('DATABASE_URL'));

if ($is_local) {
    // Local development settings
    $host = getenv('DB_HOST') ?: 'localhost';
    $dbname = getenv('DB_NAME') ?: 'ecareer_guidance';
    $username = getenv('DB_USER') ?: 'postgres';
    $password = getenv('DB_PASS') ?: 'password';
    $port = getenv('DB_PORT') ?: '5432';
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
} else {
    // Render/production settings (DATABASE_URL)
    $database_url = getenv('DATABASE_URL');
    $url = parse_url($database_url);
    $host = $url['host'];
    $port = $url['port'] ?? '5432';
    $dbname = ltrim($url['path'], '/');
    $username = $url['user'];
    $password = $url['pass'];
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";
}

try {
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    // (Optional) Test connection
    $pdo->query('SELECT 1');
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
