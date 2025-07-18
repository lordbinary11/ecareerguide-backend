<?php
// AI-Career-Project/backend/api/jwt_helper.php

// Error logging setup (can be removed in production after debugging)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Set to 0 for production, temporarily set to 1 if you need more verbose errors
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../php_error.log'); // Custom log file in backend root

error_log("jwt_helper.php: Script execution started. Timestamp: " . date('Y-m-d H:i:s'));

// The path to autoload.php should be one level up, in the 'backend' directory
$autoloadPath = __DIR__ . '/../vendor/autoload.php';

if (!file_exists($autoloadPath)) {
    error_log("FATAL ERROR: jwt_helper.php: vendor/autoload.php not found at " . $autoloadPath . ". Please run 'composer install' in your backend directory.");
    // This die() will terminate PHP execution and send a JSON error to the frontend
    http_response_code(500); // Internal Server Error
    die(json_encode(["error" => "Server configuration error: Required PHP dependencies not installed. Please contact support and ensure Composer dependencies are installed."]));
}

error_log("jwt_helper.php: Attempting to require autoload.php.");
require_once $autoloadPath;
error_log("jwt_helper.php: After autoload.php. JWT Class exists: " . (class_exists('Firebase\\JWT\\JWT') ? 'Yes' : 'No'));

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

error_log("jwt_helper.php: Before JWT_SECRET define.");
if (!defined('JWT_SECRET')) {
    // *** IMPORTANT: THIS IS THE STANDARDIZED SECRET KEY. USE THIS EXACT STRING. ***
    // For production, you should generate a truly random and unique key.
    define('JWT_SECRET', 'Munet@05');
}
error_log("jwt_helper.php: After JWT_SECRET define. Key defined: " . (defined('JWT_SECRET') ? 'Yes' : 'No'));

error_log("jwt_helper.php: Before generate_jwt function definition.");
/**
 * Generates a JSON Web Token (JWT).
 *
 * @param array $payload The data to be encoded in the token (e.g., user ID, role).
 * @param int $expiration_minutes The duration in minutes until the token expires.
 * @return string|null The generated JWT or null on error.
 */
function generate_jwt(array $payload, int $expiration_minutes = 1440): ?string { // Default 1 day expiration
    error_log("generate_jwt: Function called.");
    $issued_at = time();
    $expiration_time = $issued_at + ($expiration_minutes * 60); // exp in seconds

    $token_payload = array_merge($payload, [
        'iat' => $issued_at, // Issued at
        'exp' => $expiration_time, // Expiration time
        'nbf' => $issued_at // Not before
    ]);

    try {
        // Use HS256 algorithm
        return JWT::encode($token_payload, JWT_SECRET, 'HS256');
    } catch (Exception $e) {
        error_log("JWT Generation Error: " . $e->getMessage());
        return null;
    }
}
error_log("jwt_helper.php: After generate_jwt function definition.");

error_log("jwt_helper.php: Before validate_jwt function definition.");
/**
 * Validates a JSON Web Token (JWT).
 *
 * @param string $jwt The JWT string to validate.
 * @return object|null The decoded token payload if valid, otherwise null.
 */
function validate_jwt(string $jwt): ?object {
    error_log("validate_jwt: Function called.");
    if (empty($jwt)) {
        error_log("JWT Validation Error: JWT is empty.");
        return null;
    }

    try {
        // Decode the token using HS256 algorithm and the secret key
        // Note: For Firebase/JWT v6.0+, Key class is used for the secret.
        $decoded = JWT::decode($jwt, new Key(JWT_SECRET, 'HS256'));
        return $decoded;
    } catch (Exception $e) {
        // Log different types of JWT exceptions for better debugging
        if ($e instanceof \Firebase\JWT\SignatureInvalidException) {
            error_log("JWT Validation Error: Invalid signature - " . $e->getMessage());
        } elseif ($e instanceof \Firebase\JWT\ExpiredException) {
            error_log("JWT Validation Error: Token expired - " . $e->getMessage());
        } elseif ($e instanceof \Firebase\JWT\BeforeValidException) {
            error_log("JWT Validation Error: Token not yet valid - " . $e->getMessage());
        } else {
            error_log("JWT Validation Error: " . $e->getMessage());
        }
        return null;
    }
}
error_log("jwt_helper.php: After validate_jwt function definition.");

error_log("jwt_helper.php: Before get_jwt_from_header function definition.");
/**
 * Retrieves the JWT from the Authorization header.
 *
 * @return string|null The JWT string if found, otherwise null.
 */
function get_jwt_from_header(): ?string {
    error_log("get_jwt_from_header: Function called.");

    // Check apache_request_headers() first (common for Apache)
    $headers = apache_request_headers(); // getallheaders() is an alias for apache_request_headers()
    if (isset($headers['Authorization'])) {
        $matches = [];
        if (preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
            error_log("get_jwt_from_header: Found token in Authorization header.");
            return $matches[1];
        }
    }

    // Fallback for Nginx or other environments where Authorization might be in $_SERVER
    // or if getallheaders() isn't working as expected.
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $matches = [];
        if (preg_match('/Bearer\s(\S+)/', $_SERVER['HTTP_AUTHORIZATION'], $matches)) {
            error_log("get_jwt_from_header: Found token in HTTP_AUTHORIZATION server variable.");
            return $matches[1];
        }
    }

    error_log("get_jwt_from_header: No Authorization token found in headers or server variables.");
    return null;
}
error_log("jwt_helper.php: After get_jwt_from_header function definition.");

error_log("jwt_helper.php: About to define authenticate_user_from_jwt function.");
/**
 * Authenticates and authorizes the user based on JWT, and optionally updates last activity.
 *
 * @param PDO $pdo The PDO database connection object.
 * @param bool $is_counselor_route Set to true if the current API endpoint is meant only for counselors.
 * @return int The authenticated user/counselor ID. Exits with JSON error response if authentication fails.
 * @throws Exception if an unexpected error occurs during JWT processing.
 */
function authenticate_user_from_jwt(PDO $pdo, bool $is_counselor_route = false): int {
    error_log("authenticate_user_from_jwt: Function called successfully.");

    $token = get_jwt_from_header();
    if (!$token) {
        error_log("authenticate_user_from_jwt: No token provided in Authorization header.");
        http_response_code(401); // Unauthorized
        echo json_encode(["error" => "Authorization token not provided."]);
        exit();
    }

    $decoded_token = validate_jwt($token); // <--- Using validate_jwt here
    if (!$decoded_token) {
        error_log("authenticate_user_from_jwt: Token is invalid or expired.");
        http_response_code(401); // Unauthorized
        echo json_encode(["error" => "Invalid or expired token."]);
        exit();
    }

    $user_id = $decoded_token->id ?? null;
    $role = $decoded_token->role ?? null;

    if (!$user_id || !$role) {
        error_log("authenticate_user_from_jwt: Token payload missing ID or role. Payload: " . json_encode($decoded_token));
        http_response_code(401);
        echo json_encode(["error" => "Incomplete token payload (missing ID or role)."]);
        exit();
    }

    if ($is_counselor_route) {
        if ($role !== 'counselor') {
            error_log("authenticate_user_from_jwt: Access denied. Role '$role' tried to access counselor-only endpoint.");
            http_response_code(403); // Forbidden
            echo json_encode(["error" => "Access denied. Only counselors can access this resource."]);
            exit();
        }
    } else { // This is a regular user route
        if ($role !== 'user') { // Assuming 'user' is the role for regular users
            error_log("authenticate_user_from_jwt: Access denied. Role '$role' tried to access user-only endpoint.");
            http_response_code(403); // Forbidden
            echo json_encode(["error" => "Access denied. Only students/users can access this resource."]);
            exit();
        }

        // Only update last_activity_at for 'user' role
        try {
            $stmt = $pdo->prepare("UPDATE users SET last_activity_at = NOW() WHERE id = ?");
            $stmt->execute([$user_id]);
        } catch (PDOException $e) {
            error_log("Failed to update user last_activity_at for user_id $user_id: " . $e->getMessage());
            // Do not exit here, as this is a background update and should not break the main request
        }
    }

    return (int)$user_id; // Cast to int to ensure correct type is returned
}
error_log("jwt_helper.php: After authenticate_user_from_jwt function definition.");
?>