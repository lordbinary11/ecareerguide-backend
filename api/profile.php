<?php
// AI-Career-Project/backend/api/profile.php
// Handles GET (fetch) and PUT (update) requests for user (student) profiles.

// Include CORS helper
require_once __DIR__ . '/cors_helper.php';

header("Content-Type: application/json; charset=UTF-8");

// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Set to 0 for production, but consider logging to file

// Include JWT helper and database connection
require_once __DIR__ . '/jwt_helper.php';
require_once __DIR__ . '/../db_connect.php'; // db_connect.php is one folder UP

// 1. Authenticate and get user details from the token
$headers = apache_request_headers();
$token = get_jwt_from_header($headers); // Uses the helper function

if (!$token) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Authorization token missing."]);
    exit();
}

try {
    $decoded_token = validate_jwt($token); // Uses the helper function

    if (!$decoded_token) {
        http_response_code(401);
        echo json_encode(["success" => false, "message" => "Invalid or expired token. Please log in again."]);
        exit();
    }

    // Ensure the token corresponds to a 'user' role
    if (($decoded_token->role ?? null) !== 'user') {
        http_response_code(403); // Forbidden
        echo json_encode(["success" => false, "message" => "Access denied. Not a user account."]);
        exit();
    }

    $user_id = $decoded_token->id ?? null;
    if (!$user_id) {
        http_response_code(403);
        echo json_encode(["success" => false, "message" => "User ID not found in token."]);
        exit();
    }

    // 2. Handle GET Request (Fetch Profile Data)
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $pdo->prepare("SELECT id, full_name, email, phone, bio, role FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            http_response_code(200);
            echo json_encode(["success" => true, "user" => $user]);
        } else {
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "User not found."]);
        }
        exit();
    }

    // 3. Handle PUT Request (Update Profile Data)
    if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        $data = json_decode(file_get_contents("php://input"), true);

        // Validate required fields for update (adjust as needed)
        $full_name = trim($data['full_name'] ?? ''); // From frontend: values.name maps to full_name for users
        $email = trim($data['email'] ?? '');
        $phone = trim($data['phone'] ?? '');
        $bio = trim($data['bio'] ?? '');

        if (empty($full_name) || empty($email)) { // Basic validation
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Full Name and Email are required."]);
            exit();
        }

        // Basic email format validation (optional, but good practice)
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Invalid email format."]);
            exit();
        }

        // Check if email already exists for another user (if email is being changed)
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $user_id]);
        if ($stmt->fetch()) {
            http_response_code(409); // Conflict
            echo json_encode(["success" => false, "message" => "Email already in use by another account."]);
            exit();
        }

        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, bio = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$full_name, $email, $phone, $bio, $user_id]);

        if ($stmt->rowCount()) {
            http_response_code(200);
            echo json_encode(["success" => true, "message" => "Profile updated successfully."]);
        } else {
            // This can happen if no fields were actually changed, or user ID not found.
            // You might want a more specific message if no changes were made.
            http_response_code(200); // Still return 200 if no change, as the request was technically successful
            echo json_encode(["success" => false, "message" => "No changes made to profile, or user not found."]);
        }
        exit();
    }

    // If an unsupported method is used
    http_response_code(405); // Method Not Allowed
    echo json_encode(["success" => false, "message" => "Method Not Allowed."]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Database error in profile.php: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "Database error.", "error" => $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    error_log("General error in profile.php: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "An unexpected error occurred.", "error" => $e->getMessage()]);
}
?>