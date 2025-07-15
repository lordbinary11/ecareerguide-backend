<?php
// backend/api/get_meeting_token.php - TEMPORARY DEBUGGING VERSION

// Include CORS helper
require_once __DIR__ . '/cors_helper.php';

header('Content-Type: application/json'); // Keep as JSON for consistent response structure

// Handle preflight (OPTIONS) requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// *** IMPORTANT: THIS IS FOR DEBUGGING ONLY! DO NOT USE IN PRODUCTION! ***

// Dump all server variables and headers to the response
$debug_output = [
    "message" => "--- TEMPORARY DEBUG OUTPUT (CHECK 'Authorization' IN HEADERS OR SERVER ARRAY) ---",
    "SERVER_VARS" => $_SERVER, // This will show all $_SERVER variables
    "GET_PARAMS" => $_GET,
    "getallheaders_output" => function_exists('getallheaders') ? getallheaders() : "getallheaders() not available"
];

// Output as JSON and exit immediately
echo json_encode($debug_output, JSON_PRETTY_PRINT);
exit();

// The rest of your original get_meeting_token.php code would go here normally,
// but it's commented out/removed for this temporary debug version.
// You will replace this file with the correct version after this debugging step.

?>
