<?php
// Fetches the list of counselors

// CORS Headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization"); // Added Authorization header
header('Content-Type: application/json');

// Handle requests (OPTIONS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Set to 0 for production

require '../db_connect.php'; // Database connection

try {
    // Fetch all counselors from the database, explicitly listing all columns
    // This ensures all data filled during registration is retrieved.
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
        ORDER BY created_at DESC -- Order by creation date to show new counselors first
    ");
    $stmt->execute();
    $counselors = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "counselors" => $counselors
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Database Error in get_counselors.php: " . $e->getMessage()); // Log the specific database error
    echo json_encode([
        "success" => false,
        "error" => "Database error: " . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    error_log("General Error in get_counselors.php: " . $e->getMessage()); // Log any other unexpected errors
    echo json_encode([
        "success" => false,
        "error" => "An unexpected error occurred: " . $e->getMessage()
    ]);
}
