<?php
// db_connect.php - Database connection file (PostgreSQL)

// Use environment variables if available (for Render), otherwise use local values
$host = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME') ?: 'ecareer_guidance';
$username = getenv('DB_USER') ?: 'postgres';
$password = getenv('DB_PASS') ?: '';
$port = getenv('DB_PORT') ?: '5432';

// For Render, also support DATABASE_URL format
$database_url = getenv('DATABASE_URL');
if ($database_url) {
    $url = parse_url($database_url);
    $host = $url['host'];
    $port = $url['port'] ?? '5432';
    $dbname = ltrim($url['path'], '/');
    $username = $url['user'];
    $password = $url['pass'];
}

try {
    // PostgreSQL connection using PDO
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;user=$username;password=$password";
    $pdo = new PDO($dsn);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // For backward compatibility, create a mysqli-like connection object
    // Note: This is a simplified wrapper since we're using PostgreSQL
    $conn = new stdClass();
    $conn->connect_error = null;
    $conn->error = null;
    
    // Add a simple query method for backward compatibility
    $conn->query = function($sql) use ($pdo) {
        try {
            return $pdo->query($sql);
        } catch (Exception $e) {
            $conn->error = $e->getMessage();
            return false;
        }
    };
    
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}
