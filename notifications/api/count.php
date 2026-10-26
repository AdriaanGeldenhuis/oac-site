<?php
// =====================================================================
// /notifications/api/count.php — Get Unread Count (the bell on every page)
// =====================================================================

require_once __DIR__ . '/../../security/auth_gate.php';
require_once __DIR__ . '/../../cron/auto_thought_cron.php';
require_once __DIR__ . '/../lib/notifications.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');

try {
    $userId = nt_user_id();
    if (!$userId) {
        throw new Exception('User not logged in');
    }

    $stmt = nt_db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$userId]);

    echo json_encode([
        'success' => true,
        'count' => (int)$stmt->fetchColumn()
    ]);

    // Auto-cron: check for pending thought notifications AFTER the response has
    // been flushed, so the polling request never blocks on the FCM fan-out.
    if (isset($pdo)) {
        ignore_user_abort(true);
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            if (ob_get_level() > 0) ob_end_flush();
            flush();
        }
        runThoughtAutoCron($pdo);
    }

} catch (Exception $e) {
    error_log('Notification count error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'server_error',
        'count' => 0
    ]);
}
