<?php

// Include CORS helper
require_once __DIR__ . '/cors_helper.php';

header('Content-Type: application/json');

// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Set to 0 for production

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/jwt_helper.php';

$data = json_decode(file_get_contents("php://input"), true);
$email = trim($data['email'] ?? '');
$password = trim($data['password'] ?? '');
$loginAs = $data['loginAs'] ?? 'user'; // Default to 'user' if not specified

if (empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Email and password are required"]);
    exit();
}

try {
    $authenticatedEntity = null; // Will hold user or counselor data
    $role = '';

    if ($loginAs === 'user') {
        // Attempt to find user in 'users' table
        $stmt = $pdo->prepare("SELECT id, full_name, email, password, role FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $authenticatedEntity = $user;
            $role = 'user';
        }
    } elseif ($loginAs === 'counselor') {
        // Attempt to find counselor in 'counselors' table
        $stmt = $pdo->prepare("SELECT id, name, email, password FROM counselors WHERE email = ?");
        $stmt->execute([$email]);
        $counselor = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($counselor && password_verify($password, $counselor['password'])) {
            $authenticatedEntity = $counselor;
            $role = 'counselor';
        }
    } else {
        // Invalid loginAs value
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Invalid login role specified."]);
        exit();
    }

    // Check if authentication was successful for either user or counselor
    if ($authenticatedEntity) {
        $payload = [
            "id" => $authenticatedEntity['id'],
            "email" => $authenticatedEntity['email'],
            "role" => $role,
            "exp" => time() + (60 * 60 * 24) // 1 day expiration
        ];

        // Add specific name field based on role for JWT payload
        if ($role === 'user') {
            $payload["full_name"] = $authenticatedEntity['full_name'];
        } elseif ($role === 'counselor') {
            $payload["name"] = $authenticatedEntity['name'];
        }

        $token = generate_jwt($payload);

        if (!$token) {
            throw new Exception("Failed to generate token");
        }

        // Record login in the appropriate table
        if ($role === 'user') {
            $stmt = $pdo->prepare("INSERT INTO login_logs (user_id, login_time) VALUES (?, NOW())");
            $stmt->execute([$authenticatedEntity['id']]);
        } elseif ($role === 'counselor') {
            // Log counselor login to the new counselor_login_logs table
            $stmt = $pdo->prepare("INSERT INTO counselor_login_logs (counselor_id, login_time) VALUES (?, NOW())");
            $stmt->execute([$authenticatedEntity['id']]);
        }


        http_response_code(200);
        $responseData = [
            "success" => true,
            "message" => "Login successful as " . ($role === 'user' ? 'Student' : 'Counselor'),
            "token" => $token,
            "role" => $role // Explicitly send role
        ];

        if ($role === 'user') {
            $responseData["user"] = [
                "id" => $authenticatedEntity['id'],
                "full_name" => $authenticatedEntity['full_name'],
                "email" => $authenticatedEntity['email'],
                "role" => $role
            ];
        } elseif ($role === 'counselor') {
            $responseData["counselor"] = [
                "id" => $authenticatedEntity['id'],
                "name" => $authenticatedEntity['name'],
                "email" => $authenticatedEntity['email'],
                "role" => $role
            ];
        }
        echo json_encode($responseData);
        exit();

    } else {
        // Authentication failed for the selected role
        http_response_code(401);
        echo json_encode(["success" => false, "message" => "Invalid email or password for the selected role."]);
        exit();
    }

} catch (Exception $e) {
    http_response_code(500);
    error_log("Login error: " . $e->getMessage()); // Log detailed error
    echo json_encode([
        "success" => false,
        "message" => "Server error during login",
        "error" => $e->getMessage()
    ]);
    exit();
}
