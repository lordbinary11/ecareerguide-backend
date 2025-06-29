<?php
// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1); // Set to 1 for debugging, 0 for production

// 1. CORS Headers
header('Access-Control-Allow-Origin: http://localhost:5173'); // Adjust for your frontend domain
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');
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

// NOTE: The get_jwt_from_header() and authenticate_user_from_jwt() functions
// are now defined in jwt_helper.php and should NOT be redefined here.
// We are now just CALLING the authenticate_user_from_jwt function from jwt_helper.php.

// 3. Main Logic based on Request Method
$method = $_SERVER['REQUEST_METHOD'];
$user_id = null; // Initialize user_id

try {
    // Authenticate user for all methods that require it.
    // This is a user-facing route, so we pass 'false' to indicate it's not a counselor route,
    // which will trigger the update of the user's last_activity_at timestamp.
    $user_id = authenticate_user_from_jwt($pdo, false);

    switch ($method) {
        case 'GET':
            // Fetch the user's latest resume (or null if none)
            $stmt = $pdo->prepare("SELECT * FROM resumes WHERE user_id = ? ORDER BY updated_at DESC LIMIT 1");
            $stmt->execute([$user_id]);
            $resume = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($resume) {
                http_response_code(200);
                echo json_encode($resume);
            } else {
                http_response_code(200); // OK, but no content
                echo json_encode(["message" => "No resume found for this user."]);
            }
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);

            // Basic validation
            if (empty($data['resume_name'])) {
                http_response_code(400);
                echo json_encode(["error" => "Resume name is required."]);
                exit();
            }

            // Insert new resume
            $stmt = $pdo->prepare("INSERT INTO resumes (user_id, resume_name, personal_info, summary_objective, education_json, experience_json, skills_json, projects_json, awards_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $user_id,
                $data['resume_name'],
                $data['personal_info'] ?? '[]',
                $data['summary_objective'] ?? '',
                $data['education_json'] ?? '[]',
                $data['experience_json'] ?? '[]',
                $data['skills_json'] ?? '[]',
                $data['projects_json'] ?? '[]',
                $data['awards_json'] ?? '[]'
            ]);

            $new_resume_id = $pdo->lastInsertId();
            http_response_code(201); // Created
            echo json_encode(["message" => "Resume created successfully!", "id" => $new_resume_id]);
            break;

        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            $resume_id = $_GET['id'] ?? null; // Get resume ID from query parameter

            if (!$resume_id) {
                http_response_code(400);
                echo json_encode(["error" => "Resume ID is required for update."]);
                exit();
            }
            if (empty($data['resume_name'])) {
                http_response_code(400);
                echo json_encode(["error" => "Resume name is required."]);
                exit();
            }

            // Verify that the resume belongs to the authenticated user
            $stmt = $pdo->prepare("SELECT user_id FROM resumes WHERE id = ?");
            $stmt->execute([$resume_id]);
            $resume_owner_id = $stmt->fetchColumn();

            if (!$resume_owner_id || $resume_owner_id != $user_id) {
                http_response_code(403); // Forbidden
                echo json_encode(["error" => "You do not have permission to update this resume."]);
                exit();
            }

            // Update existing resume
            $stmt = $pdo->prepare("UPDATE resumes SET resume_name = ?, personal_info = ?, summary_objective = ?, education_json = ?, experience_json = ?, skills_json = ?, projects_json = ?, awards_json = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?");
            $stmt->execute([
                $data['resume_name'],
                $data['personal_info'] ?? '[]',
                $data['summary_objective'] ?? '',
                $data['education_json'] ?? '[]',
                $data['experience_json'] ?? '[]',
                $data['skills_json'] ?? '[]',
                $data['projects_json'] ?? '[]',
                $data['awards_json'] ?? '[]',
                $resume_id,
                $user_id
            ]);

            if ($stmt->rowCount() > 0) {
                http_response_code(200);
                echo json_encode(["message" => "Resume updated successfully!", "id" => $resume_id]);
            } else {
                http_response_code(404); // Not Found or no changes made
                echo json_encode(["message" => "Resume not found or no changes were made."]);
            }
            break;

        case 'DELETE':
            $resume_id = $_GET['id'] ?? null;

            if (!$resume_id) {
                http_response_code(400);
                echo json_encode(["error" => "Resume ID is required for deletion."]);
                exit();
            }

            // Verify that the resume belongs to the authenticated user
            $stmt = $pdo->prepare("SELECT user_id FROM resumes WHERE id = ?");
            $stmt->execute([$resume_id]);
            $resume_owner_id = $stmt->fetchColumn();

            if (!$resume_owner_id || $resume_owner_id != $user_id) {
                http_response_code(403); // Forbidden
                echo json_encode(["error" => "You do not have permission to delete this resume."]);
                exit();
            }

            $stmt = $pdo->prepare("DELETE FROM resumes WHERE id = ? AND user_id = ?");
            $stmt->execute([$resume_id, $user_id]);

            if ($stmt->rowCount() > 0) {
                http_response_code(200);
                echo json_encode(["message" => "Resume deleted successfully!"]);
            } else {
                http_response_code(404); // Not Found
                echo json_encode(["message" => "Resume not found or already deleted."]);
            }
            break;

        default:
            http_response_code(405); // Method Not Allowed
            echo json_encode(["error" => "Method not allowed"]);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Resume API database error: " . $e->getMessage());
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    error_log("Resume API general error: " . $e->getMessage());
    echo json_encode(["error" => "An error occurred: " . $e->getMessage()]);
}
?>
