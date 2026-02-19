<?php
// =====================================================================
// /admin/api/notifications/create.php
// =====================================================================

require_once __DIR__ . '/../../../security/auth_gate.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

try {
    $db = new PDO('sqlite:' . __DIR__ . '/../../../data/notifications.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create table if not exists
    $db->exec("
        CREATE TABLE IF NOT EXISTS notifications (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            title TEXT NOT NULL,
            message TEXT,
            type TEXT DEFAULT 'info',
            is_read INTEGER DEFAULT 0,
            created_at TEXT DEFAULT (datetime('now')),
            link TEXT,
            icon TEXT,
            title_key TEXT,
            message_key TEXT,
            params TEXT
        )
    ");

    $userId = $_POST['user_id'] ?? null;
    $title = trim($_POST['title'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $type = trim($_POST['type'] ?? 'info');
    $link = trim($_POST['link'] ?? '');
    $icon = trim($_POST['icon'] ?? '');

    if (!$userId || !$title) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        exit;
    }

    // Validate type
    $validTypes = ['info', 'success', 'warning', 'error', 'reminder', 'calendar', 'gospel', 'account', 'spouse', 'ampte', 'appointment', 'birthday'];
    if (!in_array($type, $validTypes)) {
        $type = 'info';
    }

    $stmt = $db->prepare("
        INSERT INTO notifications (user_id, title, message, type, link, icon)
        VALUES (:user_id, :title, :message, :type, :link, :icon)
    ");

    $stmt->execute([
        'user_id' => $userId,
        'title' => $title,
        'message' => $message,
        'type' => $type,
        'link' => $link,
        'icon' => $icon
    ]);

    echo json_encode([
        'success' => true,
        'id' => $db->lastInsertId()
    ]);

} catch (Exception $e) {
    error_log('Admin notification create error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error'
    ]);
}