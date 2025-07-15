<?php
// backend/api/get_counselor_meetings.php

// Include CORS helper
require_once __DIR__ . '/cors_helper.php';

header('Content-Type: application/json');

// Handle preflight requests (OPTIONS) - Must exit here
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 2. Error Reporting (for debugging, set display_errors to 0 in production)
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../php_error.log'); // Use the same log file as jwt_helper.php

error_log("get_counselor_meetings.php: Script execution started.");

// 3. Database Connection and JWT Helper
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/jwt_helper.php';

global $pdo;

if (!isset($pdo) || !$pdo instanceof PDO) {
    http_response_code(500);
    error_log("Get Counselor Meetings API error: PDO connection not established.");
    echo json_encode(["success" => false, "error" => "Database connection error."]);
    exit();
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(["success" => false, "error" => "Method not allowed. Only GET requests are accepted."]);
        exit();
    }

    // Authenticate the counselor. This function will exit if not authenticated or not a counselor.
    $counselor_id = authenticate_user_from_jwt($pdo, true); // This returns the counselor ID (int)

    error_log("get_counselor_meetings.php: Authenticated Counselor ID: " . $counselor_id);

    // Now, fetch the counselor's email using the ID from the 'counselors' table
    $stmt_email = $pdo->prepare("SELECT email FROM counselors WHERE id = ?");
    $stmt_email->execute([$counselor_id]);
    $counselor_data = $stmt_email->fetch(PDO::FETCH_ASSOC);

    if (!$counselor_data || empty($counselor_data['email'])) {
        http_response_code(401); // Unauthorized
        error_log("get_counselor_meetings.php: Counselor email not found in 'counselors' table for ID: " . $counselor_id);
        echo json_encode(["success" => false, "error" => "Counselor email not found for authenticated ID."]);
        exit();
    }
    $counselor_email = $counselor_data['email'];
    error_log("get_counselor_meetings.php: Found Counselor Email: " . $counselor_email);


    // Fetch scheduled meetings for this counselor_email
    $stmt = $pdo->prepare("
        SELECT
            sm.id,
            sm.user_email,
            sm.counselor_email,
            sm.schedule_date,
            sm.schedule_time,
            sm.purpose,
            sm.created_at,
            u.full_name AS student_name,
            u.email AS student_email,
            u.last_activity_at AS student_last_active
        FROM
            scheduled_meetings sm
        JOIN
            users u ON sm.user_email = u.email
        WHERE
            sm.counselor_email = ?
        ORDER BY
            sm.schedule_date DESC, sm.schedule_time DESC
    ");
    $stmt->execute([$counselor_email]);
    $meetings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(["success" => true, "meetings" => $meetings]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Get Counselor Meetings API database error: " . $e->getMessage());
    echo json_encode(["success" => false, "error" => "Database error."]);
} catch (Exception $e) {
    // This catches general exceptions, typically from jwt_helper.php's internal errors
    http_response_code(500); // Internal Server Error for unhandled exceptions
    error_log("Get Counselor Meetings API unhandled exception: " . $e->getMessage());
    echo json_encode(["success" => false, "error" => "An unexpected server error occurred."]);
}
?>