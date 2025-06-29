<?php
// backend/api/get_messages.php

// Enable full error reporting for logging, but disable display for API output.
error_reporting(E_ALL);
ini_set('display_errors', 0); // Crucial: Set to 0 to prevent warnings/errors from appearing in API response body

// Set CORS headers
header('Access-Control-Allow-Origin: http://localhost:5173'); // Adjust for your frontend domain
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization"); // Ensure Authorization header is allowed
header('Content-Type: application/json');

// Handle pre-flight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    require_once __DIR__ . '/../db_connect.php'; // Include your database connection
    require_once __DIR__ . '/jwt_helper.php';   // Include your JWT helper functions

    // Global PDO object from db_connect.php
    global $pdo;

    // Parameters for specific user-counselor conversation
    $counselor_id_param = $_GET['counselor_id'] ?? null;
    $user_id_param = $_GET['user_id'] ?? null;

    // Parameters for counselor inbox (CounselorInbox.jsx)
    $is_counselor_inbox_request = isset($_GET['is_counselor_inbox']) && $_GET['is_counselor_inbox'] === 'true';
    $counselor_logged_in_id = $_GET['counselor_logged_in_id'] ?? null; // ID of the counselor currently logged in

    $messages = [];
    $sql = "";
    $params = [];

    $authenticated_id = null;
    $authenticated_role = null;

    // Determine authentication context and perform authentication
    if ($is_counselor_inbox_request) {
        // Authenticate as a counselor
        $authenticated_id = authenticate_user_from_jwt($pdo, true);
        $authenticated_role = 'counselor';

        // Additional validation: ensure the authenticated counselor ID matches the requested counselor_logged_in_id
        if ($authenticated_id != $counselor_logged_in_id) {
            http_response_code(403); // Forbidden
            echo json_encode(["success" => false, "error" => "Access denied. Counselor ID mismatch."]);
            exit();
        }
    } else {
        // Authenticate as a regular user (default for non-counselor inbox requests)
        $authenticated_id = authenticate_user_from_jwt($pdo, false);
        $authenticated_role = 'user';

        // Additional validation: ensure the authenticated user ID matches the requested user_id_param
        if ($authenticated_id != $user_id_param) {
            http_response_code(403); // Forbidden
            echo json_encode(["success" => false, "error" => "Access denied. User ID mismatch."]);
            exit();
        }
    }


    // Scenario 1: Fetch messages for a specific user-counselor chat (used by MessageCounselor.jsx)
    // This requires both counselor_id and user_id to be present.
    if ($counselor_id_param && $user_id_param && $authenticated_role === 'user' && $authenticated_id == $user_id_param) {
        $sql = "
            SELECT
                m.id,
                m.user_id,
                m.counselor_id,
                m.message,
                m.reply,
                m.replied_at,
                m.status,
                m.timestamp,
                u.full_name AS user_full_name,
                c.name AS counselor_name
            FROM messages m
            JOIN users u ON m.user_id = u.id
            JOIN counselors c ON m.counselor_id = c.id
            WHERE m.counselor_id = ? AND m.user_id = ?
            ORDER BY m.timestamp ASC
        ";
        $params = [$counselor_id_param, $user_id_param];
    }
    // Scenario 2: Fetch messages for a counselor's inbox (used by CounselorInbox.jsx)
    // This requires the is_counselor_inbox flag and the logged-in counselor's ID.
    else if ($is_counselor_inbox_request && $counselor_logged_in_id && $authenticated_role === 'counselor' && $authenticated_id == $counselor_logged_in_id) {
        $sql = "
            SELECT
                m.id,
                m.user_id,
                m.counselor_id,
                m.message,
                m.reply,
                m.replied_at,
                m.status,
                m.timestamp,
                u.full_name AS student_name, -- Alias for CounselorInbox
                c.name AS counselor_name,
                u.email AS student_email, -- Added for CounselorInbox.jsx
                u.last_activity_at -- Added for student activity tracking
            FROM messages m
            JOIN users u ON m.user_id = u.id
            JOIN counselors c ON m.counselor_id = c.id
            WHERE m.counselor_id = ?
            ORDER BY m.timestamp DESC
        ";
        $params = [$counselor_logged_in_id];
    }
    // Scenario 3: Invalid request - no specific filtering parameters or role mismatch
    else {
        http_response_code(400); // Bad Request
        echo json_encode(["success" => false, "error" => "Invalid request: Missing required parameters or role mismatch for message filtering."]);
        exit();
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Mark messages as read if viewing a specific user-counselor conversation (Scenario 1)
    // Only mark as read if it's a direct chat being viewed by the counselor.
    // In this API, if a user is viewing their own chat, they are the 'user'.
    // Messages *from the counselor* to this user, that are unread, should be marked as read by the user.
    // However, the current logic is to mark *user's messages* as read (which is typically done by the counselor when they read it).
    // Let's adjust this for clarity and typical chat behavior.
    // When a user fetches messages, they are marking messages *sent TO them by the counselor* as read.
    if ($counselor_id_param && $user_id_param && $authenticated_role === 'user') {
        // Mark messages sent by the counselor to this user as 'read'
        $unread_counselor_messages_to_mark_read = array_column(array_filter($messages, function($msg) use ($counselor_id_param, $user_id_param) {
            return $msg['status'] === 'unread' && $msg['counselor_id'] == $counselor_id_param && $msg['user_id'] == $user_id_param && empty($msg['reply']); // Assuming original message by counselor has no reply
        }), 'id');

        if (!empty($unread_counselor_messages_to_mark_read)) {
            $placeholders = implode(',', array_fill(0, count($unread_counselor_messages_to_mark_read), '?'));
            $updateStmt = $pdo->prepare("
                UPDATE messages
                SET status = 'read'
                WHERE id IN ($placeholders)
            ");
            $updateStmt->execute($unread_counselor_messages_to_mark_read);
            // After updating, update the status in the $messages array for immediate response consistency
            foreach ($messages as &$msg) {
                if (in_array($msg['id'], $unread_counselor_messages_to_mark_read)) {
                    $msg['status'] = 'read';
                }
            }
            unset($msg); // Unset reference
        }
    }


    echo json_encode(["success" => true, "messages" => $messages]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Database error in get_messages.php: " . $e->getMessage());
    echo json_encode(["success" => false, "error" => "Database error", "debug" => $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500); // general server errors
    error_log("General Error in get_messages.php: " . $e->getMessage());
    echo json_encode(["success" => false, "error" => "An unexpected error occurred", "debug" => $e->getMessage()]);
}
?>
