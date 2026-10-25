<?php
// =====================================================================
// /prayers/api/notifications/helper.php
// Prayer notifications, stored like every other area (data/notifications.db
// with translation keys, so the notifications page shows them in the
// reader's language) and pushed to the phone in the recipient's language.
// Links open the prayer itself: /prayers/prayers.php#post-ID
// (#post-ID-c also opens its comments).
// =====================================================================

require_once __DIR__ . '/../../../admin/config/fcm_config.php';
require_once __DIR__ . '/../../../notifications/lib/notifications.php';

/** [title key, message key, params, link, icon] for a prayer notification */
function prayerNotificationSpec(string $type, array $data): ?array {
    $postId = (int)($data['post_id'] ?? 0);
    $link = '/prayers/prayers.php' . ($postId > 0 ? '#post-' . $postId : '');

    switch ($type) {
        case 'reaction_on_post':
            $emoji = ($data['reaction_type'] ?? 'heart') === 'pray' ? '🙏' : '❤️';
            return ['notif_reaction', 'notif_reaction_msg',
                ['name' => (string)($data['reactor_name'] ?? ''), 'emoji' => $emoji], $link, $emoji];

        case 'comment_on_post':
            return ['notif_new_comment', 'notif_new_comment_msg',
                ['name' => (string)($data['commenter_name'] ?? '')], $link . ($postId > 0 ? '-c' : ''), '💬'];
    }
    return null;
}

function prayerNotificationText(string $key, string $lang, array $params): string {
    $text = __t($key, $lang);
    foreach ($params as $name => $value) {
        $text = str_replace('{' . $name . '}', (string)$value, $text);
    }
    return $text;
}

function createPrayerNotification($pdo, $userId, $type, $data = []) {
    $userId = (int)$userId;
    $spec = prayerNotificationSpec((string)$type, (array)$data);
    if (!$spec || $userId <= 0) return false;
    [$titleKey, $messageKey, $params, $link, $icon] = $spec;

    try {
        // The stored text is the Afrikaans fallback; readers get their own language from the keys
        $db = nt_db();
        $stmt = $db->prepare("
            INSERT INTO notifications (user_id, title, message, type, link, icon, title_key, message_key, params)
            VALUES (:user_id, :title, :message, 'prayer', :link, :icon, :title_key, :message_key, :params)
        ");
        $stmt->execute([
            'user_id' => $userId,
            'title' => prayerNotificationText($titleKey, 'af', $params),
            'message' => prayerNotificationText($messageKey, 'af', $params),
            'link' => $link,
            'icon' => $icon,
            'title_key' => $titleKey,
            'message_key' => $messageKey,
            'params' => json_encode($params, JSON_UNESCAPED_UNICODE),
        ]);
        $notificationId = $db->lastInsertId();
    } catch (Throwable $e) {
        error_log('Create prayer notification error: ' . $e->getMessage());
        return false;
    }

    // Push, in the recipient's own language
    try {
        if ($pdo instanceof PDO) {
            $st = $pdo->prepare('SELECT language FROM users WHERE id = ? LIMIT 1');
            $st->execute([$userId]);
            $lang = (string)($st->fetchColumn() ?: 'af');
            $lang = in_array($lang, SUPPORTED_LANGS, true) ? $lang : 'af';

            $title = prayerNotificationText($titleKey, $lang, $params);
            $title = trim(preg_replace('/[\x{1F000}-\x{1FAFF}\x{2300}-\x{23FF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{FE0F}\x{200D}]/u', '', $title) ?? $title);
            sendPushToUser($userId, $title !== '' ? $title : __t('prayers', $lang), prayerNotificationText($messageKey, $lang, $params), [
                'type' => 'prayer',
                'link' => $link,
                'notification_id' => $notificationId,
                'titleKey' => $titleKey,
                'messageKey' => $messageKey,
                'params' => $params,
            ]);
        }
    } catch (Throwable $e) {
        error_log('FCM Prayers push error: ' . $e->getMessage());
    }
    return true;
}
