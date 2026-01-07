<?php
// =====================================================================
// /admin/config/fcm_config.php — Firebase Cloud Messaging Configuration
// =====================================================================

declare(strict_types=1);

// Firebase Project Config
define('FCM_PROJECT_ID', 'oac-app-5728d');
define('FCM_API_KEY', 'AIzaSyDt0ZJqlSnVLBE-r111eH1g0GBfa7X60zY');

// Legacy FCM Server Key (get from Firebase Console -> Cloud Messaging -> Server key)
// You need to add this from Firebase Console
define('FCM_SERVER_KEY', ''); // TODO: Add your server key here

/**
 * Send push notification via FCM
 *
 * @param string|array $tokens FCM token(s) to send to
 * @param string $title Notification title
 * @param string $body Notification body
 * @param array $data Additional data payload
 * @return array Result with success status
 */
function sendFcmNotification($tokens, string $title, string $body, array $data = []): array {
    if (empty(FCM_SERVER_KEY)) {
        error_log('FCM Server Key not configured');
        return ['success' => false, 'error' => 'FCM not configured'];
    }

    $tokens = is_array($tokens) ? $tokens : [$tokens];

    if (empty($tokens)) {
        return ['success' => false, 'error' => 'No tokens provided'];
    }

    $notification = [
        'title' => $title,
        'body' => $body,
        'sound' => 'default',
        'click_action' => 'OPEN_APP'
    ];

    // For single token
    if (count($tokens) === 1) {
        $payload = [
            'to' => $tokens[0],
            'notification' => $notification,
            'data' => $data,
            'priority' => 'high'
        ];
    } else {
        // For multiple tokens (max 500 per request)
        $payload = [
            'registration_ids' => array_slice($tokens, 0, 500),
            'notification' => $notification,
            'data' => $data,
            'priority' => 'high'
        ];
    }

    $ch = curl_init('https://fcm.googleapis.com/fcm/send');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: key=' . FCM_SERVER_KEY,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        error_log('FCM curl error: ' . $error);
        return ['success' => false, 'error' => $error];
    }

    $result = json_decode($response, true);

    if ($httpCode !== 200) {
        error_log('FCM error response: ' . $response);
        return ['success' => false, 'error' => 'FCM request failed', 'http_code' => $httpCode];
    }

    return [
        'success' => true,
        'result' => $result
    ];
}

/**
 * Send push notification to a specific user
 *
 * @param int $userId User ID to send to
 * @param string $title Notification title
 * @param string $body Notification body
 * @param array $data Additional data payload
 * @return array Result with success status
 */
function sendPushToUser(int $userId, string $title, string $body, array $data = []): array {
    global $pdo;

    try {
        // Get all tokens for this user
        $stmt = $pdo->prepare("SELECT token FROM fcm_tokens WHERE user_id = ?");
        $stmt->execute([$userId]);
        $tokens = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($tokens)) {
            return ['success' => false, 'error' => 'No tokens for user'];
        }

        return sendFcmNotification($tokens, $title, $body, $data);

    } catch (Exception $e) {
        error_log('sendPushToUser error: ' . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
