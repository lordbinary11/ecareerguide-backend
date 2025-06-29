<?php
// backend/api/virtual_meet.php - For scheduling VIRTUAL meetings using Agora

// Enable error reporting for debugging (disable in production)
ini_set('display_errors', 0); // Keep at 0 for production, errors should be logged
error_reporting(E_ALL); // Log all errors
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../php_error.log'); // Log file in the 'backend' folder

// 1. CORS Headers - Must be at the very top before any output
header('Access-Control-Allow-Origin: http://localhost:5173');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json');

// Handle preflight requests (OPTIONS) - Must exit here
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 2. Database Connection and JWT Helper
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/jwt_helper.php';

// IMPORTANT: Use Ramsey\Uuid for generating unique meeting links (UUIDs for Agora channels)
use Ramsey\Uuid\Uuid; 

global $pdo;

if (!isset($pdo) || !$pdo instanceof PDO) {
    http_response_code(500);
    error_log("Virtual Meet API error: PDO connection not established by db_connect.php");
    echo json_encode(["success" => false, "error" => "Database connection error."]);
    exit();
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(["success" => false, "error" => "Method not allowed. Only POST requests are accepted."]);
        exit();
    }

    // Authenticate the user (student/general user)
    $user_id_from_auth = authenticate_user_from_jwt($pdo, false); // 'false' implies student/general user

    $data = json_decode(file_get_contents("php://input"), true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        http_response_code(400);
        error_log("virtual_meet.php: Invalid JSON input. Error: " . json_last_error_msg());
        echo json_encode(["success" => false, "error" => "Invalid JSON input."]);
        exit();
    }

    // Extract data from frontend: counselor_id, schedule_date (datetime string), and purpose
    // Adapting to the frontend's current payload structure
    $counselor_id = $data['counselor_id'] ?? null;
    $schedule_datetime_str = $data['schedule_date'] ?? null; // 'YYYY-MM-DD HH:mm:ss'
    $purpose = $data['purpose'] ?? null;
    // For Agora, we typically just need a unique channel name (UUID). 
    // The duration_minutes isn't strictly used by Agora's token generation itself, 
    // but it might be useful for your scheduling logic. We'll set a default.
    // REMOVED $duration_minutes variable as it's not being stored in DB
    // $duration_minutes = 60; // Defaulting to 60 minutes for Agora meetings

    if (empty($counselor_id) || empty($schedule_datetime_str) || empty($purpose)) { // Corrected check
        http_response_code(400);
        $missingFields = [];
        if (empty($counselor_id)) $missingFields[] = 'counselor_id';
        if (empty($schedule_datetime_str)) $missingFields[] = 'schedule_date';
        if (empty($purpose)) $missingFields[] = 'purpose';
        error_log("virtual_meet.php: Missing required fields: " . implode(', ', $missingFields));
        echo json_encode(["success" => false, "error" => "Missing required fields: " . implode(', ', $missingFields)]);
        exit();
    }

    // --- Get user_email and full_name from authenticated user_id ---
    $user_email = null;
    $stmt_user_email = $pdo->prepare("SELECT email, full_name FROM users WHERE id = ?");
    $stmt_user_email->execute([$user_id_from_auth]);
    $user_info = $stmt_user_email->fetch(PDO::FETCH_ASSOC);
    if ($user_info) {
        $user_email = $user_info['email'];
        $student_name = $user_info['full_name'] ?? $user_email;
    } else {
        http_response_code(400);
        error_log("virtual_meet.php: Authenticated user email not found for ID: " . $user_id_from_auth);
        echo json_encode(["success" => false, "error" => "Authenticated user email not found."]);
        exit();
    }

    // --- Get counselor_email from counselor_id ---
    $counselor_email = null;
    $stmt_counselor_email = $pdo->prepare("SELECT email FROM counselors WHERE id = ?");
    $stmt_counselor_email->execute([$counselor_id]);
    $counselor_info = $stmt_counselor_email->fetch(PDO::FETCH_ASSOC);
    if ($counselor_info) {
        $counselor_email = $counselor_info['email'];
    } else {
        http_response_code(400);
        error_log("virtual_meet.php: Counselor email not found for provided ID: " . $counselor_id);
        echo json_encode(["success" => false, "error" => "Counselor email not found for provided ID."]);
        exit();
    }

    // Split the schedule_datetime_str into date and time components for database storage
    $date_time_parts = explode(' ', $schedule_datetime_str);
    $schedule_date = $date_time_parts[0] ?? null; //YYYY-MM-DD
    $schedule_time = $date_time_parts[1] ?? null; //HH:mm:ss

    if (empty($schedule_date) || empty($schedule_time)) { // Corrected check
        http_response_code(400);
        error_log("virtual_meet.php: Invalid date/time format provided. Expected YYYY-MM-DD HH:mm:ss. Received: " . $schedule_datetime_str);
        echo json_encode(["success" => false, "error" => "Invalid date/time format provided. Expected YYYY-MM-DD HH:mm:ss."]);
        exit();
    }

    // --- Generate Agora Meeting Link (UUID for channel name) ---
    $meeting_link = Uuid::uuid4()->toString(); // Generates a UUID V4 (e.g., "123e4567-e89b-12d3-a456-426614174000")
    
    $meeting_platform = 'agora'; // Set platform to Agora
    $is_virtual_meet = true;     // Always true for this API

    error_log("virtual_meet.php: Generated Agora meeting_link (channel name): " . $meeting_link);


    // --- Insert into your 'scheduled_meetings' table ---
    $stmt_meeting = $pdo->prepare("
        INSERT INTO scheduled_meetings (
            user_email,
            counselor_email,
            schedule_date,
            schedule_time,
            purpose,
            meeting_link,
            is_virtual_meet,
            meeting_platform,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt_meeting->execute([
        $user_email,
        $counselor_email,
        $schedule_date,
        $schedule_time,
        $purpose,
        $meeting_link,         // Pass the generated Agora UUID for channel name
        $is_virtual_meet,      // Always true
        $meeting_platform      // Now 'agora'
        // REMOVED $duration_minutes from here
    ]);


    if ($stmt_meeting->rowCount() > 0) {
        $meeting_id = $pdo->lastInsertId(); // Get the ID of the newly scheduled meeting

        // --- Insert a notification for the counselor ---
        // Assuming counselors also have entries in the 'users' table to receive notifications
        $counselor_user_id_for_notification = null;
        $stmt_counselor_user_id = $pdo->prepare("SELECT id FROM users WHERE email = ?"); // Assuming users table holds notification recipients
        $stmt_counselor_user_id->execute([$counselor_email]);
        $counselor_user_data = $stmt_counselor_user_id->fetch(PDO::FETCH_ASSOC);
        if ($counselor_user_data) { // TYPO FIX: Changed from $counseler_user_data
            $counselor_user_id_for_notification = $counselor_user_data['id'];
        }

        if ($counselor_user_id_for_notification) {
            $notification_message = "New VIRTUAL meeting scheduled by {$student_name} for " . date('Y-m-d H:i', strtotime($schedule_datetime_str)) . ". Purpose: " . $purpose . ". Join link: " . $meeting_link;

            $stmt_notification = $pdo->prepare("
                INSERT INTO notifications (user_id, sender_id, type, message, related_id, is_read)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt_notification->execute([
                $counselor_user_id_for_notification, // The user ID of the counselor (from users table)
                $user_id_from_auth,                  // The student's user ID
                'new_virtual_meeting_schedule',      // Type of notification
                $notification_message,
                $meeting_id,                         // Link to the scheduled_meeting (DB ID)
                0                                    // Not read yet
            ]);
        } else {
            error_log("virtual_meet.php: Counselor user ID not found in 'users' table for notification. Counselor Email: " . $counselor_email);
        }

        http_response_code(201);
        echo json_encode([
            "success" => true,
            "message" => "Virtual meeting scheduled successfully and counselor notified!",
            "meeting_id" => $meeting_id,           // Send back the database ID of the scheduled meeting
            "meeting_link" => $meeting_link,       // Send back the Agora channel UUID
            "is_virtual_meet" => true,
            "meeting_platform" => $meeting_platform  // Confirm it's 'agora'
        ]);
    } else {
        http_response_code(500);
        error_log("virtual_meet.php: Failed to insert meeting into database for user " . $user_email);
        echo json_encode(["success" => false, "error" => "Failed to insert meeting into database."]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Virtual Meet API database error: " . $e->getMessage());
    echo json_encode(["success" => false, "error" => "Database error: " . $e->getMessage()]);
} catch (Exception $e) {
    error_log("Virtual Meet API general error: " . $e->getMessage());
    if (strpos($e->getMessage(), 'Authorization token') !== false || strpos($e->getMessage(), 'Invalid token') !== false || strpos($e->getMessage(), 'Token expired') !== false) {
        http_response_code(401);
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    } else {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => "An unexpected error occurred: " . $e->getMessage()]);
    }
}
?>
