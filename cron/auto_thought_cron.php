<?php
/**
 * Auto-cron for daily thought notifications.
 *
 * Include this file from a frequently-called endpoint (e.g. notification count).
 * A lock file throttles general checks to once every 5 minutes, but it also
 * remembers the next scheduled display_time so the throttle is bypassed the
 * moment a thought becomes due — the 07:00 push fires on the first poll at or
 * after 07:00 instead of up to 5 minutes late.
 *
 * Requires: $pdo (MySQL PDO) to be available in the calling scope.
 */

declare(strict_types=1);

function runThoughtAutoCron(PDO $pdo): void
{
    $lockFile = __DIR__ . '/../data/.thought_cron_last';
    $now = time();

    // Read lock state: {"last": <ts of last check>, "next_due": <ts|null>}
    $lock = ['last' => 0, 'next_due' => null];
    if (file_exists($lockFile)) {
        $raw = trim((string)@file_get_contents($lockFile));
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $lock['last'] = (int)($decoded['last'] ?? 0);
            $lock['next_due'] = isset($decoded['next_due']) && $decoded['next_due'] !== null
                ? (int)$decoded['next_due'] : null;
        } elseif (ctype_digit($raw)) {
            // Legacy format: plain unix timestamp
            $lock['last'] = (int)$raw;
        }
    }

    // Skip unless a scheduled thought is due now, or the 5-minute throttle elapsed
    $scheduledDue = ($lock['next_due'] !== null && $now >= $lock['next_due']);
    if (!$scheduledDue && ($now - $lock['last']) < 300) {
        return;
    }

    // Update lock immediately to prevent concurrent runs (next_due recomputed below)
    @file_put_contents($lockFile, json_encode(['last' => $now, 'next_due' => $lock['next_due']]), LOCK_EX);

    // Compare against South African local time so scheduled display_time values
    // match what admins see in the UI — computed in PHP and passed as query
    // parameters so the shared PDO connection's session settings stay untouched.
    $sast = new DateTime('now', new DateTimeZone('Africa/Johannesburg'));
    $today = $sast->format('Y-m-d');
    $nowTime = $sast->format('H:i:s');

    try {
        // Check for today's thought where time has passed and notification not yet sent
        $stmt = $pdo->prepare("
            SELECT dt.id, dt.content, dt.author, dt.created_by, u.town_id
            FROM daily_thoughts dt
            LEFT JOIN users u ON u.id = dt.created_by
            WHERE dt.display_date = ?
              AND dt.notification_sent = 0
              AND (dt.display_time IS NULL OR dt.display_time <= ?)
            LIMIT 1
        ");
        $stmt->execute([$today, $nowTime]);
        $thought = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($thought) {
            // Atomically claim the thought so a concurrent request (or the
            // standalone cron) can never send the same push twice.
            $claim = $pdo->prepare("UPDATE daily_thoughts SET notification_sent = 1 WHERE id = ? AND notification_sent = 0");
            $claim->execute([$thought['id']]);

            if ($claim->rowCount() > 0) {
                sendThoughtNotificationsToUsers($pdo, $thought);
            }
        }

        // Remember when the next scheduled thought becomes due so the throttle
        // can be bypassed at exactly that moment.
        $nextStmt = $pdo->prepare("
            SELECT MIN(display_time) FROM daily_thoughts
            WHERE display_date = ? AND notification_sent = 0
              AND display_time IS NOT NULL AND display_time > ?
        ");
        $nextStmt->execute([$today, $nowTime]);
        $nextTime = $nextStmt->fetchColumn();

        $nextDue = null;
        if ($nextTime) {
            $nextDt = DateTime::createFromFormat('Y-m-d H:i:s', $today . ' ' . $nextTime, new DateTimeZone('Africa/Johannesburg'));
            if ($nextDt) {
                $nextDue = $nextDt->getTimestamp();
            }
        }

        @file_put_contents($lockFile, json_encode(['last' => $now, 'next_due' => $nextDue]), LOCK_EX);

    } catch (Throwable $e) {
        error_log('Auto-cron thought error: ' . $e->getMessage());
    }
}

/**
 * Fan the thought out to all recipients: in-app notification + FCM push.
 */
function sendThoughtNotificationsToUsers(PDO $pdo, array $thought): void
{
    require_once __DIR__ . '/../admin/config/fcm_config.php';
    require_once __DIR__ . '/../includes/languages.php';

    $creatorTownId = $thought['town_id'] ? (int)$thought['town_id'] : null;

    // Get approved users in the same town
    if ($creatorTownId) {
        $userStmt = $pdo->prepare("SELECT id, language FROM users WHERE status = 'approved' AND town_id = ?");
        $userStmt->execute([$creatorTownId]);
    } else {
        $userStmt = $pdo->prepare("SELECT id, language FROM users WHERE status = 'approved'");
        $userStmt->execute();
    }
    $users = $userStmt->fetchAll(PDO::FETCH_ASSOC);

    // SQLite notification database
    $notifDb = new PDO('sqlite:' . __DIR__ . '/../data/notifications.db');
    $notifDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $notifDb->exec("
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

    // Migrate older databases that pre-date the translation-key columns
    $cols = $notifDb->query("PRAGMA table_info(notifications)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('title_key', $cols)) {
        $notifDb->exec("ALTER TABLE notifications ADD COLUMN title_key TEXT");
    }
    if (!in_array('message_key', $cols)) {
        $notifDb->exec("ALTER TABLE notifications ADD COLUMN message_key TEXT");
    }
    if (!in_array('params', $cols)) {
        $notifDb->exec("ALTER TABLE notifications ADD COLUMN params TEXT");
    }

    $thoughtContent = strip_tags($thought['content']);
    $pushBody = mb_strlen($thoughtContent) > 200
        ? mb_substr($thoughtContent, 0, 197) . '...'
        : $thoughtContent;

    $insertNotif = $notifDb->prepare("
        INSERT INTO notifications (user_id, title, message, type, link, icon, title_key, message_key, params)
        VALUES (:user_id, :title, :message, :type, :link, :icon, :title_key, :message_key, :params)
    ");

    foreach ($users as $u) {
        $uid = (int)$u['id'];
        $uLang = $u['language'] ?? 'af';
        $title = __t('notif_thought_title', $uLang) ?: 'Gedagte van die Dag';

        try {
            $insertNotif->execute([
                'user_id' => $uid,
                'title' => $title,
                'message' => $pushBody,
                'type' => 'thought',
                'link' => '/gedagtes/gedagtes.php',
                'icon' => '💭',
                'title_key' => 'notif_thought_title',
                'message_key' => null,
                'params' => json_encode(['content' => $pushBody])
            ]);
        } catch (Throwable $e) {
            error_log("Auto-cron thought notif insert error user $uid: " . $e->getMessage());
        }

        try {
            sendPushToUser($uid, $title, $pushBody, [
                'type' => 'thought',
                'link' => '/gedagtes/gedagtes.php',
                'titleKey' => 'notif_thought_title'
            ]);
        } catch (Throwable $e) {
            error_log("Auto-cron thought FCM error user $uid: " . $e->getMessage());
        }
    }

    error_log("Auto-cron: Sent thought #{$thought['id']} notifications to " . count($users) . " users");
}
