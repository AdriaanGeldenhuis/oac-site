<?php
// =====================================================================
// /notifications/api/list.php
// =====================================================================

require_once __DIR__ . '/../../security/auth_gate.php';
require_once __DIR__ . '/../../includes/languages.php';

header('Content-Type: application/json; charset=utf-8');

// Get user's language preference
$pageLang = $_SESSION['language'] ?? 'af';

/**
 * Translate a notification title/message using translation key and params
 */
function translateNotification(string $text, ?string $key, ?string $paramsJson, string $lang): string {
    // If we have a translation key, use it
    if ($key) {
        $translated = __t($key, $lang);
        // If translation exists and is different from the key, use it
        if ($translated !== $key) {
            // Replace placeholders with params
            if ($paramsJson) {
                $params = json_decode($paramsJson, true);
                if (is_array($params)) {
                    foreach ($params as $placeholder => $value) {
                        if (!is_scalar($value) && $value !== null) continue;
                        $translated = str_replace('{' . $placeholder . '}', (string)$value, $translated);
                    }
                }
            }
            return $translated;
        }
    }
    // Fallback to original text
    return $text;
}

/**
 * Fallback translation for legacy notifications without translation keys
 * Detects known Afrikaans patterns and translates them
 */
function translateLegacyTitle(string $title, string $lang): string {
    // If already in target language or Afrikaans, return as-is for Afrikaans
    if ($lang === 'af') return $title;

    // Known title patterns (Afrikaans -> translation key)
    $titlePatterns = [
        '/^💬\s*Nuwe Kommentaar$/u' => 'notif_new_comment',
        '/^(❤️|🙏)\s*Reaksie$/u' => 'notif_reaction',
        '/^✍️\s*Nuwe Plasing$/u' => 'notif_new_post',
        '/^🏷️\s*Ge-tag$/u' => 'notif_tagged',
        '/^⏰\s*Herinnering$/u' => 'notif_event_reminder',
        '/^📅\s*(Aktiwiteit|Gebeurtenis) Gedeel$/u' => 'notif_event_shared',
        '/^📓\s*Dagboek Herinnering$/u' => 'notif_diary_reminder',
        '/^🏠\s*Besoek Geskeduleer$/u' => 'notif_visit_scheduled',
        '/^✅\s*Account Goedgekeur$/u' => 'notif_account_approved',
        '/^❌\s*Account Afgekeur$/u' => 'notif_account_rejected',
        '/^💍\s*Eggenoot Versoek$/u' => 'notif_spouse_request',
        '/^💕\s*Eggenoot Gekoppel$/u' => 'notif_spouse_accepted',
        '/^📅\s*Afspraak Versoek$/u' => 'notif_appointment_request',
        '/^✅\s*Afspraak Bevestig$/u' => 'notif_appointment_confirmed',
        '/^⭐\s*Amp Verander$/u' => 'notif_ampte_change',
    ];

    foreach ($titlePatterns as $pattern => $key) {
        if (preg_match($pattern, $title, $matches)) {
            $translated = __t($key, $lang);
            if ($translated !== $key) {
                // For reaction, preserve the emoji
                if ($key === 'notif_reaction' && isset($matches[1])) {
                    $translated = str_replace('{emoji}', $matches[1], $translated);
                }
                return $translated;
            }
        }
    }

    return $title;
}

/**
 * Fallback translation for legacy notification messages
 */
function translateLegacyMessage(string $message, string $lang): string {
    if ($lang === 'af' || empty($message)) return $message;

    // Pattern: "{name} het op jou plasing gereageer."
    if (preg_match('/^(.+?) het op jou plasing gereageer\.$/u', $message, $m)) {
        $translated = __t('notif_new_comment_msg', $lang);
        return str_replace('{name}', $m[1], $translated);
    }

    // Pattern: "{name} het {emoji} op jou plasing gegee."
    if (preg_match('/^(.+?) het (❤️|🙏) op jou plasing gegee\.$/u', $message, $m)) {
        $translated = __t('notif_reaction_msg', $lang);
        $translated = str_replace('{name}', $m[1], $translated);
        return str_replace('{emoji}', $m[2], $translated);
    }

    // Pattern: "{name} het in {room} geplaas."
    if (preg_match('/^(.+?) het in (.+?) geplaas\.$/u', $message, $m)) {
        $translated = __t('notif_new_post_msg', $lang);
        $translated = str_replace('{name}', $m[1], $translated);
        return str_replace('{room}', $m[2], $translated);
    }

    // Pattern: "{name} het jou in 'n plasing ge-tag."
    if (preg_match("/^(.+?) het jou in 'n plasing ge-tag\.$/u", $message, $m)) {
        $translated = __t('notif_tagged_msg', $lang);
        return str_replace('{name}', $m[1], $translated);
    }

    // Pattern: "{name} wil met jou as eggenoot koppel."
    if (preg_match('/^(.+?) wil met jou as eggenoot koppel\.$/u', $message, $m)) {
        $translated = __t('notif_spouse_request_msg', $lang);
        return str_replace('{name}', $m[1], $translated);
    }

    // Pattern: "{name} het jou versoek aanvaar!"
    if (preg_match('/^(.+?) het jou versoek aanvaar!$/u', $message, $m)) {
        $translated = __t('notif_spouse_accepted_msg', $lang);
        return str_replace('{name}', $m[1], $translated);
    }

    // Pattern: "Jou rekening is goedgekeur..."
    if (strpos($message, 'Jou rekening is goedgekeur') !== false) {
        return __t('notif_account_approved_msg', $lang);
    }

    // Pattern: "Jou rekening is afgekeur..."
    if (strpos($message, 'Jou rekening is afgekeur') !== false) {
        return __t('notif_account_rejected_msg', $lang);
    }

    // Pattern: "Jou afspraak is bevestig!"
    if (strpos($message, 'Jou afspraak is bevestig') !== false) {
        return __t('notif_appointment_confirmed_msg', $lang);
    }

    // Pattern: "Jou amp is verander na: {amp}"
    if (preg_match('/^Jou amp is verander na: (.+)$/u', $message, $m)) {
        $translated = __t('notif_ampte_change_msg', $lang);
        return str_replace('{amp}', $m[1], $translated);
    }

    return $message;
}

try {
    // Ensure data directory exists (tolerate concurrent creation)
    $dataDir = __DIR__ . '/../../data';
    if (!is_dir($dataDir) && !@mkdir($dataDir, 0775, true) && !is_dir($dataDir)) {
        throw new Exception('Failed to create data directory');
    }

    $dbPath = $dataDir . '/notifications.db';

    // Create database connection
    $db = new PDO('sqlite:' . $dbPath);
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

    $userId = $_SESSION['user_id'] ?? null;

    if (!$userId) {
        throw new Exception('User not logged in');
    }

    $stmt = $db->prepare("
        SELECT * FROM notifications
        WHERE user_id = :user_id
        ORDER BY created_at DESC
        LIMIT 100
    ");

    $stmt->execute(['user_id' => $userId]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Translate notifications based on user's language
    foreach ($notifications as &$notif) {
        $titleKey = $notif['title_key'] ?? null;
        $messageKey = $notif['message_key'] ?? null;
        $params = $notif['params'] ?? null;

        // Try translation key first
        $notif['title'] = translateNotification(
            $notif['title'],
            $titleKey,
            $params,
            $pageLang
        );

        // If no translation key, try legacy pattern matching
        if (!$titleKey) {
            $notif['title'] = translateLegacyTitle($notif['title'], $pageLang);
        }

        $notif['message'] = translateNotification(
            $notif['message'] ?? '',
            $messageKey,
            $params,
            $pageLang
        );

        // If no translation key, try legacy pattern matching
        if (!$messageKey) {
            $notif['message'] = translateLegacyMessage($notif['message'] ?? '', $pageLang);
        }

        // Remove internal keys from response
        unset($notif['title_key'], $notif['message_key'], $notif['params']);
    }
    unset($notif);

    echo json_encode([
        'success' => true,
        'notifications' => $notifications
    ]);

} catch (Exception $e) {
    error_log('Notifications list error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load notifications'
    ]);
}
