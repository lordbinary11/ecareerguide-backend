<?php

// Include CORS helper
require_once __DIR__ . '/cors_helper.php';

header('Content-Type: application/json');

// Handle request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database connection
require_once __DIR__ . '/../db_connect.php';

// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Set to 0 for production

try {
    // Validate ID parameter
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid counselor ID']);
        exit();
    }

    $counselorId = (int)$_GET['id'];
    
    // Prepare and execute query to select all relevant counselor details
    // This ensures all data from your counselors table is fetched.
    $stmt = $pdo->prepare("
        SELECT
            id,
            name,
            email,
            phone,           
            specialization,
            image,
            experience,
            rating,
            availability,    
            created_at       
        FROM counselors
        WHERE id = ?
    ");
    $stmt->execute([$counselorId]);
    $counselor = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$counselor) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Counselor not found']);
        exit();
    }

    // Return successful response
    echo json_encode([
        'success' => true,
        'counselor' => $counselor
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Database error in get_counselor.php: " . $e->getMessage()); // Log the error
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    error_log("General error in get_counselor.php: " . $e->getMessage()); // Log the error
    echo json_encode(['success' => false, 'error' => 'An unexpected error occurred: ' . $e->getMessage()]);
}
