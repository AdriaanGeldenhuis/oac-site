<?php
// =====================================================================
// /gospel_media/api/notifications/helper.php
// =====================================================================

function createGospelNotification($pdo, $userId, $type, $data = []) {
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

        return true;

    } catch (Exception $e) {
        error_log('Create gospel notification error: ' . $e->getMessage());
        return false;
    }
}
