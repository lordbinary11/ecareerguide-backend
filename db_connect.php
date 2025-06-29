<?php
// db_connect.php - Database connection file

// Use environment variables if available (for Render), otherwise use local values
$host = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME') ?: 'ecareer_guidance';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASS') ?: '';

// For Render, also support DATABASE_URL format
$database_url = getenv('DATABASE_URL');
if ($database_url) {
    $url = parse_url($database_url);
    $host = $url['host'];
    $dbname = ltrim($url['path'], '/');
    $username = $url['user'];
    $password = $url['pass'];
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Also create mysqli connection for backward compatibility
    $conn = mysqli_connect($host, $username, $password, $dbname);
    if (!$conn) {
        throw new Exception("mysqli connection failed: " . mysqli_connect_error());
    }
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}
