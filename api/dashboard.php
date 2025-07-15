<?php

// Include CORS helper
require_once __DIR__ . '/cors_helper.php';

header('Content-Type: application/json');

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/jwt_helper.php';
require_once __DIR__ . '/auth_middleware.php';

try {
    // This will validate the token and return user data if valid
    $decoded = authenticate();

    // Fetch additional user data if needed
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$decoded->user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        throw new Exception("User not found");
    }

    // Return dashboard data
    http_response_code(200);
    echo json_encode([
        "success" => true,
        "message" => "Dashboard data retrieved",
        "user" => [
            "id" => $user['id'],
            "full_name" => $user['full_name'],
            "email" => $user['email'],
            "role" => $user['role']
        ],
        "stats" => [ 
            "last_login" => "2023-06-15", 
            "career_tests_taken" => 3
        ]
    ]);

} catch (Exception $e) {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "message" => "Unauthorized",
        "error" => $e->getMessage()
    ]);
}