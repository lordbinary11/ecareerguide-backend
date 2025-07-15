<?php
// backend/api/optimize_document.php

// Enable full error reporting for logging, but disable display for API output.
error_reporting(E_ALL);
ini_set('display_errors', 0); // Set to 0 for production

// Include CORS helper
require_once __DIR__ . '/cors_helper.php';

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

    // Authenticate the user. This is a user-facing route.
    // We pass 'false' to update their last_activity_at timestamp.
    $user_id_from_auth = authenticate_user_from_jwt($pdo, false);

    // Get the raw POST data
    $json_data = file_get_contents("php://input");
    $data = json_decode($json_data, true);

    // Check if JSON decoding failed or if data is not an array
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        http_response_code(400); // Bad Request
        echo json_encode(["success" => false, "error" => "Invalid JSON input."]);
        exit();
    }

    // Extract input data
    $user_document = trim($data['user_document'] ?? ''); // User's resume or cover letter text
    $job_description = trim($data['job_description'] ?? ''); // Target job description text
    $document_type = $data['document_type'] ?? 'resume'; // 'resume' or 'cover_letter'
    $job_title = trim($data['job_title'] ?? 'General Role'); // Specific job title (optional, for context)
    $company_name = trim($data['company_name'] ?? 'Specific Company'); // Company name (optional, for context)

    // Validate essential fields
    if (empty($user_document) || empty($job_description)) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Both your document and the job description are required for analysis."]);
        exit();
    }

    // IMPORTANT: Storing API keys directly in code is NOT recommended for production.
    // Consider using environment variables or a secure configuration management system.
    $apiKey = "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"; // Your OpenRouter API Key

    // Construct the prompt for the AI model
    $system_prompt = "You are an expert career advisor and resume/cover letter analyst. Your task is to provide constructive, actionable feedback to a job seeker on how to optimize their document for a specific job description. Focus on content relevance, keywords, action verbs, measurable achievements, and overall impact. Provide feedback in a structured JSON format with distinct categories like 'Keywords', 'ActionVerbs', 'Achievements', 'Tailoring', 'Clarity', 'Suggestions'. Each category should have an array of specific feedback points. If a category is not applicable, use an empty array. Do not include a conversational preamble or conclusion outside the JSON.";

    $user_prompt = "Critique the following " . $document_type . " for a '" . $job_title . "' position at '" . $company_name . "'. Provide feedback on keywords, action verbs, quantifiable achievements, and overall relevance. Suggest specific improvements to align it better with the job description.\n\n--- User's " . ucfirst($document_type) . " ---\n" . $user_document . "\n\n--- Job Description ---\n" . $job_description . "\n\nProvide the feedback in JSON format.";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://openrouter.ai/api/v1/chat/completions");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $apiKey",
        "Content-Type: application/json",
        "HTTP-Referer: http://localhost", // Replace with your actual domain in production
        "X-Title: AI-CAREER-PROJECT"    // Your application title
    ]);

    $body = [
        "model" => "mistralai/mistral-7b-instruct", // Or a more powerful model like 'openai/gpt-4o' if available/suitable
        "messages" => [
            ["role" => "system", "content" => $system_prompt],
            ["role" => "user", "content" => $user_prompt]
        ],
        "response_format" => ["type" => "json_object"] // Request JSON response explicitly
    ];

    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));

    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
        http_response_code(500);
        echo json_encode(["success" => false, "error" => "cURL error: " . $error_msg]);
        exit;
    }

    if ($httpcode !== 200) {
        $error_response_data = json_decode($response, true);
        $error_message = $error_response_data['message'] ?? 'Unknown error from OpenRouter.ai';
        http_response_code($httpcode);
        echo json_encode(["success" => false, "error" => "OpenRouter.ai request failed with status code: $httpcode - " . $error_message]);
        exit;
    } else {
        $responseData = json_decode($response, true);
        $ai_reply_content = $responseData["choices"][0]["message"]["content"] ?? null;

        if ($ai_reply_content) {
            // Attempt to decode the AI's JSON reply
            $feedback = json_decode($ai_reply_content, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                http_response_code(200);
                echo json_encode(["success" => true, "feedback" => $feedback]);
            } else {
                // If AI did not return valid JSON, return its raw text and an error
                http_response_code(500);
                error_log("AI returned non-JSON content: " . $ai_reply_content);
                echo json_encode(["success" => false, "error" => "AI response was not valid JSON.", "raw_ai_response" => $ai_reply_content]);
            }
        } else {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => "AI did not provide a reply."]);
        }
    }

    curl_close($ch);

} catch (Exception $e) {
    http_response_code(500);
    error_log("Document Optimization API general error: " . $e->getMessage());
    echo json_encode(["success" => false, "error" => "An internal server error occurred: " . $e->getMessage()]);
}
?>
