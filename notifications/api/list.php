<?php
// =====================================================================
// /notifications/api/list.php
// =====================================================================

require_once __DIR__ . '/../../security/auth_gate.php';
require_once __DIR__ . '/../../includes/languages.php';

header('Content-Type: application/json; charset=utf-8');

// Get user's language preference
$pageLang = $_SESSION['language'] ?? 'af';

/**
 * Translate a notification title/message using translation key and params
 */
function translateNotification(string $text, ?string $key, ?string $paramsJson, string $lang): string {
    // If we have a translation key, use it
    if ($key) {
        $translated = __t($key, $lang);
        // If translation exists and is different from the key, use it
        if ($translated !== $key) {
            // Replace placeholders with params
            if ($paramsJson) {
                $params = json_decode($paramsJson, true);
                if (is_array($params)) {
                    foreach ($params as $placeholder => $value) {
                        $translated = str_replace('{' . $placeholder . '}', $value, $translated);
                    }
                }
            }
            return $translated;
        }
    }
    // Fallback to original text
    return $text;
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
            icon TEXT,
            title_key TEXT,
            message_key TEXT,
            params TEXT
        )
    ");

    // Check if we need to add the new columns
    $cols = $db->query("PRAGMA table_info(notifications)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('title_key', $cols)) {
        $db->exec("ALTER TABLE notifications ADD COLUMN title_key TEXT");
    }
    if (!in_array('message_key', $cols)) {
        $db->exec("ALTER TABLE notifications ADD COLUMN message_key TEXT");
    }
    if (!in_array('params', $cols)) {
        $db->exec("ALTER TABLE notifications ADD COLUMN params TEXT");
    }

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

    // Translate notifications based on user's language
    foreach ($notifications as &$notif) {
        $notif['title'] = translateNotification(
            $notif['title'],
            $notif['title_key'] ?? null,
            $notif['params'] ?? null,
            $pageLang
        );
        $notif['message'] = translateNotification(
            $notif['message'] ?? '',
            $notif['message_key'] ?? null,
            $notif['params'] ?? null,
            $pageLang
        );
        // Remove internal keys from response
        unset($notif['title_key'], $notif['message_key'], $notif['params']);
    }
    unset($notif);

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
