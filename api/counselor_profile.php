<?php
// AI-Career-Project/backend/api/counselor_profile.php
// Handles GET (fetch) and PUT (update) requests for counselor profiles.

// Include CORS helper
require_once __DIR__ . '/cors_helper.php';

header('Content-Type: application/json');

// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Set to 0 for production, but consider logging to file

// Include JWT helper and database connection
require_once __DIR__ . '/jwt_helper.php';
require_once __DIR__ . '/../db_connect.php'; // db_connect.php is one folder UP

// Handle OPTIONS preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 1. Authenticate and get counselor details from the token
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

    // Ensure the token corresponds to a 'counselor' role
    if (($decoded_token->role ?? null) !== 'counselor') {
        http_response_code(403); // Forbidden
        echo json_encode(["success" => false, "message" => "Access denied. Not a counselor account."]);
        exit();
    }

    $counselor_id = $decoded_token->id ?? null;
    if (!$counselor_id) {
        http_response_code(403);
        echo json_encode(["success" => false, "message" => "Counselor ID not found in token."]);
        exit();
    }

    // 2. Handle GET Request (Fetch Profile Data)
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $pdo->prepare("SELECT id, name, email, phone, specialization, image, experience, rating, availability, bio FROM counselors WHERE id = ?");
        $stmt->execute([$counselor_id]);
        $counselor = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($counselor) {
            // Format experience_years to int for consistency with frontend InputNumber
            if (isset($counselor['experience'])) {
                $counselor['experience_years'] = (int)$counselor['experience'];
                unset($counselor['experience']); // Remove old key if you prefer
            }
            http_response_code(200);
            echo json_encode(["success" => true, "counselor" => $counselor]);
        } else {
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "Counselor not found."]);
        }
        exit();
    }

    // 3. Handle PUT Request (Update Profile Data)
    if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        $data = json_decode(file_get_contents("php://input"), true);

        // Basic profile fields (from frontend: values.name maps to name for counselors)
        $name = trim($data['name'] ?? '');
        $email = trim($data['email'] ?? '');
        $phone = trim($data['phone'] ?? '');
        $bio = trim($data['bio'] ?? '');

        // Counselor-specific fields
        $specialization = trim($data['specialization'] ?? '');
        $experience_years = filter_var($data['experience'] ?? null, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE); // Backend expects 'experience'
        $availability = trim($data['availability'] ?? '');

        if (empty($name) || empty($email)) { // Basic validation
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Name and Email are required."]);
            exit();
        }

        // Basic email format validation
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Invalid email format."]);
            exit();
        }

        // Check if email already exists for another counselor (if email is being changed)
        $stmt = $pdo->prepare("SELECT id FROM counselors WHERE email = ? AND id != ?");
        $stmt->execute([$email, $counselor_id]);
        if ($stmt->fetch()) {
            http_response_code(409); // Conflict
            echo json_encode(["success" => false, "message" => "Email already in use by another account."]);
            exit();
        }

        $stmt = $pdo->prepare("UPDATE counselors SET name = ?, email = ?, phone = ?, bio = ?, specialization = ?, experience = ?, availability = ?, created_at = NOW() WHERE id = ?"); // Assuming created_at updates on edit too
        $stmt->execute([$name, $email, $phone, $bio, $specialization, $experience_years, $availability, $counselor_id]);

        if ($stmt->rowCount()) {
            http_response_code(200);
            echo json_encode(["success" => true, "message" => "Counselor profile updated successfully."]);
        } else {
            http_response_code(200); // Still return 200 if no change, as the request was technically successful
            echo json_encode(["success" => false, "message" => "No changes made to counselor profile, or counselor not found."]);
        }
        exit();
    }

    // If an unsupported method is used
    http_response_code(405); // Method Not Allowed
    echo json_encode(["success" => false, "message" => "Method Not Allowed."]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Database error in counselor_profile.php: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "Database error.", "error" => $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    error_log("General error in counselor_profile.php: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "An unexpected error occurred.", "error" => $e->getMessage()]);
}
?>