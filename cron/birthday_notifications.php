<?php
/**
 * Birthday Notifications Cron Job
 *
 * Run this script daily at 7:00 AM via cron:
 * 0 7 * * * php /path/to/oac-site/cron/birthday_notifications.php
 *
 * Or via URL (with cron service like cron-job.org):
 * https://oacapp.co.za/cron/birthday_notifications.php?key=YOUR_SECRET_KEY
 */

declare(strict_types=1);

// Security: Only allow CLI or authenticated web requests
$isCliMode = (php_sapi_name() === 'cli');
$secretKey = 'oac_birthday_cron_2024_secure'; // Change this to a secure random key

if (!$isCliMode) {
    // Web request - check secret key
    if (($_GET['key'] ?? '') !== $secretKey) {
        http_response_code(403);
        die('Forbidden');
    }
    header('Content-Type: application/json');
}

// Load dependencies
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../admin/config/fcm_config.php';
require_once __DIR__ . '/../includes/languages.php';

$pdo = db();
$results = ['sent' => 0, 'errors' => 0, 'birthdays' => []];

try {
    // Get today's date (month-day)
    $today = date('m-d');

    // Find all users with birthday today
    $stmt = $pdo->prepare("
        SELECT id, name, surname, email, language, birthdate
        FROM users
        WHERE status = 'approved'
        AND DATE_FORMAT(birthdate, '%m-%d') = ?
    ");
    $stmt->execute([$today]);
    $birthdayUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($birthdayUsers)) {
        $results['message'] = 'No birthdays today';
        if (!$isCliMode) echo json_encode($results);
        else echo "No birthdays today.\n";
        exit;
    }

    // SQLite notification database
    $notifDb = new PDO('sqlite:' . __DIR__ . '/../data/notifications.db');
    $notifDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create notifications table if not exists
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

    foreach ($birthdayUsers as $birthdayUser) {
        $birthdayUserId = (int)$birthdayUser['id'];
        $birthdayName = trim($birthdayUser['name'] . ' ' . $birthdayUser['surname']);
        $userLang = $birthdayUser['language'] ?? 'af';

        // Calculate age
        $birthYear = (int)date('Y', strtotime($birthdayUser['birthdate']));
        $currentYear = (int)date('Y');
        $age = $currentYear - $birthYear;

        $results['birthdays'][] = [
            'name' => $birthdayName,
            'age' => $age
        ];

        // Get all approved users to notify them (congregation members, same town, etc.)
        // For now, notify users in the same congregation
        $notifyStmt = $pdo->prepare("
            SELECT u.id, u.name, u.language
            FROM users u
            WHERE u.status = 'approved'
            AND u.id != ?
            AND u.congregation_id = (SELECT congregation_id FROM users WHERE id = ?)
        ");
        $notifyStmt->execute([$birthdayUserId, $birthdayUserId]);
        $usersToNotify = $notifyStmt->fetchAll(PDO::FETCH_ASSOC);

        // Also send birthday greeting to the birthday person themselves
        $selfNotifyStmt = $notifDb->prepare("
            INSERT INTO notifications (user_id, title, message, type, link, icon, title_key, message_key, params)
            VALUES (:user_id, :title, :message, :type, :link, :icon, :title_key, :message_key, :params)
        ");

        $selfTitle = __t('happy_birthday', $userLang) ?: 'Happy Birthday!';
        $selfMessage = __t('birthday_self_msg', $userLang) ?: "Congratulations on your birthday! May God bless you.";

        $selfNotifyStmt->execute([
            'user_id' => $birthdayUserId,
            'title' => "🎂 $selfTitle",
            'message' => $selfMessage,
            'type' => 'birthday',
            'link' => '/profile/index.php',
            'icon' => '🎂',
            'title_key' => 'happy_birthday',
            'message_key' => 'birthday_self_msg',
            'params' => json_encode(['age' => $age])
        ]);

        // Send FCM push to birthday person
        try {
            sendPushToUser($birthdayUserId, "Happy Birthday!", $selfMessage, [
                'type' => 'birthday',
                'link' => '/profile/index.php'
            ]);
            $results['sent']++;
        } catch (Exception $e) {
            $results['errors']++;
            error_log("Birthday FCM error for user $birthdayUserId: " . $e->getMessage());
        }

        // Notify congregation members
        foreach ($usersToNotify as $recipient) {
            $recipientId = (int)$recipient['id'];
            $recipientLang = $recipient['language'] ?? 'af';

            $notifyTitle = __t('birthday_notification', $recipientLang) ?: 'Birthday Today';
            $notifyMessage = str_replace(
                ['{name}', '{age}'],
                [$birthdayName, $age],
                __t('birthday_notification_msg', $recipientLang) ?: "{name} is celebrating their birthday today!"
            );

            // Insert notification
            $insertStmt = $notifDb->prepare("
                INSERT INTO notifications (user_id, title, message, type, link, icon, title_key, message_key, params)
                VALUES (:user_id, :title, :message, :type, :link, :icon, :title_key, :message_key, :params)
            ");

            $insertStmt->execute([
                'user_id' => $recipientId,
                'title' => "🎂 $notifyTitle",
                'message' => $notifyMessage,
                'type' => 'birthday',
                'link' => '/profile/index.php?user_id=' . $birthdayUserId,
                'icon' => '🎂',
                'title_key' => 'birthday_notification',
                'message_key' => 'birthday_notification_msg',
                'params' => json_encode(['name' => $birthdayName, 'age' => $age, 'user_id' => $birthdayUserId])
            ]);

            // Send FCM push notification
            try {
                sendPushToUser($recipientId, "Birthday Today", $notifyMessage, [
                    'type' => 'birthday',
                    'link' => '/profile/index.php?user_id=' . $birthdayUserId
                ]);
                $results['sent']++;
            } catch (Exception $e) {
                $results['errors']++;
                error_log("Birthday FCM error for recipient $recipientId: " . $e->getMessage());
            }
        }
    }

    $results['message'] = 'Birthday notifications sent successfully';

} catch (Exception $e) {
    $results['error'] = $e->getMessage();
    error_log('Birthday cron error: ' . $e->getMessage());
}

if (!$isCliMode) {
    echo json_encode($results, JSON_PRETTY_PRINT);
} else {
    echo "Birthday notifications complete.\n";
    echo "Sent: {$results['sent']}, Errors: {$results['errors']}\n";
    if (!empty($results['birthdays'])) {
        echo "Birthdays today:\n";
        foreach ($results['birthdays'] as $b) {
            echo "  - {$b['name']} (Age: {$b['age']})\n";
        }
    }
}
