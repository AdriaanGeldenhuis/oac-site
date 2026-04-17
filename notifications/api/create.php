<?php
// =====================================================================
// /notifications/api/create.php — Create New Notification (Internal API)
// =====================================================================

require_once __DIR__ . '/../../security/auth_gate.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

try {
    $db = new PDO('sqlite:' . __DIR__ . '/../../data/notifications.db');
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

    // Use session user_id for security — prevent creating notifications for other users
    $userId = (int)($_SESSION['user_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $type = trim($_POST['type'] ?? 'info');
    $link = trim($_POST['link'] ?? '');
    $icon = trim($_POST['icon'] ?? '');
    
    if (!$userId || empty($title)) {
        echo json_encode(['success' => false, 'error' => 'User ID and title required']);
        exit;
    }
    
    // Validate type — keep in sync with /notifications/api/save.php
    $validTypes = ['info', 'success', 'warning', 'error', 'reminder', 'calendar', 'gospel', 'account', 'spouse', 'spouse_accepted', 'spouse_rejected', 'ampte', 'appointment', 'birthday', 'thought'];
    if (!in_array($type, $validTypes, true)) {
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
    
    $newId = $db->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'id' => $newId,
        'message' => 'Notification created'
    ]);
    
} catch (Exception $e) {
    error_log('Create notification error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error'
    ]);
}