<?php

// Include CORS helper
require_once __DIR__ . '/cors_helper.php';

header('Content-Type: application/json');

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/jwt_helper.php';
require_once __DIR__ . '/auth_middleware.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $decoded = authenticate();
    echo json_encode([
        "success" => true,
        "user_id" => $decoded->user_id,
        "message" => "Token is valid"
    ]);
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}