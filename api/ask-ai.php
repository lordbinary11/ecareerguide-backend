<?php
// backend/api/ask-ai.php

// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Set to 0 for production

// Include CORS helper
require_once __DIR__ . '/cors_helper.php';

header('Content-Type: application/json');

// Handle OPTIONS requests (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 2. Database Connection and JWT Helper
require_once __DIR__ . '/../db_connect.php'; // Path to your PDO database connection
require_once __DIR__ . '/jwt_helper.php';   // Path to your JWT helper functions

// Global PDO object from db_connect.php
global $pdo;

try {
    // Authenticate the user. Pass 'false' to indicate this is a user route,
    // which will also update their last_activity_at timestamp.
    $user_id = authenticate_user_from_jwt($pdo, false); // Enforces user role and updates last_activity_at

    $data = json_decode(file_get_contents("php://input"), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid JSON received"]);
        exit;
    }

    if (!isset($data["message"]) || empty(trim($data["message"]))) {
        http_response_code(400);
        echo json_encode(["error" => "No message provided"]);
        exit;
    }

    $user_message = trim($data["message"]);

    // IMPORTANT: Storing API keys directly in code is NOT recommended for production.
    // Consider using environment variables or a secure configuration management system.
    $apiKey = "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"; // Your OpenRouter API Key

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, "https://openrouter.ai/api/v1/chat/completions");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $apiKey",
        "Content-Type: application/json",
        "HTTP-Referer: http://localhost", // Replace with your actual domain in production
        "X-Title: AI-CAREER-PROJECT" // Your application title
    ]);

    $body = [
        "model" => "mistralai/mistral-7b-instruct", // Or any other model you prefer on OpenRouter.ai
        "messages" => [
            ["role" => "system", "content" => "You are a helpful career guidance assistant. Provide concise and direct answers."],
            ["role" => "user", "content" => $user_message]
        ]
    ];

    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));

    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
        http_response_code(500);
        echo json_encode(["error" => "cURL error: " . $error_msg]);
        exit;
    }

    if ($httpcode !== 200) {
        $error_response_data = json_decode($response, true);
        $error_message = $error_response_data['message'] ?? 'Unknown error from OpenRouter.ai';
        http_response_code($httpcode); // Pass through the original HTTP status code
        echo json_encode(["error" => "OpenRouter.ai request failed with status code: $httpcode - " . $error_message]);
    } else {
        $responseData = json_decode($response, true);
        if (isset($responseData["choices"][0]["message"]["content"])) {
            echo json_encode(["reply" => $responseData["choices"][0]["message"]["content"]]);
        } else {
            http_response_code(500);
            echo json_encode(["error" => "Unexpected response structure from OpenRouter.ai.", "raw_response" => $responseData]);
        }
    }

    curl_close($ch);

} catch (Exception $e) {
    http_response_code(500);
    error_log("Ask AI API general error: " . $e->getMessage());
    echo json_encode(["error" => "An internal server error occurred: " . $e->getMessage()]);
}
?>
