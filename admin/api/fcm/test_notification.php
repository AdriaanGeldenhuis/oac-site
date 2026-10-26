<?php
// =====================================================================
// /admin/api/fcm/test_notification.php — Test createAdminNotification flow
// POST (JSON) from /admin/fcm-debug.php; office bearers (amp 1-6) only.
// =====================================================================
declare(strict_types=1);

require_once __DIR__ . '/../../../security/auth_gate.php';
require_once __DIR__ . '/../../../includes/admin_api.php';
require_once __DIR__ . '/../notifications/helper.php';

admin_api_require_json_post();
$userId = admin_api_user_id();

try {
    $gate = $pdo->prepare('SELECT amp_id FROM users WHERE id = ? LIMIT 1');
    $gate->execute([$userId]);
    $gateAmp = (int)($gate->fetchColumn() ?: 0);
    if ($gateAmp < 1 || $gateAmp > 6) {
        admin_api_fail('adm_err_forbidden', 403);
    }

    // A test notification through the normal flow
    $result = createAdminNotification($userId, 'custom', [
        'title' => 'Test Notification',
        'message' => 'Hierdie is \'n toets notifikasie via createAdminNotification()',
        'link' => '/notifications/notifications.php',
        'icon' => '🔔',
    ]);

    if ($result) {
        admin_api_json(['success' => true, 'message' => 'Notification created! Check your phone AND /notifications/ page.']);
    }
    admin_api_json(['success' => false, 'error' => 'createAdminNotification returned false - check the server error log']);
} catch (Throwable $e) {
    admin_api_server_error($e, 'fcm/test_notification');
}
