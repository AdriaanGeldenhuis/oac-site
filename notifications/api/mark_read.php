<?php
declare(strict_types=1);
/**
 * POST /notifications/api/mark_read.php  (JSON body, X-CSRF-Token header)
 *   {"action": "read", "ids": [1, 2]}      mark these as read
 *   {"action": "read_all"}                mark everything as read
 *   {"action": "delete", "ids": [1, 2]}   delete these
 *   {"action": "delete_read"}             delete everything already read
 * Only ever touches the signed-in user's own rows. Answers with the new counts.
 */

require_once dirname(__DIR__, 2) . '/security/auth_gate.php';
require_once dirname(__DIR__) . '/lib/notifications.php';

nt_require_post();
$userId = nt_user_id();
$in = nt_input();
$action = (string)($in['action'] ?? '');

$ids = [];
if (isset($in['ids']) && is_array($in['ids'])) {
    foreach (array_slice($in['ids'], 0, 200) as $id) {
        if (is_int($id) || (is_string($id) && ctype_digit($id))) {
            $id = (int)$id;
            if ($id > 0) $ids[$id] = $id;
        }
    }
    $ids = array_values($ids);
}

try {
    $db = nt_db();
    $changed = 0;
    $marks = $ids ? implode(',', array_fill(0, count($ids), '?')) : '';

    switch ($action) {
        case 'read':
            if (!$ids) nt_fail('invalid_input');
            $st = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0 AND id IN ($marks)");
            $st->execute(array_merge([$userId], $ids));
            $changed = $st->rowCount();
            break;

        case 'read_all':
            $st = $db->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0');
            $st->execute([$userId]);
            $changed = $st->rowCount();
            break;

        case 'delete':
            if (!$ids) nt_fail('invalid_input');
            $st = $db->prepare("DELETE FROM notifications WHERE user_id = ? AND id IN ($marks)");
            $st->execute(array_merge([$userId], $ids));
            $changed = $st->rowCount();
            break;

        case 'delete_read':
            $st = $db->prepare('DELETE FROM notifications WHERE user_id = ? AND is_read = 1');
            $st->execute([$userId]);
            $changed = $st->rowCount();
            break;

        default:
            nt_fail('invalid_action');
    }

    nt_json(['success' => true, 'changed' => $changed, 'counts' => nt_counts($db, $userId)]);
} catch (Throwable $e) {
    error_log('Notifications write error: ' . $e->getMessage());
    nt_fail('server_error', 500);
}
