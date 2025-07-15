<?php
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

// Function to get JWT from Authorization header (reused)
function get_jwt_from_header() {
    $headers = getallheaders();
    if (isset($headers['Authorization'])) {
        $matches = [];
        if (preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
            return $matches[1];
        }
    }
    return null;
}

// Function to authenticate and authorize the user (reused)
function authenticate_user_from_jwt($pdo) {
    $token = get_jwt_from_header();
    if (!$token) {
        http_response_code(401); // Unauthorized
        echo json_encode(["error" => "Authorization token not provided."]);
        exit();
    }

    $decoded_token = validate_jwt($token);
    if (!$decoded_token) {
        http_response_code(401); // Unauthorized
        echo json_encode(["error" => "Invalid or expired token."]);
        exit();
    }

    // Ensure the token belongs to a 'user' role
    if ($decoded_token->role !== 'user') {
        http_response_code(403); // Forbidden
        echo json_encode(["error" => "Access denied. Only users can manage their learning journey."]);
        exit();
    }

    return $decoded_token->id; // Return authenticated user's ID
}

// 3. Main Logic based on Request Method
$method = $_SERVER['REQUEST_METHOD'];
$user_id = authenticate_user_from_jwt($pdo); // Authenticate for all operations

try {
    switch ($method) {
        case 'GET':
            $stmt = $pdo->prepare("SELECT id, title, platform, start_date, end_date, progress, status, description FROM user_learning_journey WHERE user_id = ? ORDER BY start_date DESC, created_at DESC");
            $stmt->execute([$user_id]);
            $learningItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
            http_response_code(200);
            echo json_encode($learningItems);
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);

            $title = filter_var($data['title'] ?? null, FILTER_SANITIZE_SPECIAL_CHARS);
            $platform = filter_var($data['platform'] ?? null, FILTER_SANITIZE_SPECIAL_CHARS);
            $start_date = filter_var($data['start_date'] ?? null, FILTER_SANITIZE_SPECIAL_CHARS);
            $end_date = filter_var($data['end_date'] ?? null, FILTER_SANITIZE_SPECIAL_CHARS);
            $progress = filter_var($data['progress'] ?? 0, FILTER_VALIDATE_INT);
            $status = filter_var($data['status'] ?? 'Planned', FILTER_SANITIZE_SPECIAL_CHARS);
            $description = filter_var($data['description'] ?? null, FILTER_SANITIZE_SPECIAL_CHARS);

            if (empty($title) || $progress === false || !in_array($status, ['In Progress', 'Completed', 'Planned', 'Dropped'])) {
                http_response_code(400);
                echo json_encode(["error" => "Invalid or missing required learning journey fields (title, progress, status)."]);
                exit();
            }

            // Basic date validation
            if (!empty($start_date) && !preg_match("/^\d{4}-\d{2}-\d{2}$/", $start_date)) {
                http_response_code(400);
                echo json_encode(["error" => "Invalid start_date format. Must be YYYY-MM-DD."]);
                exit();
            }
            if (!empty($end_date) && !preg_match("/^\d{4}-\d{2}-\d{2}$/", $end_date)) {
                http_response_code(400);
                echo json_encode(["error" => "Invalid end_date format. Must be YYYY-MM-DD."]);
                exit();
            }
            if (!empty($start_date) && !empty($end_date) && ($start_date > $end_date)) {
                http_response_code(400);
                echo json_encode(["error" => "Start date cannot be after end date."]);
                exit();
            }


            $stmt = $pdo->prepare("INSERT INTO user_learning_journey (user_id, title, platform, start_date, end_date, progress, status, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $title, $platform, $start_date, $end_date, $progress, $status, $description]);
            $new_id = $pdo->lastInsertId();

            http_response_code(201); // Created
            echo json_encode(["message" => "Learning entry added successfully.", "id" => $new_id]);
            break;

        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
            $title = filter_var($data['title'] ?? null, FILTER_SANITIZE_SPECIAL_CHARS);
            $platform = filter_var($data['platform'] ?? null, FILTER_SANITIZE_SPECIAL_CHARS);
            $start_date = filter_var($data['start_date'] ?? null, FILTER_SANITIZE_SPECIAL_CHARS);
            $end_date = filter_var($data['end_date'] ?? null, FILTER_SANITIZE_SPECIAL_CHARS);
            $progress = filter_var($data['progress'] ?? 0, FILTER_VALIDATE_INT);
            $status = filter_var($data['status'] ?? 'Planned', FILTER_SANITIZE_SPECIAL_CHARS);
            $description = filter_var($data['description'] ?? null, FILTER_SANITIZE_SPECIAL_CHARS);

            if (empty($id) || empty($title) || $progress === false || !in_array($status, ['In Progress', 'Completed', 'Planned', 'Dropped'])) {
                http_response_code(400);
                echo json_encode(["error" => "Invalid or missing required fields for update (id, title, progress, status)."]);
                exit();
            }

            // Basic date validation
            if (!empty($start_date) && !preg_match("/^\d{4}-\d{2}-\d{2}$/", $start_date)) {
                http_response_code(400);
                echo json_encode(["error" => "Invalid start_date format. Must be YYYY-MM-DD."]);
                exit();
            }
            if (!empty($end_date) && !preg_match("/^\d{4}-\d{2}-\d{2}$/", $end_date)) {
                http_response_code(400);
                echo json_encode(["error" => "Invalid end_date format. Must be YYYY-MM-DD."]);
                exit();
            }
            if (!empty($start_date) && !empty($end_date) && ($start_date > $end_date)) {
                http_response_code(400);
                echo json_encode(["error" => "Start date cannot be after end date."]);
                exit();
            }

            // Verify ownership before updating
            $stmt = $pdo->prepare("SELECT user_id FROM user_learning_journey WHERE id = ?");
            $stmt->execute([$id]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$item || $item['user_id'] !== $user_id) {
                http_response_code(403); // Forbidden
                echo json_encode(["error" => "Access denied or learning entry not found."]);
                exit();
            }

            $stmt = $pdo->prepare("UPDATE user_learning_journey SET title = ?, platform = ?, start_date = ?, end_date = ?, progress = ?, status = ?, description = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?");
            $stmt->execute([$title, $platform, $start_date, $end_date, $progress, $status, $description, $id, $user_id]);

            if ($stmt->rowCount() === 0) {
                http_response_code(404); // Not Found or No Change
                echo json_encode(["message" => "No changes made or learning entry not found."]);
            } else {
                http_response_code(200);
                echo json_encode(["message" => "Learning entry updated successfully."]);
            }
            break;

        case 'DELETE':
            $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);

            if (empty($id)) {
                http_response_code(400);
                echo json_encode(["error" => "Learning entry ID is required."]);
                exit();
            }

            // Verify ownership before deleting
            $stmt = $pdo->prepare("SELECT user_id FROM user_learning_journey WHERE id = ?");
            $stmt->execute([$id]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$item || $item['user_id'] !== $user_id) {
                http_response_code(403); // Forbidden
                echo json_encode(["error" => "Access denied or learning entry not found."]);
                exit();
            }

            $stmt = $pdo->prepare("DELETE FROM user_learning_journey WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $user_id]);

            if ($stmt->rowCount() > 0) {
                http_response_code(200);
                echo json_encode(["message" => "Learning entry deleted successfully."]);
            } else {
                http_response_code(404); // Not Found
                echo json_encode(["error" => "Learning entry not found or already deleted."]);
            }
            break;

        default:
            http_response_code(405); // Method Not Allowed
            echo json_encode(["error" => "Method not allowed"]);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Learning Journey API database error: " . $e->getMessage());
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    error_log("Learning Journey API general error: " . $e->getMessage());
    echo json_encode(["error" => "An error occurred: " . $e->getMessage()]);
}
?>