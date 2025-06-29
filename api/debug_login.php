<?php
// Debug Login Endpoint
// This endpoint helps debug login issues by providing detailed error information

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set CORS headers to allow all origins for testing
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Log request details for debugging
$log_data = [
    'timestamp' => date('Y-m-d H:i:s'),
    'method' => $_SERVER['REQUEST_METHOD'],
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'not set',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'not set',
    'raw_input' => file_get_contents('php://input'),
    'post_data' => $_POST,
    'get_data' => $_GET
];

// Write to debug log
file_put_contents('debug_login.log', json_encode($log_data, JSON_PRETTY_PRINT) . "\n\n", FILE_APPEND);

try {
    // Check if it's a POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST method is allowed');
    }

    // Get raw input
    $raw_input = file_get_contents('php://input');
    
    // Try to parse JSON
    $json_data = json_decode($raw_input, true);
    
    // If JSON parsing failed, try POST data
    if (json_last_error() !== JSON_ERROR_NONE) {
        $json_data = $_POST;
    }
    
    // Log parsed data
    $log_data['parsed_data'] = $json_data;
    file_put_contents('debug_login.log', "Parsed data: " . json_encode($log_data, JSON_PRETTY_PRINT) . "\n\n", FILE_APPEND);

    // Validate required fields
    if (empty($json_data)) {
        throw new Exception('No data received');
    }

    $email = $json_data['email'] ?? $json_data['Email'] ?? null;
    $password = $json_data['password'] ?? $json_data['Password'] ?? null;

    if (empty($email)) {
        throw new Exception('Email is required');
    }

    if (empty($password)) {
        throw new Exception('Password is required');
    }

    // Test database connection
    require_once '../db_connect.php';
    
    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    // Test query to check if users table exists
    $test_query = "SHOW TABLES LIKE 'users'";
    $test_result = mysqli_query($conn, $test_query);
    
    if (!$test_result) {
        throw new Exception('Database query failed: ' . mysqli_error($conn));
    }

    if (mysqli_num_rows($test_result) === 0) {
        throw new Exception('Users table does not exist');
    }

    // Check if user exists (without password verification for debugging)
    $email = mysqli_real_escape_string($conn, $email);
    $check_query = "SELECT id, email, password FROM users WHERE email = '$email' LIMIT 1";
    $check_result = mysqli_query($conn, $check_query);

    if (!$check_result) {
        throw new Exception('User check query failed: ' . mysqli_error($conn));
    }

    if (mysqli_num_rows($check_result) === 0) {
        throw new Exception('User not found with email: ' . $email);
    }

    $user = mysqli_fetch_assoc($check_result);

    // For debugging, return success with user info (without password)
    $response = [
        'success' => true,
        'message' => 'Debug login successful',
        'debug_info' => [
            'email_received' => $email,
            'password_received' => !empty($password) ? 'yes' : 'no',
            'user_found' => true,
            'user_id' => $user['id'],
            'stored_password_length' => strlen($user['password']),
            'content_type_received' => $_SERVER['CONTENT_TYPE'] ?? 'not set',
            'request_method' => $_SERVER['REQUEST_METHOD']
        ]
    ];

    http_response_code(200);
    echo json_encode($response, JSON_PRETTY_PRINT);

} catch (Exception $e) {
    $error_response = [
        'success' => false,
        'message' => $e->getMessage(),
        'debug_info' => [
            'content_type_received' => $_SERVER['CONTENT_TYPE'] ?? 'not set',
            'request_method' => $_SERVER['REQUEST_METHOD'],
            'raw_input_length' => strlen(file_get_contents('php://input')),
            'post_data_count' => count($_POST),
            'get_data_count' => count($_GET)
        ]
    ];

    http_response_code(400);
    echo json_encode($error_response, JSON_PRETTY_PRINT);
}
?> 