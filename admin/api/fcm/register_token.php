<?php
// =====================================================================
// /admin/api/fcm/register_token.php — Register FCM Token for Push
// =====================================================================

require_once __DIR__ . '/../../../security/auth_gate.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    $token = trim($data['token'] ?? '');
    $userId = $_SESSION['user_id'] ?? null;

    if (!$userId) {
        throw new Exception('User not logged in');
    }

    if (empty($token)) {
        throw new Exception('Token is required');
    }

    // Use main database
    global $pdo;

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

    // Insert or update token
    $stmt = $pdo->prepare("
        INSERT INTO fcm_tokens (user_id, token, device_info, updated_at)
        VALUES (:user_id, :token, :device_info, NOW())
        ON DUPLICATE KEY UPDATE
            user_id = VALUES(user_id),
            device_info = VALUES(device_info),
            updated_at = NOW()
    ");

    $deviceInfo = $data['device_info'] ?? 'Android App';

    $stmt->execute([
        ':user_id' => $userId,
        ':token' => $token,
        ':device_info' => $deviceInfo
    ]);

    // Remember this device's token so logout.php can unlink it — otherwise the
    // phone keeps receiving the previous user's notifications after logout.
    $_SESSION['fcm_token'] = $token;

    echo json_encode([
        'success' => true,
        'message' => 'Token registered'
    ]);

} catch (Exception $e) {
    error_log('FCM register token error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
