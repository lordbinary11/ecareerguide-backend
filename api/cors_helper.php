<?php
// cors_helper.php - Centralized CORS configuration

// Get the origin from the request
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

// Log the incoming origin for debugging
error_log('CORS request from origin: ' . $origin);

// Define allowed origins
$allowed_origins = [
    'http://localhost:5173',           // Local development (Vite)
    'http://localhost:3000',           // Local development (React)
    'http://localhost:8080',           // Local development (Docker)
    'https://ecareerguide-frontend.onrender.com',  // Production frontend
    'https://ecareerguide.vercel.app', // Alternative production frontend
    'https://ecareerguide.netlify.app' // Alternative production frontend
];

// Check if the origin is allowed
$is_allowed_origin = in_array($origin, $allowed_origins);

// Set CORS headers
if ($is_allowed_origin) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    // For development/testing, allow all origins
    // In production, you might want to be more restrictive
    header("Access-Control-Allow-Origin: *");
}

// Set other CORS headers
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Max-Age: 86400"); // 24 hours

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
?> 