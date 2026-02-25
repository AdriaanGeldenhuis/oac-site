<?php
// =====================================================================
// /notifications/api/count.php — Get Unread Count
// =====================================================================

require_once __DIR__ . '/../../security/auth_gate.php';

// Auto-cron: check for pending thought notifications (throttled to once per 5 min)
require_once __DIR__ . '/../../cron/auto_thought_cron.php';
if (isset($pdo)) {
    runThoughtAutoCron($pdo);
}

header('Content-Type: application/json; charset=utf-8');

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
    
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM notifications 
        WHERE user_id = :user_id AND is_read = 0
    ");
    
    $stmt->execute(['user_id' => $userId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'count' => (int)$result['count']
    ]);
    
} catch (Exception $e) {
    error_log('Notification count error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'count' => 0
    ]);
}