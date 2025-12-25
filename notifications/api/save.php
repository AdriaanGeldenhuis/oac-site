<?php
// =====================================================================
// /notifications/api/save.php — Save Notification from App
// =====================================================================

require_once __DIR__ . '/../../security/auth_gate.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    // Get JSON input
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if (!$data) {
        throw new Exception('Invalid JSON');
    }
    
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
            icon TEXT
        )
    ");
    
    $userId = $_SESSION['user_id'] ?? null;
    
    if (!$userId) {
        throw new Exception('User not logged in');
    }
    
    $title = $data['title'] ?? '';
    $body = $data['body'] ?? '';
    $type = $data['type'] ?? 'info';
    $link = $data['link'] ?? null;
    $icon = $data['icon'] ?? null;
    
    if (empty($title) || empty($body)) {
        throw new Exception('Title and body are required');
    }
    
    // Insert notification
    $stmt = $db->prepare("
        INSERT INTO notifications (user_id, title, message, type, is_read, created_at, link, icon)
        VALUES (:user_id, :title, :message, :type, 0, datetime('now'), :link, :icon)
    ");
    
    $stmt->execute([
        ':user_id' => $userId,
        ':title' => $title,
        ':message' => $body,
        ':type' => $type,
        ':link' => $link,
        ':icon' => $icon
    ]);
    
    $insertedId = $db->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'id' => $insertedId
    ]);
    
} catch (Exception $e) {
    error_log('Save notification error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}