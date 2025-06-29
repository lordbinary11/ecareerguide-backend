<?php
// backend/api/get_meeting_details.php

// 1. CORS Headers
header('Access-Control-Allow-Origin: http://localhost:5173');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json');

// Handle preflight (OPTIONS) requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 2. Error Reporting
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../php_error.log');

// Log script start
error_log("get_meeting_details.php: Script execution started. Timestamp: " . date('Y-m-d H:i:s'));

// 3. Include Autoloader and database/JWT helpers
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/jwt_helper.php';

global $pdo;

if (!isset($pdo) || !$pdo instanceof PDO) {
    http_response_code(500);
    error_log("get_meeting_details.php: Database connection error. PDO object not available.");
    echo json_encode(["success" => false, "error" => "Database connection error."]);
    exit();
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(["success" => false, "error" => "Method not allowed. Only GET requests are accepted."]);
        exit();
    }

    // Authenticate the user
    $authenticated_user_id = authenticate_user_from_jwt($pdo, false); 
    error_log("get_meeting_details.php: Authenticated User ID: " . $authenticated_user_id);

    // Get the meeting ID from the URL query parameter
    $meetingId = $_GET['meeting_id'] ?? null;
    error_log("get_meeting_details.php: Received meeting_id: " . ($meetingId ?? 'NULL'));


    if (empty($meetingId)) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Meeting ID is required."]);
        exit();
    }

    // !! IMPORTANT FIX: REMOVED (int) CAST !!
    // The meetingId is now expected to be a UUID string, so we don't cast it to integer.
    error_log("get_meeting_details.php: Using meeting_id as string (UUID): " . $meetingId);


    // --- IMPORTANT SECURITY STEP: Verify User's Access to this Meeting ---
    // Query the database using the meeting_id (which is a UUID string)
    $stmt = $pdo->prepare("SELECT meeting_link, user_email, counselor_email FROM scheduled_meetings WHERE id = ?"); // Assuming 'id' column stores the UUID
    $stmt->execute([$meetingId]);
    $meeting_details = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$meeting_details) {
        error_log("get_meeting_details.php: Meeting not found for ID: " . $meetingId);
        http_response_code(404);
        echo json_encode(["success" => false, "error" => "Meeting not found for this ID."]);
        exit();
    }
    // Log the meeting_link (which should now be the Agora channel name) before returning
    error_log("get_meeting_details.php: Meeting found. Fetched meeting_link (channel name): " . $meeting_details['meeting_link']);

    // Get the email of the authenticated user from their decoded JWT
    $auth_user_email = null;
    $token_from_header = get_jwt_from_header();
    $decoded_jwt = validate_jwt($token_from_header);

    if ($decoded_jwt && isset($decoded_jwt->email)) {
        $auth_user_email = $decoded_jwt->email;
    } else {
        $stmt_user_email = $pdo->prepare("
            (SELECT email FROM users WHERE id = ?)
            UNION
            (SELECT email FROM counselors WHERE id = ?)
        ");
        $stmt_user_email->execute([$authenticated_user_id, $authenticated_user_id]);
        $fetched_email = $stmt_user_email->fetchColumn();
        if ($fetched_email) {
            $auth_user_email = $fetched_email;
        } else {
            error_log("get_meeting_details.php: Could not retrieve authenticated user's email for ID: " . $authenticated_user_id);
            http_response_code(500);
            echo json_encode(["success" => false, "error" => "Could not retrieve authenticated user's email."]);
            exit();
        }
    }
    error_log("get_meeting_details.php: Authenticated user email: " . $auth_user_email);


    // Check if the authenticated user's email is one of the participants
    if ($auth_user_email !== $meeting_details['user_email'] && $auth_user_email !== $meeting_details['counselor_email']) {
        error_log("get_meeting_details.php: Access denied for user " . $auth_user_email . " to meeting ID " . $meetingId);
        http_response_code(403); // Forbidden
        echo json_encode(["success" => false, "error" => "Access denied. You are not authorized to view this meeting."]);
        exit();
    }
    error_log("get_meeting_details.php: User authorized to view meeting ID: " . $meetingId);

    // Return the Agora channel name (stored as meeting_link)
    echo json_encode([
        'success' => true,
        'meeting_link' => $meeting_details['meeting_link'] // This now contains the Agora channel name (UUID)
    ]);

} catch (PDOException $e) {
    error_log("get_meeting_details.php: Database error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Database error: " . $e->getMessage()]);
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Authorization token') !== false || strpos($e->getMessage(), 'Invalid token') !== false || strpos($e->getMessage(), 'Token expired') !== false) {
        http_response_code(401);
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    } else {
        http_response_code(500);
        error_log("get_meeting_details.php: General error: " . $e->getMessage());
        echo json_encode(["success" => false, "error" => "An unexpected error occurred: " . $e->getMessage()]);
    }
}
