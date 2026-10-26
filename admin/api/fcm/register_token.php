<?php
// =====================================================================
// /admin/api/fcm/register_token.php — Register FCM Token for Push
// =====================================================================

require_once __DIR__ . '/../../../security/auth_gate.php';
require_once __DIR__ . '/../../../includes/admin_api.php';

// Called from the native app bridge (assets/js/fcm-bridge.js) with a JSON body
admin_api_require_json_post();
$userId = admin_api_user_id();

try {
    $data = json_decode((string)file_get_contents('php://input'), true);
    $data = is_array($data) ? $data : [];

    $token = trim((string)($data['token'] ?? ''));
    if ($token === '' || strlen($token) > 500) {
        admin_api_fail('adm_err_invalid');
    }
    $deviceInfo = mb_substr(trim((string)($data['device_info'] ?? 'Android App')), 0, 255);

    // Create fcm_tokens table if not exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS fcm_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token VARCHAR(500) NOT NULL,
            device_info VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_token (token(255)),
            INDEX idx_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Insert or update token (a phone that changes hands moves to the new user)
    $stmt = $pdo->prepare("
        INSERT INTO fcm_tokens (user_id, token, device_info, updated_at)
        VALUES (:user_id, :token, :device_info, NOW())
        ON DUPLICATE KEY UPDATE
            user_id = VALUES(user_id),
            device_info = VALUES(device_info),
            updated_at = NOW()
    ");
    $stmt->execute([':user_id' => $userId, ':token' => $token, ':device_info' => $deviceInfo]);

    // Remember this device's token so logout.php can unlink it — otherwise the
    // phone keeps receiving the previous user's notifications after logout.
    $_SESSION['fcm_token'] = $token;

    admin_api_json(['success' => true, 'message' => 'Token registered']);
} catch (Throwable $e) {
    admin_api_server_error($e, 'fcm/register_token');
}
