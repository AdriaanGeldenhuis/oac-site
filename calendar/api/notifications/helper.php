<?php
// =====================================================================
// /calendar/api/notifications/helper.php
// =====================================================================

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
                icon TEXT
            )
        ");
        
        $title = '';
        $message = '';
        $link = '';
        $icon = '';
        $notifType = 'calendar';
        
        switch ($type) {
            case 'event_reminder':
                $eventTitle = $data['event_title'] ?? 'Event';
                $time = $data['time'] ?? '';
                $title = '⏰ Herinnering';
                $message = "{$eventTitle} begin oor 30 minute om {$time}";
                $link = '/calendar/calendar.php';
                $icon = '⏰';
                break;
                
            case 'event_shared':
                $fromName = $data['from_name'] ?? 'Iemand';
                $eventTitle = $data['event_title'] ?? 'Event';
                $title = '📅 Gebeurtenis Gedeel';
                $message = "{$fromName} het '{$eventTitle}' met jou gedeel.";
                $link = '/calendar/calendar.php';
                $icon = '📅';
                break;
                
            case 'diary_reminder':
                $title = '📓 Dagboek Herinnering';
                $message = 'Onthou om jou dagboek vandag by te werk!';
                $link = '/diary/diary.php';
                $icon = '📓';
                break;
                
            case 'visit_scheduled':
                $personName = $data['person_name'] ?? 'Iemand';
                $date = $data['date'] ?? '';
                $title = '🏠 Besoek Geskeduleer';
                $message = "Besoek by {$personName} op {$date}";
                $link = '/calendar/calendar.php';
                $icon = '🏠';
                break;
                
            default:
                $title = $data['title'] ?? 'Calendar Notification';
                $message = $data['message'] ?? '';
                $link = $data['link'] ?? '/calendar/calendar.php';
                $icon = $data['icon'] ?? '📅';
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
        error_log('Create calendar notification error: ' . $e->getMessage());
        return false;
    }
}