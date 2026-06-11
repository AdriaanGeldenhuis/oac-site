<?php
// =====================================================================
// /gospel_media/api/notifications/helper.php
// =====================================================================

// Include FCM functions for push notifications
require_once __DIR__ . '/../../../admin/config/fcm_config.php';

// Reuse one SQLite connection (and run schema checks once) per request,
// since posts can fan out notifications to hundreds of members in a loop.
function gospelNotificationDb(): PDO {
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

function createGospelNotification($pdo, $userId, $type, $data = []) {
    try {
        $db = gospelNotificationDb();

        $title = '';
        $message = '';
        $link = '';
        $icon = '';
        $notifType = 'gospel';
        $titleKey = '';
        $messageKey = '';
        $params = [];

        switch ($type) {
            case 'new_post':
                $roomName = $data['room_name'] ?? 'Room';
                $authorName = $data['author_name'] ?? 'Iemand';
                $titleKey = 'notif_new_post';
                $messageKey = 'notif_new_post_msg';
                $params = ['name' => $authorName, 'room' => $roomName];
                $title = '✍️ Nuwe Plasing';
                $message = "{$authorName} het in {$roomName} geplaas.";
                $link = '/gospel_media/gospel.php?room_id=' . ($data['room_id'] ?? '');
                $icon = '✍️';
                break;

            case 'comment_on_post':
                $commenterName = $data['commenter_name'] ?? 'Iemand';
                $postId = $data['post_id'] ?? '';
                $titleKey = 'notif_new_comment';
                $messageKey = 'notif_new_comment_msg';
                $params = ['name' => $commenterName];
                $title = '💬 Nuwe Kommentaar';
                $message = "{$commenterName} het op jou plasing gereageer.";
                $link = '/gospel_media/gospel.php?room_id=' . ($data['room_id'] ?? '') . '#post-' . $postId;
                $icon = '💬';
                break;

            case 'reaction_on_post':
                $reactorName = $data['reactor_name'] ?? 'Iemand';
                $reactionType = $data['reaction_type'] ?? 'heart';
                $emoji = $reactionType === 'heart' ? '❤️' : '🙏';
                $titleKey = 'notif_reaction';
                $messageKey = 'notif_reaction_msg';
                $params = ['name' => $reactorName, 'emoji' => $emoji];
                $title = "{$emoji} Reaksie";
                $message = "{$reactorName} het {$emoji} op jou plasing gegee.";
                $link = '/gospel_media/gospel.php?room_id=' . ($data['room_id'] ?? '');
                $icon = $emoji;
                break;

            case 'tagged_in_post':
                $taggerName = $data['tagger_name'] ?? 'Iemand';
                $titleKey = 'notif_tagged';
                $messageKey = 'notif_tagged_msg';
                $params = ['name' => $taggerName];
                $title = '🏷️ Ge-tag';
                $message = "{$taggerName} het jou in 'n plasing ge-tag.";
                $link = '/gospel_media/gospel.php?room_id=' . ($data['room_id'] ?? '');
                $icon = '🏷️';
                break;

            default:
                $title = $data['title'] ?? 'Gospel Notification';
                $message = $data['message'] ?? '';
                $link = $data['link'] ?? '/gospel_media/gospel.php';
                $icon = $data['icon'] ?? '📢';
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
            $pushTitle = preg_replace('/[\x{1F300}-\x{1F9FF}]/u', '', $title);
            $pushTitle = trim($pushTitle);
            if (empty($pushTitle)) $pushTitle = 'Gospel Media';

            $pushResult = sendPushToUser((int)$userId, $pushTitle, $message, [
                'type' => $notifType,
                'link' => $link,
                'notification_id' => $db->lastInsertId(),
                'titleKey' => $titleKey,
                'messageKey' => $messageKey,
                'params' => $params
            ]);

            error_log("FCM Gospel: Push to user $userId result: " . json_encode($pushResult));
        } catch (Exception $pushError) {
            error_log('FCM Gospel push error: ' . $pushError->getMessage());
        }

        return true;

    } catch (Exception $e) {
        error_log('Create gospel notification error: ' . $e->getMessage());
        return false;
    }
}
