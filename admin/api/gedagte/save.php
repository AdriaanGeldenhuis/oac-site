<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/security/config.php';
require_once dirname(__DIR__, 3) . '/security/session.php';
require_once dirname(__DIR__, 3) . '/security/auth.php';

ob_start();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if (!auth_logged_in()) {
    ob_end_clean();
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$userId = auth_user_id();

// Check Elder permissions (amp_id 1-5)
try {
    $stmt = $pdo->prepare('SELECT amp_id FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $ampId = (int)($user['amp_id'] ?? 0);
    if ($ampId !== 0 && ($ampId < 1 || $ampId > 5)) {
        ob_end_clean();
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Insufficient permissions']);
        exit;
    }
} catch (Throwable $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$content = trim((string)($_POST['content'] ?? ''));
$author = trim((string)($_POST['author'] ?? ''));
$displayDate = trim((string)($_POST['display_date'] ?? ''));
$displayTime = trim((string)($_POST['display_time'] ?? ''));

if ($content === '') {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Content is required']);
    exit;
}

if ($displayDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $displayDate)) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Valid date is required']);
    exit;
}

// Validate time format (HH:MM)
if ($displayTime !== '' && !preg_match('/^\d{2}:\d{2}$/', $displayTime)) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Valid time is required (HH:MM)']);
    exit;
}

// Sanitize content (strip dangerous HTML)
$content = strip_tags($content, '<p><br><strong><em><b><i>');

try {
    // Create table if not exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS daily_thoughts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            content TEXT NOT NULL,
            author VARCHAR(255) DEFAULT NULL,
            display_date DATE NOT NULL,
            display_time TIME DEFAULT NULL,
            notification_sent TINYINT(1) DEFAULT 0,
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_date (display_date),
            INDEX idx_display_date (display_date),
            INDEX idx_created_by (created_by)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Add columns if they don't exist (for existing tables)
    try {
        $pdo->exec("ALTER TABLE daily_thoughts ADD COLUMN display_time TIME DEFAULT NULL AFTER display_date");
    } catch (Throwable $e) { /* column already exists */ }
    try {
        $pdo->exec("ALTER TABLE daily_thoughts ADD COLUMN notification_sent TINYINT(1) DEFAULT 0 AFTER display_time");
    } catch (Throwable $e) { /* column already exists */ }

    // Insert or update (replace if same date exists)
    $stmt = $pdo->prepare('
        INSERT INTO daily_thoughts (content, author, display_date, display_time, notification_sent, created_by)
        VALUES (?, ?, ?, ?, 0, ?)
        ON DUPLICATE KEY UPDATE content = VALUES(content), author = VALUES(author), display_time = VALUES(display_time), notification_sent = 0, created_by = VALUES(created_by)
    ');
    $stmt->execute([
        $content,
        $author !== '' ? $author : null,
        $displayDate,
        $displayTime !== '' ? $displayTime . ':00' : null,
        $userId
    ]);

    // --- Send notifications immediately if thought is for today and time has passed ---
    // Use MySQL CURDATE()/CURTIME() to match the same timezone as gedagtes/api/list.php.
    // For future dates/times, the auto-cron (cron/thought_notifications.php) handles it.
    $nowRow = $pdo->query("SELECT CURDATE() AS today, CURTIME() AS now_time")->fetch(PDO::FETCH_ASSOC);
    $today = $nowRow['today'];
    $now = $nowRow['now_time'];
    $timeValue = $displayTime !== '' ? $displayTime . ':00' : null;
    $shouldNotify = ($displayDate === $today) && ($timeValue === null || $timeValue <= $now);

    if ($shouldNotify) {
        try {
            require_once dirname(__DIR__, 2) . '/config/fcm_config.php';
            require_once dirname(__DIR__, 3) . '/includes/languages.php';

            // Get creator's town_id for scoping
            $townStmt = $pdo->prepare('SELECT town_id FROM users WHERE id = ? LIMIT 1');
            $townStmt->execute([$userId]);
            $creatorTown = $townStmt->fetch(PDO::FETCH_ASSOC);
            $creatorTownId = $creatorTown ? (int)($creatorTown['town_id'] ?? 0) : 0;

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
            $notifDb = new PDO('sqlite:' . dirname(__DIR__, 3) . '/data/notifications.db');
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

            $pushBody = mb_strlen($content) > 200 ? mb_substr($content, 0, 197) . '...' : $content;
            $pushBody = strip_tags($pushBody);

            $insertNotif = $notifDb->prepare("
                INSERT INTO notifications (user_id, title, message, type, link, icon, title_key, message_key, params)
                VALUES (:user_id, :title, :message, :type, :link, :icon, :title_key, :message_key, :params)
            ");

            foreach ($users as $u) {
                $uid = (int)$u['id'];
                $uLang = $u['language'] ?? 'af';
                $title = __t('notif_thought_title', $uLang) ?: 'Gedagte van die Dag';

                // In-app notification
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
                    error_log("Thought save notif insert error user $uid: " . $e->getMessage());
                }

                // FCM push notification
                try {
                    sendPushToUser($uid, $title, $pushBody, [
                        'type' => 'thought',
                        'link' => '/gedagtes/gedagtes.php',
                        'titleKey' => 'notif_thought_title'
                    ]);
                } catch (Throwable $e) {
                    error_log("Thought save FCM error user $uid: " . $e->getMessage());
                }
            }

            // Mark notification as sent
            $pdo->prepare("UPDATE daily_thoughts SET notification_sent = 1 WHERE display_date = ?")->execute([$displayDate]);

        } catch (Throwable $e) {
            error_log('Thought save notification error: ' . $e->getMessage());
            // Don't fail the save if notifications fail
        }
    }

    ob_end_clean();
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    error_log('Daily thought save error: ' . $e->getMessage());
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to save']);
}
exit;
