<?php
error_log("education.php: Script started at top of file. Timestamp: " . date('Y-m-d H:i:s'));

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

    error_log("education.php: Attempting to authenticate user via authenticate_user_from_jwt.");
    $userId = authenticate_user_from_jwt($pdo, false); // 'false' indicates user-facing route
    error_log("education.php: User authenticated with ID: " . $userId);

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Corrected table name to 'user_education'
        $stmt = $pdo->prepare("SELECT id, user_id, degree, institution, start_year, end_year, description FROM user_education WHERE user_id = ?");
        $stmt->execute([$userId]);
        $education = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($education); // Return education entries directly as an array
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents("php://input"), true);
        $degree = trim($data['degree'] ?? '');
        $institution = trim($data['institution'] ?? '');
        $startYear = $data['start_year'] ?? null; // Can be a string "YYYY" or integer
        $endYear = $data['end_year'] ?? null;     // Can be a string "YYYY" or integer, or null if ongoing
        $description = trim($data['description'] ?? '');

        if (empty($degree) || empty($institution) || $startYear === null) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Degree, institution, and start year are required."]);
            exit();
        }

        // Convert years to integer if they are not null, to store as INT
        $startYear = $startYear !== null ? (int)$startYear : null;
        $endYear = $endYear !== null ? (int)$endYear : null;

        // Corrected table name to 'user_education'
        $stmt = $pdo->prepare("INSERT INTO user_education (user_id, degree, institution, start_year, end_year, description) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $degree, $institution, $startYear, $endYear, $description]);
        $educationId = $pdo->lastInsertId();

        echo json_encode(["success" => true, "message" => "Education entry added successfully.", "id" => $educationId]);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        $data = json_decode(file_get_contents("php://input"), true);
        $educationId = $data['id'] ?? null;
        $degree = trim($data['degree'] ?? '');
        $institution = trim($data['institution'] ?? '');
        $startYear = $data['start_year'] ?? null;
        $endYear = $data['end_year'] ?? null;
        $description = trim($data['description'] ?? '');

        if (empty($educationId) || empty($degree) || empty($institution) || $startYear === null) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Education ID, degree, institution, and start year are required for update."]);
            exit();
        }

        // Convert years to integer if they are not null
        $startYear = $startYear !== null ? (int)$startYear : null;
        $endYear = $endYear !== null ? (int)$endYear : null;

        // Ensure the education entry belongs to the authenticated user and corrected table name to 'user_education'
        $stmt = $pdo->prepare("UPDATE user_education SET degree = ?, institution = ?, start_year = ?, end_year = ?, description = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$degree, $institution, $startYear, $endYear, $description, $educationId, $userId]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(["success" => true, "message" => "Education entry updated successfully."]);
        } else {
            http_response_code(404);
            echo json_encode(["success" => false, "error" => "Education entry not found or not owned by user."]);
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $educationId = $_GET['id'] ?? null;

        if (empty($educationId)) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Education ID is required for deletion."]);
            exit();
        }

        // Ensure the education entry belongs to the authenticated user and corrected table name to 'user_education'
        $stmt = $pdo->prepare("DELETE FROM user_education WHERE id = ? AND user_id = ?");
        $stmt->execute([$educationId, $userId]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(["success" => true, "message" => "Education entry deleted successfully."]);
        } else {
            http_response_code(404);
            echo json_encode(["success" => false, "error" => "Education entry not found or not owned by user."]);
        }
    } else {
        http_response_code(405);
        echo json_encode(["success" => false, "error" => "Method not allowed."]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    error_log("education.php PDO error: " . $e->getMessage());
    echo json_encode(["success" => false, "error" => "Database error: " . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    error_log("education.php general error: " . $e->getMessage());
    echo json_encode(["success" => false, "error" => "Server error: " . $e->getMessage()]);
}
