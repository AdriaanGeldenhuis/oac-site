<?php
// =====================================================================
// /calendar/api/notifications/helper.php
// Calendar notifications, stored like every other area (data/notifications.db
// with translation keys, so the notifications page shows them in the
// reader's language) and pushed to the phone in the recipient's language.
// Links open the calendar on the right day: /calendar/calendar.php#d=…&i=…
// =====================================================================

require_once __DIR__ . '/../../../admin/config/fcm_config.php';
require_once __DIR__ . '/../../lib/calendar.php';

function calendarNotificationDb(): PDO {
    static $db = null;
    if ($db !== null) return $db;

    $db = new PDO('sqlite:' . __DIR__ . '/../../../data/notifications.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
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
    $cols = $db->query("PRAGMA table_info(notifications)")->fetchAll(PDO::FETCH_COLUMN, 1);
    foreach (['title_key', 'message_key', 'params'] as $col) {
        if (!in_array($col, $cols, true)) {
            $db->exec("ALTER TABLE notifications ADD COLUMN $col TEXT");
        }
    }
    return $db;
}

/** [title key, message key, params, link, icon, type] for a calendar notification */
function calendarNotificationSpec(string $type, array $data): ?array {
    $date = (string)($data['date'] ?? '');
    $uid = (string)($data['uid'] ?? '');
    $link = cal_date($date) !== null ? cal_link($date, $uid) : '/calendar/calendar.php';

    switch ($type) {
        case 'visit_scheduled':
            return ['notif_visit_scheduled', 'cal_notif_visit_msg',
                ['name' => $data['person_name'] ?? '', 'date' => $data['when'] ?? $date],
                $link, '🏠', 'calendar'];

        case 'event_shared':
            return ['notif_event_shared', 'notif_event_shared_msg',
                ['name' => $data['from_name'] ?? '', 'event' => $data['event_title'] ?? ''],
                $link, '📅', 'calendar'];

        case 'event_reminder':
            return ['notif_event_reminder', 'cal_notif_reminder_msg',
                ['event' => $data['event_title'] ?? '', 'when' => $data['when'] ?? $date, 'room' => $data['room_name'] ?? ''],
                $link, '⏰', 'reminder'];

        case 'appointment_confirmed':
            return ['notif_appointment_confirmed', 'cal_notif_appt_confirmed_msg',
                ['name' => $data['from_name'] ?? '', 'date' => $date, 'time' => $data['time'] ?? ''],
                $link, '✅', 'appointment'];

        case 'appointment_cancelled':
            return ['notif_appointment_cancelled', 'notif_appointment_cancelled_msg',
                ['name' => $data['from_name'] ?? '', 'date' => $date, 'time' => $data['time'] ?? ''],
                $link, '❌', 'appointment'];
    }
    return null;
}

function calendarPushTitle(string $title): string {
    $t = preg_replace('/[\x{1F000}-\x{1FAFF}\x{2300}-\x{23FF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{FE0F}\x{200D}]/u', '', $title) ?? $title;
    $t = trim($t);
    return $t !== '' ? $t : 'Kalender';
}

/**
 * Notify several people at once (a room reminder can reach hundreds):
 * one stored row each, and one push per language group in parallel batches.
 */
function createCalendarNotifications(array $userIds, string $type, array $data = []): int {
    global $pdo;
    $spec = calendarNotificationSpec($type, $data);
    $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), fn($id) => $id > 0)));
    if (!$spec || !$userIds) return 0;
    [$titleKey, $messageKey, $params, $link, $icon, $notifType] = $spec;

    $stored = 0;
    try {
        $db = calendarNotificationDb();
        $ins = $db->prepare("
            INSERT INTO notifications (user_id, title, message, type, link, icon, title_key, message_key, params)
            VALUES (:user_id, :title, :message, :type, :link, :icon, :title_key, :message_key, :params)
        ");
        // The stored text is the Afrikaans fallback; readers get their own language from the keys
        $title = cal_t($titleKey, 'af', $params);
        $message = cal_t($messageKey, 'af', $params);
        $db->beginTransaction();
        foreach ($userIds as $uid) {
            $ins->execute([
                'user_id' => $uid,
                'title' => $title,
                'message' => $message,
                'type' => $notifType,
                'link' => $link,
                'icon' => $icon,
                'title_key' => $titleKey,
                'message_key' => $messageKey,
                'params' => json_encode($params, JSON_UNESCAPED_UNICODE),
            ]);
            $stored++;
        }
        $db->commit();
    } catch (Throwable $e) {
        error_log('Calendar notification store error: ' . $e->getMessage());
    }

    // Push, in each recipient's own language
    try {
        if (!($pdo instanceof PDO)) return $stored;
        $in = implode(',', array_fill(0, count($userIds), '?'));
        $st = $pdo->prepare("SELECT t.token, u.language FROM fcm_tokens t JOIN users u ON u.id = t.user_id WHERE t.user_id IN ($in)");
        $st->execute($userIds);
        $byLang = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $byLang[cal_valid_lang($row['language'] ?? 'af')][] = $row['token'];
        }
        foreach ($byLang as $lang => $tokens) {
            sendFcmNotification(
                $tokens,
                calendarPushTitle(cal_t($titleKey, $lang, $params)),
                cal_t($messageKey, $lang, $params),
                ['type' => $notifType, 'link' => $link, 'titleKey' => $titleKey, 'messageKey' => $messageKey, 'params' => $params]
            );
        }
    } catch (Throwable $e) {
        error_log('Calendar push error: ' . $e->getMessage());
    }
    return $stored;
}

/** One person (kept for existing callers) */
function createCalendarNotification($userId, $type, $data = []): bool {
    return createCalendarNotifications([(int)$userId], (string)$type, (array)$data) > 0;
}
