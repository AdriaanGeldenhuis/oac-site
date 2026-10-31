<?php
declare(strict_types=1);
/**
 * GET /notifications/open.php?id=ID&s=SIGNATURE[&to=/path]
 *
 * Opening a notification takes it down. Every tap goes through here: on
 * the notifications page and on the phone's push (its click_url). The
 * notification is deleted, then the member goes on to where it points.
 *
 * Safe as a GET: it only ever deletes the signed-in member's own row, and
 * only with the signature made for that member and that row
 * (nt_open_path), which no other site can know. The way on is the row's
 * own link; when the row is gone already (deleted on the page, then the
 * old push tapped), ?to= — a path on this site only (nt_safe_link).
 */

require_once dirname(__DIR__) . '/security/auth_gate.php';
require_once __DIR__ . '/lib/notifications.php';

$userId = nt_user_id();
$id = isset($_GET['id']) && is_string($_GET['id']) && ctype_digit($_GET['id']) ? (int)$_GET['id'] : 0;
$sig = isset($_GET['s']) && is_string($_GET['s']) ? $_GET['s'] : '';
$fallback = isset($_GET['to']) && is_string($_GET['to']) ? nt_safe_link($_GET['to']) : null;

$target = null;
if ($id > 0 && $userId > 0) {
    try {
        $db = nt_db();
        $st = $db->prepare('SELECT link FROM notifications WHERE id = ? AND user_id = ? LIMIT 1');
        $st->execute([$id, $userId]);
        $row = $st->fetch();
        if ($row !== false) {
            $target = nt_safe_link($row['link'] ?? null);
            $expected = nt_open_sig($userId, $id);
            if ($expected !== null && $sig !== '' && hash_equals($expected, $sig)) {
                $del = $db->prepare('DELETE FROM notifications WHERE id = ? AND user_id = ?');
                $del->execute([$id, $userId]);
            }
        }
    } catch (Throwable $e) {
        // The member still gets where they were going
        error_log('Notifications open error: ' . $e->getMessage());
    }
}

header('Cache-Control: private, no-store');
header('Location: ' . ($target ?? $fallback ?? '/notifications/notifications.php'), true, 303);
exit;
