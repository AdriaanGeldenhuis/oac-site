<?php
// =====================================================================
// /notifications/api/list.php
// =====================================================================

require_once __DIR__ . '/../../security/auth_gate.php';

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
    
    // Create database connection
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
            icon TEXT
        )
    ");
    
    $userId = $_SESSION['user_id'] ?? null;
    
    if (!$userId) {
        throw new Exception('User not logged in');
    }
    
    $stmt = $db->prepare("
        SELECT * FROM notifications 
        WHERE user_id = :user_id 
        ORDER BY created_at DESC
        LIMIT 100
    ");
    
    $stmt->execute(['user_id' => $userId]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'notifications' => $notifications
    ]);
    
} catch (Exception $e) {
    error_log('Notifications list error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => [
            'data_dir' => isset($dataDir) ? $dataDir : 'not set',
            'db_path' => isset($dbPath) ? $dbPath : 'not set',
            'user_id' => $_SESSION['user_id'] ?? 'not set'
        ]
    ]);
}