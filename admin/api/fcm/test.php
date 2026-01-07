<?php
// =====================================================================
// /admin/api/fcm/test.php — Test FCM Push Notification
// =====================================================================

require_once __DIR__ . '/../../../security/auth_gate.php';
require_once __DIR__ . '/../../config/fcm_config.php';

header('Content-Type: application/json; charset=utf-8');

// Any logged-in user can test their own notifications
$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    echo json_encode(['success' => false, 'error' => 'User not logged in']);
    exit;
}

// Test 1: Check if service account file exists
$serviceAccountExists = file_exists(FCM_SERVICE_ACCOUNT_FILE);

// Test 2: Try to get access token
$accessToken = null;
$tokenError = null;
if ($serviceAccountExists) {
    $accessToken = getFcmAccessToken();
    if (!$accessToken) {
        $tokenError = 'Failed to get access token';
    }
}

// Test 3: Check if user has FCM tokens registered
global $pdo;
$userTokens = [];
try {
    $stmt = $pdo->prepare("SELECT token, device_info, updated_at FROM fcm_tokens WHERE user_id = ?");
    $stmt->execute([$userId]);
    $userTokens = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Table might not exist yet
}

// Test 4: Send test notification if requested
$testResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (!empty($data['send_test'])) {
        if (!empty($userTokens)) {
            $testResult = sendPushToUser(
                (int)$userId,
                'Test Notification',
                'As jy hierdie sien, werk FCM!',
                ['type' => 'test', 'timestamp' => time()]
            );
        } else {
            $testResult = ['success' => false, 'error' => 'No FCM tokens registered for your account'];
        }
    }
}

echo json_encode([
    'success' => true,
    'checks' => [
        'service_account_exists' => $serviceAccountExists,
        'access_token_valid' => $accessToken !== null,
        'token_error' => $tokenError,
        'user_tokens_count' => count($userTokens),
        'user_tokens' => array_map(function($t) {
            return [
                'token_preview' => substr($t['token'], 0, 20) . '...',
                'device_info' => $t['device_info'],
                'updated_at' => $t['updated_at']
            ];
        }, $userTokens)
    ],
    'test_result' => $testResult
]);
