<?php
// =====================================================================
// /notifications/api/mark_read.php — Mark Notifications as Read or Delete
// =====================================================================

require_once __DIR__ . '/../../security/auth_gate.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

try {
    // Ensure data directory exists
    $dataDir = __DIR__ . '/../../data';
    if (!is_dir($dataDir)) {
        if (!mkdir($dataDir, 0775, true)) {
            throw new Exception('Failed to create data directory');
        }
    }
    
    $dbPath = $dataDir . '/notifications.db';
    
    $db = new PDO('sqlite:' . $dbPath);
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
    
    $userId = $_SESSION['user_id'] ?? null;
    
    if (!$userId) {
        throw new Exception('User not logged in');
    }
    
    // DELETE
    if (isset($_POST['delete']) && $_POST['delete'] === '1') {
        $id = $_POST['id'] ?? null;
        
        if (!$id) {
            echo json_encode(['success' => false, 'error' => 'ID required']);
            exit;
        }
        
        $stmt = $db->prepare("DELETE FROM notifications WHERE id = :id AND user_id = :user_id");
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
        
        echo json_encode(['success' => true, 'message' => 'Notification deleted']);
        exit;
    }
    
    // MARK ALL AS READ
    if (isset($_POST['all']) && $_POST['all'] === '1') {
        $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $userId]);
        
        echo json_encode(['success' => true, 'message' => 'All notifications marked as read']);
        exit;
    }
    
    // MARK SINGLE AS READ
    $id = $_POST['id'] ?? null;
    
    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'ID required']);
        exit;
    }
    
    $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :user_id");
    $stmt->execute(['id' => $id, 'user_id' => $userId]);
    
    echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
    
} catch (Exception $e) {
    error_log('Mark read error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}