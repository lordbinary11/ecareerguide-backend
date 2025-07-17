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
$role = $data['role'] ?? 'user'; // 'user' or 'counselor'

// Determine the name field based on the role
$name_input = '';
if ($role === 'user') {
    $name_input = trim($data['full_name'] ?? '');
} elseif ($role === 'counselor') {
    $name_input = trim($data['name'] ?? ''); // Expect 'name' for counselors
}

// Basic validation for common fields
if (empty($name_input) || empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "All fields (name, email, password) are required"]);
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Invalid email format"]);
    exit();
}

if (strlen($password) < 6) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Password must be at least 6 characters long"]);
    exit();
}

try {
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    $table = '';
    $nameColumn = '';
    $insertId = null;

    if ($role === 'user') {
        $table = 'users';
        $nameColumn = 'full_name';
    } elseif ($role === 'counselor') {
        $table = 'counselors';
        $nameColumn = 'name'; // Counselors table uses 'name'
    } else {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Invalid role specified"]);
        exit();
    }

    // Check if email already exists in the respective table
    $stmt = $pdo->prepare("SELECT id FROM $table WHERE email = ?");
    $stmt->execute([$email]);

    if ($stmt->rowCount() > 0) {
        http_response_code(409); // Conflict
        echo json_encode(["success" => false, "message" => "Email already exists for this role."]);
        exit();
    }

    // Insert new user/counselor
    if ($role === 'user') {
        $stmt = $pdo->prepare("INSERT INTO $table ($nameColumn, email, password, role, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$name_input, $email, $hashedPassword, $role]);
    } else { // 'counselor'
        $phone = trim($data['phone'] ?? null);
        $specialization = trim($data['specialization'] ?? null);
        $experience = $data['experience'] ?? null; // Should be numeric
        $availability = $data['availability'] ?? null;
        // Accept both string and array for backward compatibility
        if (is_array($availability) || is_object($availability)) {
            $availability = json_encode($availability);
        }
        // Basic validation for counselor-specific fields
        if (empty($phone) || empty($specialization) || !is_numeric($experience) || empty($availability)) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "All counselor fields (phone, specialization, experience, availability) are required."]);
            exit();
        }
        // If availability is a JSON array, check it's not empty
        $decodedAvailability = json_decode($availability, true);
        if (json_last_error() === JSON_ERROR_NONE && (empty($decodedAvailability) || !is_array($decodedAvailability))) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Availability must be a non-empty array."]);
            exit();
        }

        // 'image' and 'rating' are omitted for initial signup, will default to NULL/0
        $stmt = $pdo->prepare("
            INSERT INTO $table (
                $nameColumn, email, password, phone, specialization, experience, availability, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $name_input, $email, $hashedPassword, $phone, $specialization, $experience, $availability
        ]);
    }

    $insertId = $pdo->lastInsertId();

    echo json_encode([
        "success" => true,
        "message" => "Registration successful as $role! You can now log in.",
        "id" => $insertId,
        "role" => $role
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Registration database error: " . $e->getMessage()); // Log detailed error
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    error_log("Registration general error: " . $e->getMessage()); // Log detailed error
    echo json_encode(["success" => false, "message" => "An unexpected error occurred: " . $e->getMessage()]);
}
