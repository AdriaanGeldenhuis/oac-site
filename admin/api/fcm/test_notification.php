<?php
// =====================================================================
// /admin/api/fcm/test_notification.php — Test createAdminNotification Flow
// =====================================================================

require_once __DIR__ . '/../../../security/auth_gate.php';
require_once __DIR__ . '/../notifications/helper.php';

header('Content-Type: application/json; charset=utf-8');

$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    echo json_encode(['success' => false, 'error' => 'User not logged in']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'POST required']);
    exit;
}

try {
    // Create a test notification using the normal flow
    $result = createAdminNotification($userId, 'custom', [
        'title' => 'Test Notification',
        'message' => 'Hierdie is \'n toets notifikasie via createAdminNotification()',
        'link' => '/notifications/notifications.php',
        'icon' => '🔔'
    ]);

    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Notification created! Check your phone AND /notifications/ page.'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'createAdminNotification returned false'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
