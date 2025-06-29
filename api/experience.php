<?php
error_log("experience.php: Script started at top of file. Timestamp: " . date('Y-m-d H:i:s'));

header('Access-Control-Allow-Origin: http://localhost:5173');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

error_reporting(E_ALL);
ini_set('display_errors', 1); // TEMPORARY: Set to 1 for debugging, set to 0 for production!
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../php_error.log'); // Use a consistent error log

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    require_once __DIR__ . '/../db_connect.php';
    require_once __DIR__ . '/jwt_helper.php';

    error_log("experience.php: Attempting to authenticate user via authenticate_user_from_jwt.");
    $userId = authenticate_user_from_jwt($pdo, false); // 'false' indicates user-facing route
    error_log("experience.php: User authenticated with ID: " . $userId);

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Corrected table name to 'user_experience'
        $stmt = $pdo->prepare("SELECT id, user_id, title, company, location, start_date, end_date, description FROM user_experience WHERE user_id = ?");
        $stmt->execute([$userId]);
        $experience = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($experience); // Return experience entries directly as an array
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents("php://input"), true);
        $title = trim($data['title'] ?? '');
        $company = trim($data['company'] ?? '');
        $location = trim($data['location'] ?? '');
        $startDate = $data['start_date'] ?? null; // YYYY-MM-DD
        $endDate = $data['end_date'] ?? null;     // YYYY-MM-DD, can be null
        $description = trim($data['description'] ?? '');

        if (empty($title) || empty($company) || empty($startDate)) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Title, company, and start date are required."]);
            exit();
        }

        // Corrected table name to 'user_experience'
        $stmt = $pdo->prepare("INSERT INTO user_experience (user_id, title, company, location, start_date, end_date, description) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $title, $company, $location, $startDate, $endDate, $description]);
        $experienceId = $pdo->lastInsertId();

        echo json_encode(["success" => true, "message" => "Experience entry added successfully.", "id" => $experienceId]);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        $data = json_decode(file_get_contents("php://input"), true);
        $experienceId = $data['id'] ?? null;
        $title = trim($data['title'] ?? '');
        $company = trim($data['company'] ?? '');
        $location = trim($data['location'] ?? '');
        $startDate = $data['start_date'] ?? null;
        $endDate = $data['end_date'] ?? null;
        $description = trim($data['description'] ?? '');

        if (empty($experienceId) || empty($title) || empty($company) || empty($startDate)) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Experience ID, title, company, and start date are required for update."]);
            exit();
        }

        // Ensure the experience entry belongs to the authenticated user and corrected table name to 'user_experience'
        $stmt = $pdo->prepare("UPDATE user_experience SET title = ?, company = ?, location = ?, start_date = ?, end_date = ?, description = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$title, $company, $location, $startDate, $endDate, $description, $experienceId, $userId]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(["success" => true, "message" => "Experience entry updated successfully."]);
        } else {
            http_response_code(404);
            echo json_encode(["success" => false, "error" => "Experience entry not found or not owned by user."]);
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $experienceId = $_GET['id'] ?? null;

        if (empty($experienceId)) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Experience ID is required for deletion."]);
            exit();
        }

        // Ensure the experience entry belongs to the authenticated user and corrected table name to 'user_experience'
        $stmt = $pdo->prepare("DELETE FROM user_experience WHERE id = ? AND user_id = ?");
        $stmt->execute([$experienceId, $userId]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(["success" => true, "message" => "Experience deleted successfully."]);
        } else {
            http_response_code(404);
            echo json_encode(["success" => false, "error" => "Experience entry not found or not owned by user."]);
        }
    } else {
        http_response_code(405);
        echo json_encode(["success" => false, "error" => "Method not allowed."]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    error_log("experience.php PDO error: " . $e->getMessage());
    echo json_encode(["success" => false, "error" => "Database error: " . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    error_log("experience.php general error: " . $e->getMessage());
    echo json_encode(["success" => false, "error" => "Server error: " . $e->getMessage()]);
}
