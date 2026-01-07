<?php
// =====================================================================
// /admin/api/notifications/helper.php - Helper functions
// =====================================================================

// Include database for FCM functions
require_once __DIR__ . '/../../../lib/db.php';

// Include FCM functions
require_once __DIR__ . '/../../config/fcm_config.php';

function createAdminNotification($userId, $type, $data = []) {
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
        $notifType = 'info';
        $titleKey = '';
        $messageKey = '';
        $params = [];

        switch ($type) {
            case 'account_approved':
                $titleKey = 'notif_account_approved';
                $messageKey = 'notif_account_approved_msg';
                $title = '✅ Account Goedgekeur';
                $message = 'Jou rekening is goedgekeur. Jy kan nou aanmeld.';
                $link = '/admin/index.php';
                $icon = '✅';
                $notifType = 'success';
                break;

            case 'account_rejected':
                $titleKey = 'notif_account_rejected';
                $messageKey = 'notif_account_rejected_msg';
                $title = '❌ Account Afgekeur';
                $message = 'Jou rekening is afgekeur. Kontak admin vir meer inligting.';
                $icon = '❌';
                $notifType = 'error';
                break;

            case 'spouse_request':
                $fromName = $data['from_name'] ?? 'Iemand';
                $titleKey = 'notif_spouse_request';
                $messageKey = 'notif_spouse_request_msg';
                $params = ['name' => $fromName];
                $title = '💍 Eggenoot Versoek';
                $message = "{$fromName} wil met jou as eggenoot koppel.";
                $link = '/admin/account.php';
                $icon = '💍';
                $notifType = 'spouse';
                break;

            case 'spouse_accepted':
                $fromName = $data['from_name'] ?? 'Jou eggenoot';
                $titleKey = 'notif_spouse_accepted';
                $messageKey = 'notif_spouse_accepted_msg';
                $params = ['name' => $fromName];
                $title = '💕 Eggenoot Gekoppel';
                $message = "{$fromName} het jou versoek aanvaar!";
                $link = '/admin/account.php';
                $icon = '💕';
                $notifType = 'success';
                break;

            case 'appointment_request':
                $fromName = $data['from_name'] ?? 'Iemand';
                $date = $data['date'] ?? '';
                $time = $data['time'] ?? '';
                $titleKey = 'notif_appointment_request';
                $messageKey = 'notif_appointment_request_msg';
                $params = ['name' => $fromName, 'date' => $date, 'time' => $time];
                $title = '📅 Afspraak Versoek';
                $message = "{$fromName} wil 'n afspraak maak op {$date} om {$time}.";
                $link = '/admin/index.php?tab=afsprake';
                $icon = '📅';
                $notifType = 'appointment';
                break;

            case 'appointment_confirmed':
                $titleKey = 'notif_appointment_confirmed';
                $messageKey = 'notif_appointment_confirmed_msg';
                $title = '✅ Afspraak Bevestig';
                $message = 'Jou afspraak is bevestig!';
                $link = '/calendar/calendar.php';
                $icon = '✅';
                $notifType = 'success';
                break;

            case 'ampte_change':
                $newAmp = $data['new_amp'] ?? 'Unknown';
                $titleKey = 'notif_ampte_change';
                $messageKey = 'notif_ampte_change_msg';
                $params = ['amp' => $newAmp];
                $title = '⭐ Amp Verander';
                $message = "Jou amp is verander na: {$newAmp}";
                $link = '/admin/index.php';
                $icon = '⭐';
                $notifType = 'ampte';
                break;

            default:
                $title = $data['title'] ?? 'Notification';
                $message = $data['message'] ?? '';
                $link = $data['link'] ?? '';
                $icon = $data['icon'] ?? '🔔';
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

        // Also send FCM push notification
        try {
            // Remove emoji from title for cleaner push notification
            $pushTitle = preg_replace('/[\x{1F300}-\x{1F9FF}]/u', '', $title);
            $pushTitle = trim($pushTitle);

            error_log("FCM: Attempting push to user $userId - Title: $pushTitle");

            $pushResult = sendPushToUser((int)$userId, $pushTitle, $message, [
                'type' => $notifType,
                'link' => $link,
                'notification_id' => $db->lastInsertId()
            ]);

            error_log("FCM: Push result: " . json_encode($pushResult));
        } catch (Exception $pushError) {
            // Log but don't fail - push is optional
            error_log('FCM push error: ' . $pushError->getMessage());
        }

        return true;

    } catch (Exception $e) {
        error_log('Create admin notification error: ' . $e->getMessage());
        return false;
    }
}
