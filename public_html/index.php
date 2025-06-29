<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$response = [
    'status' => 'success',
    'message' => 'ECareerGuide API is running',
    'version' => '1.0.0',
    'endpoints' => [
        'auth' => [
            'POST /api/login.php' => 'User login',
            'POST /api/register.php' => 'User registration',
            'POST /api/debug_login.php' => 'Debug login endpoint'
        ],
        'user' => [
            'GET /api/profile.php' => 'Get user profile',
            'PUT /api/profile.php' => 'Update user profile',
            'GET /api/dashboard.php' => 'Get user dashboard'
        ],
        'counselors' => [
            'GET /api/get_counselors.php' => 'Get all counselors',
            'GET /api/get_counselor.php' => 'Get specific counselor'
        ],
        'meetings' => [
            'POST /api/schedule_meeting.php' => 'Schedule a meeting',
            'GET /api/get_meeting_details.php' => 'Get meeting details'
        ],
        'ai' => [
            'POST /api/ask-ai.php' => 'AI chat endpoint',
            'POST /api/ai_insights.php' => 'Get AI insights'
        ]
    ],
    'base_url' => 'https://' . $_SERVER['HTTP_HOST'],
    'timestamp' => date('Y-m-d H:i:s')
];

echo json_encode($response, JSON_PRETTY_PRINT);
?> 