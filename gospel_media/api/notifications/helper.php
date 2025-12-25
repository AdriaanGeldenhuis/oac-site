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
                icon TEXT
            )
        ");
        
        $title = '';
        $message = '';
        $link = '';
        $icon = '';
        $notifType = 'gospel';
        
        switch ($type) {
            case 'new_post':
                $roomName = $data['room_name'] ?? 'Room';
                $authorName = $data['author_name'] ?? 'Iemand';
                $title = '✍️ Nuwe Plasing';
                $message = "{$authorName} het in {$roomName} geplaas.";
                $link = '/gospel_media/gospel.php?room_id=' . ($data['room_id'] ?? '');
                $icon = '✍️';
                break;
                
            case 'comment_on_post':
                $commenterName = $data['commenter_name'] ?? 'Iemand';
                $postId = $data['post_id'] ?? '';
                $title = '💬 Nuwe Kommentaar';
                $message = "{$commenterName} het op jou plasing gereageer.";
                $link = '/gospel_media/gospel.php?room_id=' . ($data['room_id'] ?? '') . '#post-' . $postId;
                $icon = '💬';
                break;
                
            case 'reaction_on_post':
                $reactorName = $data['reactor_name'] ?? 'Iemand';
                $reactionType = $data['reaction_type'] ?? 'heart';
                $emoji = $reactionType === 'heart' ? '❤️' : '🙏';
                $title = "{$emoji} Reaksie";
                $message = "{$reactorName} het {$emoji} op jou plasing gegee.";
                $link = '/gospel_media/gospel.php?room_id=' . ($data['room_id'] ?? '');
                $icon = $emoji;
                break;
                
            case 'tagged_in_post':
                $taggerName = $data['tagger_name'] ?? 'Iemand';
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
            INSERT INTO notifications (user_id, title, message, type, link, icon)
            VALUES (:user_id, :title, :message, :type, :link, :icon)
        ");
        
        $stmt->execute([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $notifType,
            'link' => $link,
            'icon' => $icon
        ]);
        
        return true;
        
    } catch (Exception $e) {
        error_log('Create gospel notification error: ' . $e->getMessage());
        return false;
    }
}