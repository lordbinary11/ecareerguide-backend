<?php
// backend/api/schedule_meeting.php

// Enable error reporting for debugging (disable in production)
// ini_set('display_errors', 1); // For development
// error_reporting(E_ALL);
ini_set('display_errors', 0); // Keep at 0 for production, errors should be logged
error_reporting(E_ALL); // Log all errors

// Include CORS helper
require_once __DIR__ . '/cors_helper.php';

header('Content-Type: application/json');

// Handle preflight requests (OPTIONS) - Must exit here
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 2. Database Connection and JWT Helper
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/jwt_helper.php';

global $pdo;

if (!isset($pdo) || !$pdo instanceof PDO) {
    http_response_code(500);
    error_log("Schedule Meeting API error: PDO connection not established by db_connect.php");
    echo json_encode(["success" => false, "error" => "Database connection error."]);
    exit();
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(["success" => false, "error" => "Method not allowed. Only POST requests are accepted."]);
        exit();
    }

    // Authenticate the user. This will fetch the user's ID and also update their activity.
    // For scheduling, we expect a student/user's token.
    $user_id_from_auth = authenticate_user_from_jwt($pdo, false); // 'false' implies student/general user

    $data = json_decode(file_get_contents("php://input"), true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Invalid JSON input."]);
        exit();
    }

    // Extract data from frontend, assuming it sends counselor_id, selectedDate (datetime string), and purpose
    $counselor_id = $data['counselor_id'] ?? null;
    $schedule_datetime_str = $data['schedule_date'] ?? null; // This will be 'YYYY-MM-DD HH:mm:ss'
    $purpose = $data['purpose'] ?? null; // Assuming purpose is still sent from frontend

    if (!$counselor_id || !$schedule_datetime_str || !$purpose) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Counselor ID, schedule date/time, and purpose are required."]);
        exit();
    }

    // --- Get user_email from authenticated user_id ---
    $user_email = null;
    $stmt_user_email = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $stmt_user_email->execute([$user_id_from_auth]);
    $user_info = $stmt_user_email->fetch(PDO::FETCH_ASSOC);
    if ($user_info) {
        $user_email = $user_info['email'];
    } else {
        http_response_code(400);
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
        echo json_encode(["success" => false, "error" => "Counselor email not found for provided ID."]);
        exit();
    }

    // Split the schedule_datetime_str into date and time components
    $date_time_parts = explode(' ', $schedule_datetime_str);
    $schedule_date = $date_time_parts[0] ?? null; // YYYY-MM-DD
    $schedule_time = $date_time_parts[1] ?? null; // HH:mm:ss

    if (!$schedule_date || !$schedule_time) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Invalid date/time format provided. Expected YYYY-MM-DD HH:mm:ss."]);
        exit();
    }

    // --- Insert into your 'scheduled_meetings' table ---
    $stmt_meeting = $pdo->prepare("
        INSERT INTO scheduled_meetings (user_email, counselor_email, schedule_date, schedule_time, purpose)
        VALUES (?, ?, ?, ?, ?)
    ");
    // Assuming 'purpose' is a new column you'll add or temporarily map.
    // If 'purpose' is not a column in 'scheduled_meetings', you must remove it from here.
    $stmt_meeting->execute([$user_email, $counselor_email, $schedule_date, $schedule_time, $purpose]);


    if ($stmt_meeting->rowCount() > 0) {
        $meeting_id = $pdo->lastInsertId(); // Get the ID of the newly scheduled meeting

        // --- Retrieve student's name for notification message ---
        $student_name = $user_info['name'] ?? $user_email; // Use name if available, else email
        
        // --- Insert a notification for the counselor ---
        // Note: The 'user_id' in the notifications table should be the ID of the user receiving the notification (counselor's user ID).
        // You'll need to fetch the counselor's user ID if counselors are also in the 'users' table,
        // or ensure 'user_id' in notifications table can link to counselor_id.
        // For simplicity, let's assume 'user_id' in notifications refers to the primary 'users' table ID.
        // If your counselors are in a separate 'counselors' table and don't have an entry in 'users' table with the same ID,
        // you might need to adjust the FK or how notifications are targeted.
        // For now, I'll use the counselor_id for notifications.user_id if counselors are linked to users.
        // If counselors are NOT in the 'users' table, and 'notifications.user_id' MUST reference 'users.id',
        // then you'll need to reconsider how counselor notifications are stored or add a user_id column to counselors table.

        // Assuming counselors *are* linked to user accounts in the 'users' table, or have an 'id' that matches 'users.id' for notifications:
        // You might need to fetch the counselor's actual 'user_id' if they are users in the 'users' table
        $counselor_user_id_for_notification = null;
        $stmt_counselor_user_id = $pdo->prepare("SELECT id FROM users WHERE email = ?"); // Assuming counselor email exists in users table
        $stmt_counselor_user_id->execute([$counselor_email]);
        $counselor_user_data = $stmt_counselor_user_id->fetch(PDO::FETCH_ASSOC);
        if ($counselor_user_data) {
            $counselor_user_id_for_notification = $counselor_user_data['id'];
        }

        if ($counselor_user_id_for_notification) {
            $notification_message = "New meeting scheduled by {$student_name} for " . date('Y-m-d H:i', strtotime($schedule_datetime_str)) . ". Purpose: " . $purpose;
            $stmt_notification = $pdo->prepare("
                INSERT INTO notifications (user_id, sender_id, type, message, related_id, is_read)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt_notification->execute([
                $counselor_user_id_for_notification, // The user ID of the counselor (from users table)
                $user_id_from_auth,                  // The student's user ID
                'new_meeting_schedule',              // Type of notification
                $notification_message,
                $meeting_id,                         // Link to the scheduled_meeting
                0                                    // Not read yet
            ]);
        } else {
             error_log("Counselor user ID not found for notification. Counselor Email: " . $counselor_email);
        }

        http_response_code(201);
        echo json_encode(["success" => true, "message" => "Meeting scheduled successfully and counselor notified!"]);
    } else {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => "Failed to insert meeting into database."]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Schedule Meeting API database error: " . $e->getMessage());
    echo json_encode(["success" => false, "error" => "Database error: " . $e->getMessage()]);
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Authorization token') !== false || strpos($e->getMessage(), 'Invalid token') !== false) {
        http_response_code(401);
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    } else {
        http_response_code(500);
        error_log("Schedule Meeting API general error: " . $e->getMessage());
        echo json_encode(["success" => false, "error" => "An unexpected error occurred: " . $e->getMessage()]);
    }
}
?>