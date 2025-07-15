<?php
// AI-Career-Project/backend/api/user-profile.php

// Include CORS helper
require_once __DIR__ . '/cors_helper.php';

header('Content-Type: application/json');

// Check if AUTH_USER_ID is defined (meaning it was included by profile.php)
if (!defined('AUTH_USER_ID')) {
    // This block runs if user-profile.php is accessed directly.
    require_once __DIR__ . '/jwt_helper.php';
    require_once __DIR__ . '/../db_connect.php';

    $headers = apache_request_headers();
    if (!isset($headers['Authorization'])) {
        http_response_code(401);
        echo json_encode(["message" => "Missing Authorization Header."]);
        exit();
    }
    $token = str_replace('Bearer ', '', $headers['Authorization']);
    try {
        $decoded = validate_jwt($token);
        if (!$decoded) {
            http_response_code(401);
            echo json_encode(["message" => "Invalid token."]);
            exit();
        }
        $userId = $decoded->id ?? null; // Use 'id' from the token payload
        if (!$userId) {
            http_response_code(403);
            echo json_encode(["message" => "User ID not found in token."]);
            exit();
        }
    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode(["message" => "Invalid token."]);
        exit();
    }
} else {
    // If AUTH_USER_ID is defined, it means profile.php already handled validation
    $userId = AUTH_USER_ID;
    // $pdo variable is already available from db_connect.php which was included by profile.php
}

try {
    // CHANGE IS HERE: Select 'full_name' instead of 'name'
    $stmt = $pdo->prepare("SELECT full_name, email FROM users WHERE id = ?");
    $stmt->execute([$userId]);

    if ($stmt->rowCount()) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        // CHANGE IS HERE: Use 'full_name' from the fetched row
        echo json_encode(["success" => true, "user" => ["full_name" => $row['full_name'], "email" => $row['email']]]);
    } else {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "User not found."]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Database error in user-profile.php: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    error_log("General error in user-profile.php: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "An unexpected error occurred: " . $e->getMessage()]);
}
?>