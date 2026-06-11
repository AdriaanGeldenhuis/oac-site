<?php
// =====================================================================
// /prayers/api/notifications/helper.php
// =====================================================================

// Include FCM functions for push notifications
require_once __DIR__ . '/../../../admin/config/fcm_config.php';

// Reuse one SQLite connection (and run schema checks once) per request.
function prayerNotificationDb(): PDO {
    static $db = null;
    if ($db !== null) return $db;

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

    return $db;
}

function createPrayerNotification($pdo, $userId, $type, $data = []) {
    try {
        $db = prayerNotificationDb();

        $title = '';
        $message = '';
        $link = '/prayers/prayers.php';
        $icon = '🙏';
        $notifType = 'prayer';
        $titleKey = '';
        $messageKey = '';
        $params = [];

        switch ($type) {
            case 'reaction_on_post':
                $reactorName = $data['reactor_name'] ?? 'Iemand';
                $reactionType = $data['reaction_type'] ?? 'heart';
                $emoji = $reactionType === 'heart' ? '❤️' : '🙏';
                $titleKey = 'notif_reaction';
                $messageKey = 'notif_reaction_msg';
                $params = ['name' => $reactorName, 'emoji' => $emoji];
                $title = "{$emoji} Reaksie";
                $message = "{$reactorName} het {$emoji} op jou plasing gegee.";
                $icon = $emoji;
                break;

            case 'comment_on_post':
                $commenterName = $data['commenter_name'] ?? 'Iemand';
                $titleKey = 'notif_new_comment';
                $messageKey = 'notif_new_comment_msg';
                $params = ['name' => $commenterName];
                $title = '💬 Nuwe Kommentaar';
                $message = "{$commenterName} het op jou plasing gereageer.";
                $icon = '💬';
                break;

            default:
                $title = $data['title'] ?? 'Gebede Kennisgewing';
                $message = $data['message'] ?? '';
                $link = $data['link'] ?? '/prayers/prayers.php';
                $icon = $data['icon'] ?? '🙏';
        }

        $stmt = $db->prepare("
            INSERT INTO notifications (user_id, title, message, type, link, icon, title_key, message_key, params)
            VALUES (:user_id, :title, :message, :type, :link, :icon, :title_key, :message_key, :params)
        ");

        $stmt->execute([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $notifType,
            'link' => $link,
            'icon' => $icon,
            'title_key' => $titleKey,
            'message_key' => $messageKey,
            'params' => !empty($params) ? json_encode($params) : null
        ]);

        // Send FCM push notification (translated to user's language)
        try {
            // Remove emoji from title for cleaner push notification
            $pushTitle = preg_replace('/[\x{1F000}-\x{1FAFF}\x{2300}-\x{23FF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{FE0F}\x{200D}]/u', '', $title);
            $pushTitle = trim($pushTitle);
            if (empty($pushTitle)) $pushTitle = 'Gebede';

            $pushResult = sendPushToUser((int)$userId, $pushTitle, $message, [
                'type' => $notifType,
                'link' => $link,
                'notification_id' => $db->lastInsertId(),
                'titleKey' => $titleKey,
                'messageKey' => $messageKey,
                'params' => $params
            ]);

            error_log("FCM Prayers: Push to user $userId result: " . json_encode($pushResult));
        } catch (Exception $pushError) {
            error_log('FCM Prayers push error: ' . $pushError->getMessage());
        }

        return true;

    } catch (Exception $e) {
        error_log('Create prayer notification error: ' . $e->getMessage());
        return false;
    }
}
