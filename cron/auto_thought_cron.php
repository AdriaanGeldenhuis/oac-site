<?php
/**
 * Auto-cron for daily thought notifications.
 *
 * Include this file from a frequently-called endpoint (e.g. notification count).
 * It uses a timestamp lock file so the actual check only runs once every 5 minutes.
 *
 * Requires: $pdo (MySQL PDO) to be available in the calling scope.
 */

declare(strict_types=1);

function runThoughtAutoCron(PDO $pdo): void
{
    // Lock file to throttle: only run once every 5 minutes
    $lockFile = __DIR__ . '/../data/.thought_cron_last';
    $now = time();

    if (file_exists($lockFile)) {
        $last = (int)file_get_contents($lockFile);
        if (($now - $last) < 300) { // 5 minutes
            return; // Too soon, skip
        }
    }

    // Update lock immediately to prevent concurrent runs
    @file_put_contents($lockFile, (string)$now);

    try {
        // Check for today's thought where time has passed and notification not yet sent
        $stmt = $pdo->prepare("
            SELECT dt.id, dt.content, dt.author, dt.created_by, u.town_id
            FROM daily_thoughts dt
            LEFT JOIN users u ON u.id = dt.created_by
            WHERE dt.display_date = CURDATE()
              AND dt.notification_sent = 0
              AND (dt.display_time IS NULL OR dt.display_time <= CURTIME())
            LIMIT 1
        ");
        $stmt->execute();
        $thought = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$thought) {
            return; // Nothing pending
        }

        // Mark as sent immediately to prevent duplicate sends
        $pdo->prepare("UPDATE daily_thoughts SET notification_sent = 1 WHERE id = ?")
            ->execute([$thought['id']]);

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
                    'type' => 'info',
                    'link' => '/gedagtes/gedagtes.php',
                    'icon' => '',
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

    } catch (Throwable $e) {
        error_log('Auto-cron thought error: ' . $e->getMessage());
    }
}
