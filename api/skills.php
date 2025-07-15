<?php
// AI-Career-Project/backend/api/skills.php
// Handles CRUD operations for user skills.

error_reporting(E_ALL);
ini_set('display_errors', 0); // Set to 0 for production
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../php_error.log');

// Include CORS helper
require_once __DIR__ . '/cors_helper.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Credentials: true'); // Crucial for sending cookies/auth headers

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    require_once __DIR__ . '/../db_connect.php';
    require_once __DIR__ . '/jwt_helper.php';

    global $pdo;

    // Only 'user' role can manage skills
    // authenticate_user_from_jwt will handle token validation and role check,
    // and exit if validation fails. It returns the user_id on success.
    // The false argument signifies this is a user-specific route.
    $user_id = authenticate_user_from_jwt($pdo, false);

    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'GET':
            // Corrected table name: user_skills
            // Corrected column name: proficiency (from proficiency_level)
            $stmt = $pdo->prepare("SELECT id, skill_name, proficiency FROM user_skills WHERE user_id = ? ORDER BY skill_name ASC");
            $stmt->execute([$user_id]);
            $skills = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($skills); // Return array directly
            break;

        case 'POST':
            $data = json_decode(file_get_contents("php://input"), true);
            $skill_name = trim($data['skill_name'] ?? '');
            $proficiency = trim($data['proficiency'] ?? ''); // Frontend sends 'proficiency', matches DB

            if (empty($skill_name)) {
                http_response_code(400);
                echo json_encode(["success" => false, "error" => "Skill name is required."]);
                exit();
            }

            // Check for duplicate skill for the same user
            // Corrected table name: user_skills
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM user_skills WHERE user_id = ? AND skill_name = ?");
            $stmt_check->execute([$user_id, $skill_name]);
            if ($stmt_check->fetchColumn() > 0) {
                http_response_code(409); // Conflict
                echo json_encode(["success" => false, "error" => "This skill already exists for your profile."]);
                exit();
            }

            // Corrected table name: user_skills
            // Corrected column name: proficiency (from proficiency_level)
            $stmt = $pdo->prepare("INSERT INTO user_skills (user_id, skill_name, proficiency, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
            $stmt->execute([$user_id, $skill_name, $proficiency]);
            http_response_code(201); // Created
            echo json_encode(["success" => true, "message" => "Skill added successfully.", "id" => $pdo->lastInsertId()]);
            break;

        case 'PUT':
            $data = json_decode(file_get_contents("php://input"), true);
            $id = $data['id'] ?? null;
            $skill_name = trim($data['skill_name'] ?? '');
            $proficiency = trim($data['proficiency'] ?? ''); // Frontend sends 'proficiency', matches DB

            if (empty($id) || empty($skill_name)) {
                http_response_code(400);
                echo json_encode(["success" => false, "error" => "Skill ID and name are required."]);
                exit();
            }

            // Check if updating to a name that already exists for *another* skill of this user
            // Corrected table name: user_skills
            $stmt_check_duplicate = $pdo->prepare("SELECT id FROM user_skills WHERE user_id = ? AND skill_name = ? AND id != ?");
            $stmt_check_duplicate->execute([$user_id, $skill_name, $id]);
            if ($stmt_check_duplicate->fetchColumn()) {
                http_response_code(409); // Conflict
                echo json_encode(["success" => false, "error" => "Another skill with this name already exists."]);
                exit();
            }

            // Corrected table name: user_skills
            // Corrected column name: proficiency (from proficiency_level)
            $stmt = $pdo->prepare("UPDATE user_skills SET skill_name = ?, proficiency = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
            $stmt->execute([$skill_name, $proficiency, $id, $user_id]);

            if ($stmt->rowCount() > 0) {
                echo json_encode(["success" => true, "message" => "Skill updated successfully."]);
            } else {
                http_response_code(404); // Not found or no changes
                echo json_encode(["success" => false, "error" => "Skill not found or no changes made."]);
            }
            break;

        case 'DELETE':
            $id = $_GET['id'] ?? null; // DELETE requests often use query parameters for ID
            if (empty($id)) {
                http_response_code(400);
                echo json_encode(["success" => false, "error" => "Skill ID is required."]);
                exit();
            }

            // Corrected table name: user_skills
            $stmt = $pdo->prepare("DELETE FROM user_skills WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $user_id]);

            if ($stmt->rowCount() > 0) {
                echo json_encode(["success" => true, "message" => "Skill deleted successfully."]);
            } else {
                http_response_code(404);
                echo json_encode(["success" => false, "error" => "Skill not found or you do not have permission."]);
            }
            break;

        default:
            http_response_code(405); // Method Not Allowed
            echo json_encode(["success" => false, "error" => "Method not allowed."]);
            break;
    }

} catch (PDOException $e) {
    http_response_code(500);
    error_log("skills.php PDO error: " . $e->getMessage());
    echo json_encode(["success" => false, "error" => "Database error: " . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    error_log("skills.php general error: " . $e->getMessage());
    echo json_encode(["success" => false, "error" => "Server error: " . $e->getMessage()]);
}
?>