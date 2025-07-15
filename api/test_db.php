<?php
// test_db.php - Database connection test endpoint

// Include CORS helper
require_once __DIR__ . '/cors_helper.php';

header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../db_connect.php';
    
    // Test basic connection
    $test_query = $pdo->query('SELECT 1 as test');
    $result = $test_query->fetch();
    
    // Test if tables exist
    $tables_query = $pdo->query("
        SELECT table_name 
        FROM information_schema.tables 
        WHERE table_schema = 'public' 
        ORDER BY table_name
    ");
    $tables = $tables_query->fetchAll();
    
    // Test user count
    $user_count = 0;
    $counselor_count = 0;
    
    try {
        $user_query = $pdo->query('SELECT COUNT(*) as count FROM users');
        $user_count = $user_query->fetch()['count'];
    } catch (Exception $e) {
        // Table might not exist yet
    }
    
    try {
        $counselor_query = $pdo->query('SELECT COUNT(*) as count FROM counselors');
        $counselor_count = $counselor_query->fetch()['count'];
    } catch (Exception $e) {
        // Table might not exist yet
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Database connection successful',
        'data' => [
            'connection_test' => $result['test'],
            'tables_found' => count($tables),
            'table_names' => array_column($tables, 'table_name'),
            'user_count' => $user_count,
            'counselor_count' => $counselor_count,
            'environment' => [
                'database_url_set' => !empty(getenv('DATABASE_URL')),
                'db_host' => getenv('DB_HOST') ?: 'not_set',
                'db_name' => getenv('DB_NAME') ?: 'not_set',
                'db_user' => getenv('DB_USER') ?: 'not_set'
            ]
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed',
        'error' => $e->getMessage(),
        'environment' => [
            'database_url_set' => !empty(getenv('DATABASE_URL')),
            'db_host' => getenv('DB_HOST') ?: 'not_set',
            'db_name' => getenv('DB_NAME') ?: 'not_set',
            'db_user' => getenv('DB_USER') ?: 'not_set'
        ]
    ]);
}
?> 