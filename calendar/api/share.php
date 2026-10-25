<?php
declare(strict_types=1);
/**
 * POST /calendar/api/share.php  (X-CSRF-Token)
 * Share (or stop sharing) a room event or one of your visits with your
 * spouse. Diary entries are never shared from the calendar.
 * Fields: type (event|visit), id, share (1|0)
 * Answer: {success, shared}
 */

require_once __DIR__ . '/../../security/auth_gate.php';
require_once __DIR__ . '/../lib/calendar.php';
require_once __DIR__ . '/notifications/helper.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    cal_fail('db_unavailable', 500);
}
cal_require_post();

$me = cal_user($pdo, (int)$_SESSION['user_id']);
if (!$me) {
    cal_fail('forbidden', 403);
}
$userId = (int)$me['id'];
$type = (string)($_POST['type'] ?? '');
$id = (int)($_POST['id'] ?? 0);
$share = ($_POST['share'] ?? '') === '1';

if (!in_array($type, ['event', 'visit'], true) || $id <= 0) {
    cal_fail('invalid_id');
}

$spouseId = cal_spouse_id($pdo, $me);
if ($spouseId <= 0) {
    cal_fail('no_spouse');
}

try {
    // What is being shared, and may this user share it?
    if ($type === 'visit') {
        $st = $pdo->prepare('SELECT title, visit_date AS date FROM calendar_visits WHERE id = ? AND user_id = ? LIMIT 1');
        $st->execute([$id, $userId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        $title = $row ? cal_clean_line((string)$row['title']) : '';
    } else {
        $st = $pdo->prepare("SELECT room_id, text, event_at FROM posts WHERE id = ? AND type = 'event' LIMIT 1");
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row && !cal_room_rights($pdo, $userId, (int)$row['room_id'])) {
            $row = false;
        }
        $title = $row ? cal_split_post_text((string)$row['text'])[0] : '';
        if ($row) {
            $row['date'] = substr((string)$row['event_at'], 0, 10);
        }
    }
    if (!$row) {
        cal_fail('not_found', 404);
    }

    $st = $pdo->prepare('SELECT 1 FROM calendar_shared_events WHERE event_type = ? AND event_id = ? AND owner_user_id = ? AND shared_with_user_id = ? LIMIT 1');
    $st->execute([$type, $id, $userId, $spouseId]);
    $wasShared = (bool)$st->fetchColumn();

    $pdo->beginTransaction();
    $pdo->prepare('DELETE FROM calendar_shared_events WHERE event_type = ? AND event_id = ? AND owner_user_id = ?')
        ->execute([$type, $id, $userId]);
    if ($share) {
        $pdo->prepare('INSERT INTO calendar_shared_events (event_id, event_type, owner_user_id, shared_with_user_id, created_at)
                       VALUES (?, ?, ?, ?, NOW())')
            ->execute([$id, $type, $userId, $spouseId]);
    }
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Calendar share error: ' . $e->getMessage());
    cal_fail('server_error', 500);
}

cal_finish_response(['success' => true, 'shared' => $share]);

if ($share && !$wasShared && cal_rate_ok('cal_notify:' . $userId, 30)) {
    try {
        createCalendarNotification($spouseId, 'event_shared', [
            'from_name' => cal_name($me['name'] ?? '', $me['surname'] ?? ''),
            'event_title' => $title,
            'date' => (string)($row['date'] ?? ''),
            'uid' => $type . '-' . $id,
        ]);
    } catch (Throwable $e) {
        error_log('Calendar share notification error: ' . $e->getMessage());
    }
}
