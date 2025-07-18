<?php
// backend/api/student_activity.php

// Enable error reporting for debugging (disable in production)
// ini_set('display_errors', 1); // Set to 1 for development to see errors directly
// error_reporting(E_ALL);
ini_set('display_errors', 0); // Keep at 0 for production, errors should be logged
error_reporting(E_ALL); // Log all errors

// Include CORS helper
require_once __DIR__ . '/cors_helper.php';

header('Content-Type: application/json');

// Handle OPTIONS requests (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(); // Exit immediately after sending preflight headers
}

// 2. Database Connection and JWT Helper
require_once __DIR__ . '/../db_connect.php'; // Path to your PDO database connection
require_once __DIR__ . '/jwt_helper.php';   // Path to your JWT helper functions

// --- IMPORTANT FIX HERE ---
// Declare the global $pdo variable established by db_connect.php
global $pdo;

// Check if $pdo object was successfully created by db_connect.php
if (!isset($pdo) || !$pdo instanceof PDO) {
    http_response_code(500);
    error_log("Student Activity API error: PDO connection not established by db_connect.php");
    echo json_encode(["success" => false, "error" => "Database connection error."]);
    exit();
}

try {
    // Authenticate the counselor. Pass 'true' to indicate this is a counselor route.
    // jwt_helper.php's authenticate_user_from_jwt should handle role checking.
    // Ensure authenticate_user_from_jwt accepts $pdo as an argument.
    $counselor_id = authenticate_user_from_jwt($pdo, true); // Enforces counselor role for this endpoint

    // Define activity thresholds
    $currently_active_threshold = date('Y-m-d H:i:s', strtotime('-5 minutes'));
    $daily_active_threshold = date('Y-m-d 00:00:00');

    // Query for currently active students (within the last 5 minutes)
    $stmt_online = $pdo->prepare("
        SELECT COUNT(id) AS count
        FROM users
        WHERE role = 'user' AND last_activity_at >= ?
    ");
    $stmt_online->execute([$currently_active_threshold]);
    $currently_active_count = $stmt_online->fetch(PDO::FETCH_ASSOC)['count'];

    // Query for daily active students (since the start of today)
    $stmt_daily = $pdo->prepare("
        SELECT COUNT(DISTINCT id) AS count
        FROM users
        WHERE role = 'user' AND last_activity_at >= ?
    ");
    $stmt_daily->execute([$daily_active_threshold]);
    $daily_active_count = $stmt_daily->fetch(PDO::FETCH_ASSOC)['count'];

    http_response_code(200);
    echo json_encode([
        "success" => true,
        "currently_active_students" => (int)$currently_active_count,
        "daily_active_students" => (int)$daily_active_count
    ]);

} catch (PDOException $e) {
    error_log("Student Activity API database error: " . $e->getMessage());
    error_log("PDOException Trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Database error: " . $e->getMessage()]);
} catch (Exception $e) {
    error_log("Student Activity API general error: " . $e->getMessage());
    error_log("Exception Trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "An error occurred: " . $e->getMessage()]);
}
?>