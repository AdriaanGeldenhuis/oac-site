<?php
/**
 * Daily Thought Push Notification Cron Job
 *
 * Run this script every 15 minutes via cron:
 * */15 * * * * php /path/to/oac-site/cron/thought_notifications.php
 *
 * Or via URL (with cron service like cron-job.org):
 * https://oacapp.co.za/cron/thought_notifications.php?key=YOUR_SECRET_KEY
 *
 * Checks if today's thought has reached its display_time and sends
 * push notifications to all users in their preferred language.
 */

declare(strict_types=1);

$isCliMode = (php_sapi_name() === 'cli');
$secretKey = 'oac_thought_cron_2024_secure';

if (!$isCliMode) {
    if (($_GET['key'] ?? '') !== $secretKey) {
        http_response_code(403);
        die('Forbidden');
    }
    header('Content-Type: application/json');
}

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../admin/config/fcm_config.php';
require_once __DIR__ . '/../includes/languages.php';

$pdo = db();
$results = ['sent' => 0, 'errors' => 0, 'skipped' => false];

try {
    // Add columns if they don't exist (safe migration)
    try {
        $pdo->exec("ALTER TABLE daily_thoughts ADD COLUMN display_time TIME DEFAULT NULL AFTER display_date");
    } catch (Throwable $e) { /* already exists */ }
    try {
        $pdo->exec("ALTER TABLE daily_thoughts ADD COLUMN notification_sent TINYINT(1) DEFAULT 0 AFTER display_time");
    } catch (Throwable $e) { /* already exists */ }

    // Find today's thought that is ready to display but notification not yet sent
    $stmt = $pdo->prepare("
        SELECT id, content, author
        FROM daily_thoughts
        WHERE display_date = CURDATE()
          AND notification_sent = 0
          AND (display_time IS NULL OR display_time <= CURTIME())
        LIMIT 1
    ");
    $stmt->execute();
    $thought = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$thought) {
        $results['skipped'] = true;
        $results['message'] = 'No thought ready for notification';
        if (!$isCliMode) echo json_encode($results);
        else echo "No thought ready for notification.\n";
        exit;
    }

    // Mark as sent immediately to prevent duplicate sends
    $updateStmt = $pdo->prepare("UPDATE daily_thoughts SET notification_sent = 1 WHERE id = ?");
    $updateStmt->execute([$thought['id']]);

    // Get all approved users
    $userStmt = $pdo->prepare("
        SELECT id, name, language
        FROM users
        WHERE status = 'approved'
    ");
    $userStmt->execute();
    $users = $userStmt->fetchAll(PDO::FETCH_ASSOC);

    // SQLite notification database
    $notifDb = new PDO('sqlite:' . __DIR__ . '/../data/notifications.db');
    $notifDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $notifDb->exec("
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

    $thoughtContent = strip_tags($thought['content']);
    // Truncate for push notification body (max 200 chars)
    $pushBody = mb_strlen($thoughtContent) > 200
        ? mb_substr($thoughtContent, 0, 197) . '...'
        : $thoughtContent;

    $insertNotif = $notifDb->prepare("
        INSERT INTO notifications (user_id, title, message, type, link, icon, title_key, message_key, params)
        VALUES (:user_id, :title, :message, :type, :link, :icon, :title_key, :message_key, :params)
    ");

    foreach ($users as $user) {
        $userId = (int)$user['id'];
        $userLang = $user['language'] ?? 'af';

        // Get translated title
        $title = __t('notif_thought_title', $userLang) ?: 'Thought of the Day';

        // Create in-app notification
        try {
            $insertNotif->execute([
                'user_id' => $userId,
                'title' => $title,
                'message' => $pushBody,
                'type' => 'info',
                'link' => '/welcome.php',
                'icon' => '',
                'title_key' => 'notif_thought_title',
                'message_key' => null,
                'params' => json_encode(['content' => $pushBody])
            ]);
        } catch (Throwable $e) {
            error_log("Thought notification insert error for user $userId: " . $e->getMessage());
        }

        // Send FCM push notification
        try {
            sendPushToUser($userId, $title, $pushBody, [
                'type' => 'thought',
                'link' => '/welcome.php',
                'titleKey' => 'notif_thought_title'
            ]);
            $results['sent']++;
        } catch (Throwable $e) {
            $results['errors']++;
            error_log("Thought FCM error for user $userId: " . $e->getMessage());
        }
    }

    $results['message'] = 'Thought notifications sent successfully';
    $results['thought_id'] = $thought['id'];

} catch (Throwable $e) {
    $results['error'] = $e->getMessage();
    error_log('Thought notification cron error: ' . $e->getMessage());
}

if (!$isCliMode) {
    echo json_encode($results, JSON_PRETTY_PRINT);
} else {
    echo "Thought notifications complete.\n";
    echo "Sent: {$results['sent']}, Errors: {$results['errors']}\n";
}
