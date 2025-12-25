<?php
// =====================================================================
// /admin/api/notifications/helper.php - Helper functions
// =====================================================================

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
                icon TEXT
            )
        ");
        
        $title = '';
        $message = '';
        $link = '';
        $icon = '';
        $notifType = 'info';
        
        switch ($type) {
            case 'account_approved':
                $title = '✅ Account Goedgekeur';
                $message = 'Jou rekening is goedgekeur. Jy kan nou aanmeld.';
                $link = '/admin/index.php';
                $icon = '✅';
                $notifType = 'success';
                break;
                
            case 'account_rejected':
                $title = '❌ Account Afgekeur';
                $message = 'Jou rekening is afgekeur. Kontak admin vir meer inligting.';
                $icon = '❌';
                $notifType = 'error';
                break;
                
            case 'spouse_request':
                $fromName = $data['from_name'] ?? 'Iemand';
                $title = '💍 Eggenoot Versoek';
                $message = "{$fromName} wil met jou as eggenoot koppel.";
                $link = '/admin/account.php';
                $icon = '💍';
                $notifType = 'spouse';
                break;
                
            case 'spouse_accepted':
                $fromName = $data['from_name'] ?? 'Jou eggenoot';
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
                $title = '📅 Afspraak Versoek';
                $message = "{$fromName} wil 'n afspraak maak op {$date} om {$time}.";
                $link = '/admin/index.php?tab=afsprake';
                $icon = '📅';
                $notifType = 'appointment';
                break;
                
            case 'appointment_confirmed':
                $title = '✅ Afspraak Bevestig';
                $message = 'Jou afspraak is bevestig!';
                $link = '/calendar/calendar.php';
                $icon = '✅';
                $notifType = 'success';
                break;
                
            case 'ampte_change':
                $newAmp = $data['new_amp'] ?? 'Unknown';
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
        error_log('Create admin notification error: ' . $e->getMessage());
        return false;
    }
}