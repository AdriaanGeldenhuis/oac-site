<?php
// =====================================================================
// /calendar/api/notifications/helper.php
// =====================================================================

// Include FCM functions for push notifications
require_once __DIR__ . '/../../../admin/config/fcm_config.php';

function createCalendarNotification($userId, $type, $data = []) {
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
        $notifType = 'calendar';
        $titleKey = '';
        $messageKey = '';
        $params = [];

        switch ($type) {
            case 'event_reminder':
                $eventTitle = $data['event_title'] ?? 'Aktiwiteit';
                $time = $data['time'] ?? '';
                $titleKey = 'notif_event_reminder';
                $messageKey = 'notif_event_reminder_msg';
                $params = ['event' => $eventTitle, 'time' => $time];
                $title = '⏰ Herinnering';
                $message = "{$eventTitle} begin oor 30 minute om {$time}";
                $link = '/calendar/calendar.php';
                $icon = '⏰';
                break;

            case 'event_shared':
                $fromName = $data['from_name'] ?? 'Iemand';
                $eventTitle = $data['event_title'] ?? 'Aktiwiteit';
                $titleKey = 'notif_event_shared';
                $messageKey = 'notif_event_shared_msg';
                $params = ['name' => $fromName, 'event' => $eventTitle];
                $title = '📅 Aktiwiteit Gedeel';
                $message = "{$fromName} het '{$eventTitle}' met jou gedeel.";
                $link = '/calendar/calendar.php';
                $icon = '📅';
                break;

            case 'diary_reminder':
                $titleKey = 'notif_diary_reminder';
                $messageKey = 'notif_diary_reminder_msg';
                $title = '📓 Dagboek Herinnering';
                $message = 'Onthou om jou dagboek vandag by te werk!';
                $link = '/diary/diary.php';
                $icon = '📓';
                break;

            case 'visit_scheduled':
                $personName = $data['person_name'] ?? 'Iemand';
                $date = $data['date'] ?? '';
                $titleKey = 'notif_visit_scheduled';
                $messageKey = 'notif_visit_scheduled_msg';
                $params = ['name' => $personName, 'date' => $date];
                $title = '🏠 Besoek Geskeduleer';
                $message = "Besoek by {$personName} op {$date}";
                $link = '/calendar/calendar.php';
                $icon = '🏠';
                break;

            default:
                $title = $data['title'] ?? 'Kalender Kennisgewing';
                $message = $data['message'] ?? '';
                $link = $data['link'] ?? '/calendar/calendar.php';
                $icon = $data['icon'] ?? '📅';
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
            global $pdo;
            $pushTitle = preg_replace('/[\x{1F300}-\x{1F9FF}]/u', '', $title);
            $pushTitle = trim($pushTitle);
            if (empty($pushTitle)) $pushTitle = 'Calendar';

            $pushResult = sendPushToUser((int)$userId, $pushTitle, $message, [
                'type' => $notifType,
                'link' => $link,
                'notification_id' => $db->lastInsertId(),
                'titleKey' => $titleKey,
                'messageKey' => $messageKey,
                'params' => $params
            ]);
            error_log("FCM Calendar: Push to user $userId result: " . json_encode($pushResult));
        } catch (Exception $pushError) {
            error_log('FCM Calendar push error: ' . $pushError->getMessage());
        }

        return true;

    } catch (Exception $e) {
        error_log('Create calendar notification error: ' . $e->getMessage());
        return false;
    }
}
