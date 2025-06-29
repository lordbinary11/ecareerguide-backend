<?php
// backend/api/renew_meeting_token.php
header('Access-Control-Allow-Origin: http://localhost:5173');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/jwt_helper.php';

use Agora\RtcTokenBuilder2;

$appId = '1c08db789936418899fc001688c077aa';
$appCertificate = '2d30018749f645cd8a5aa633b7d07970';
$tokenExpirationInSeconds = 3600; // Token valid for 1 hour
$privilegeExpirationInSeconds = 3600; // Privileges valid for 1 hour

try {
    $token = get_jwt_from_header();
    if (!$token) {
        throw new Exception('User not authenticated. Please log in.');
    }

    $channelName = $_GET['channel_name'] ?? null;
    if (empty($channelName)) {
        throw new Exception('Meeting channel name is required.');
    }

    $authenticated_user_id = authenticate_user_from_jwt($pdo, false);
    $rtcToken = RtcTokenBuilder2::buildTokenWithUid(
        $appId,
        $appCertificate,
        $channelName,
        $authenticated_user_id,
        RtcTokenBuilder2::ROLE_PUBLISHER,
        $tokenExpirationInSeconds,
        $privilegeExpirationInSeconds
    );

    echo json_encode([
        'success' => true,
        'token' => $rtcToken,
        'appId' => $appId,
        'channelName' => $channelName,
        'uid' => $authenticated_user_id
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
?>
