<?php
// db_connect.php - Database connection file (PostgreSQL)

// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);

// For Render, prioritize DATABASE_URL format
$database_url = getenv('DATABASE_URL');

if ($database_url) {
    // Parse the DATABASE_URL from Render
    $url = parse_url($database_url);
    
    if ($url === false) {
        die("Invalid DATABASE_URL format");
    }
    
    $host = $url['host'];
    $port = $url['port'] ?? '5432';
    $dbname = ltrim($url['path'], '/');
    $username = $url['user'];
    $password = $url['pass'];
    
    // Log connection details for debugging (remove in production)
    error_log("Connecting to PostgreSQL via DATABASE_URL: host=$host, port=$port, dbname=$dbname, user=$username");
    
} else {
    // Fallback to individual environment variables
    $host = getenv('DB_HOST') ?: 'localhost';
    $dbname = getenv('DB_NAME') ?: 'ecareer_guidance';
    $username = getenv('DB_USER') ?: 'postgres';
    $password = getenv('DB_PASS') ?: '';
    $port = getenv('DB_PORT') ?: '5432';
    
    error_log("Connecting to PostgreSQL via individual env vars: host=$host, port=$port, dbname=$dbname, user=$username");
}

try {
    // Build PostgreSQL connection string
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";
    
    // Create PDO connection with error handling
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    
    // Test the connection
    $pdo->query('SELECT 1');
    error_log("PostgreSQL connection successful");
    
    // For backward compatibility, create a mysqli-like connection object
    $conn = new stdClass();
    $conn->connect_error = null;
    $conn->error = null;
    
    // Add a simple query method for backward compatibility
    $conn->query = function($sql) use ($pdo) {
        try {
            return $pdo->query($sql);
        } catch (Exception $e) {
            $conn->error = $e->getMessage();
            error_log("Query error: " . $e->getMessage());
            return false;
        }
    };
    
} catch (PDOException $e) {
    error_log("PostgreSQL connection failed: " . $e->getMessage());
    die("Database connection failed: " . $e->getMessage());
} catch (Exception $e) {
    error_log("General connection error: " . $e->getMessage());
    die("Database connection failed: " . $e->getMessage());
}
