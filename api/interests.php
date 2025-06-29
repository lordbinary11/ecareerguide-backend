<?php
// AI-Career-Project/backend/api/interests.php
// Handles CRUD operations for user interests.

error_log("interests.php: Script started at top of file. Timestamp: " . date('Y-m-d H:i:s'));

header('Access-Control-Allow-Origin: http://localhost:5173');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');
header('Access-Control-Allow-Credentials: true'); // Important for frontend to send credentials

error_reporting(E_ALL);
ini_set('display_errors', 0); // Set to 0 for production; use 1 only for focused debugging sessions
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../php_error.log'); // Use a consistent error log

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    require_once __DIR__ . '/../db_connect.php'; // db_connect.php is one folder UP
    require_once __DIR__ . '/jwt_helper.php';   // jwt_helper.php is in the same 'api' folder

    // authenticate_user_from_jwt will handle token validation and role check,
    // and exit if validation fails. It returns the user_id on success.
    // The 'false' argument signifies this is a user-specific route.
    error_log("interests.php: Attempting to authenticate user via authenticate_user_from_jwt.");
    $userId = authenticate_user_from_jwt($pdo, false);
    error_log("interests.php: User authenticated with ID: " . $userId);

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Corrected table name to 'user_interests'
        // Removed 'description' column as it doesn't exist in user_interests table
        $stmt = $pdo->prepare("SELECT id, user_id, interest_name FROM user_interests WHERE user_id = ? ORDER BY interest_name ASC");
        $stmt->execute([$userId]);
        $interests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($interests); // Return interests directly as an array
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents("php://input"), true);
        $interestName = trim($data['interest_name'] ?? '');
        // Removed $description as it's not in the table and frontend doesn't send it

        if (empty($interestName)) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Interest name is required."]);
            exit();
        }

        // Check for duplicate interest for the same user
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM user_interests WHERE user_id = ? AND interest_name = ?");
        $stmt_check->execute([$userId, $interestName]);
        if ($stmt_check->fetchColumn() > 0) {
            http_response_code(409); // Conflict
            echo json_encode(["success" => false, "error" => "This interest already exists for your profile."]);
            exit();
        }

        // Corrected table name to 'user_interests'
        // Removed 'description' from INSERT statement, added 'created_at'
        $stmt = $pdo->prepare("INSERT INTO user_interests (user_id, interest_name, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$userId, $interestName]);
        $interestId = $pdo->lastInsertId();

        echo json_encode(["success" => true, "message" => "Interest added successfully.", "id" => $interestId]);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        $data = json_decode(file_get_contents("php://input"), true);
        $interestId = $data['id'] ?? null;
        $interestName = trim($data['interest_name'] ?? '');
        // Removed $description as it's not in the table

        if (empty($interestId) || empty($interestName)) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Interest ID and name are required for update."]);
            exit();
        }

        // Check if updating to a name that already exists for *another* interest of this user
        $stmt_check_duplicate = $pdo->prepare("SELECT id FROM user_interests WHERE user_id = ? AND interest_name = ? AND id != ?");
        $stmt_check_duplicate->execute([$userId, $interestName, $interestId]);
        if ($stmt_check_duplicate->fetchColumn()) {
            http_response_code(409); // Conflict
            echo json_encode(["success" => false, "error" => "Another interest with this name already exists."]);
            exit();
        }


        // Ensure the interest belongs to the authenticated user and corrected table name to 'user_interests'
        // Removed 'description' from UPDATE statement
        $stmt = $pdo->prepare("UPDATE user_interests SET interest_name = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$interestName, $interestId, $userId]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(["success" => true, "message" => "Interest updated successfully."]);
        } else {
            http_response_code(404);
            echo json_encode(["success" => false, "error" => "Interest not found or not owned by user, or no changes made."]);
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $interestId = $_GET['id'] ?? null;

        if (empty($interestId)) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Interest ID is required for deletion."]);
            exit();
        }

        // Ensure the interest belongs to the authenticated user and corrected table name to 'user_interests'
        $stmt = $pdo->prepare("DELETE FROM user_interests WHERE id = ? AND user_id = ?");
        $stmt->execute([$interestId, $userId]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(["success" => true, "message" => "Interest deleted successfully."]);
        } else {
            http_response_code(404);
            echo json_encode(["success" => false, "error" => "Interest not found or not owned by user."]);
        }
    } else {
        http_response_code(405);
        echo json_encode(["success" => false, "error" => "Method not allowed."]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    error_log("interests.php PDO error: " . $e->getMessage());
    echo json_encode(["success" => false, "error" => "Database error: " . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    error_log("interests.php general error: " . $e->getMessage());
    echo json_encode(["success" => false, "error" => "Server error: " . $e->getMessage()]);
}
?>