<?php
// backend/api/send_message.php

// Enable full error reporting for logging, but disable display for API output.
error_reporting(E_ALL);
ini_set('display_errors', 0); // Crucial: Set to 0 to prevent warnings/errors from appearing in API response body

// Include CORS helper
require_once __DIR__ . '/cors_helper.php';

header('Content-Type: application/json'); // Crucial: tell the client to expect JSON

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

    // Authenticate the user. This is a user-facing route,
    // so we pass 'false' to update their last_activity_at timestamp.
    $user_id_from_auth = authenticate_user_from_jwt($pdo, false);

    // Get the raw POST data
    $json_data = file_get_contents("php://input");
    // Decode the JSON data into a PHP associative array
    $data = json_decode($json_data, true);

    // Check if JSON decoding failed or if data is not an array
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        http_response_code(400); // Bad Request
        echo json_encode(["success" => false, "error" => "Invalid JSON input."]);
        exit();
    }

    // Extract data, using null coalescing operator for safety
    // user_id is now taken from authentication, not input data
    $counselor_id = $data['counselor_id'] ?? null;
    $message_text = $data['message'] ?? null;

    // Validate required fields. The user_id comes from authentication.
    if (!$counselor_id || !trim($message_text)) {
        http_response_code(400); // Bad Request
        echo json_encode(["success" => false, "error" => "All fields (counselor_id, message) are required."]);
        exit();
    }

    // Insert the message into the database
    $stmt = $pdo->prepare("INSERT INTO messages (user_id, counselor_id, message, status, timestamp) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([
        $user_id_from_auth,
        $counselor_id,
        $message_text,
        'sent' // Use 'sent' instead of 'unread' to match allowed status values
    ]);

    // Check if the message was successfully inserted
    if ($stmt->rowCount() > 0) {
        $message_id = $pdo->lastInsertId(); // Get the ID of the newly inserted message
        echo json_encode([
            "success" => true,
            "message" => "Message sent successfully.",
            "message_id" => $message_id, // Return the new message ID
            "timestamp" => date("Y-m-d H:i:s") // Return current timestamp for client-side update
        ]);
    } else {
        http_response_code(500); // Internal Server Error
        echo json_encode(["success" => false, "error" => "Failed to insert message into database."]);
    }
    exit();

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Database Error in send_message.php: " . $e->getMessage());
    echo json_encode(["success" => false, "error" => "Database error: " . $e->getMessage()]);
    exit();
} catch (Exception $e) {
    http_response_code(500);
    error_log("General Error in send_message.php: " . $e->getMessage());
    echo json_encode(["success" => false, "error" => "An unexpected error occurred: " . $e->getMessage()]);
    exit();
}
