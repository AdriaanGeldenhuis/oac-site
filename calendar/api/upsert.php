<?php
declare(strict_types=1);
/**
 * POST /calendar/api/upsert.php  (X-CSRF-Token)
 * Create or update one of your own visits (calendar_visits).
 *
 * Room events are posts and are written through Gospel Media's own API
 * (/gospel_media/api/posts/*), so the feed and the calendar always agree on
 * rights, notifications and clean-up. Diary entries belong to /diary/.
 *
 * Fields: id, title, date (YYYY-MM-DD), time (HH:MM, empty = all day),
 * linked_user_id | person_name, location, notes, share_with_spouse (1/0)
 * Answer: {success, id, uid} or {success:false, error:<code>}
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

$type = (string)($_POST['type'] ?? 'visit');
if ($type !== 'visit') {
    cal_fail('unsupported_type');
}

// ===== VALIDATE =====
$id = max(0, (int)($_POST['id'] ?? 0));
$title = cal_clean_line((string)($_POST['title'] ?? ''));
$date = cal_date($_POST['date'] ?? null);
$rawTime = trim((string)($_POST['time'] ?? ''));
$allDay = ($_POST['all_day'] ?? '') === '1' || $rawTime === '';
$time = $allDay ? null : cal_time($rawTime);
$linkedUserId = max(0, (int)($_POST['linked_user_id'] ?? 0));
$personName = cal_clean_line((string)($_POST['person_name'] ?? ''));
$location = cal_clean_line((string)($_POST['location'] ?? ''));
$notes = cal_clean_text((string)($_POST['notes'] ?? ''));
$share = ($_POST['share_with_spouse'] ?? '') === '1';

if ($title === '') cal_fail('title_required');
if (cal_len($title) > 150) cal_fail('title_too_long');
if ($date === null) cal_fail('invalid_date');
if (!$allDay && $time === null) cal_fail('invalid_time');
if (cal_len($personName) > 120 || cal_len($location) > 200) cal_fail('too_long');
if (cal_len($notes) > 5000) cal_fail('notes_too_long');

// A linked person must be someone the picker offers (users.php): approved, same town, not yourself
$linkedUser = null;
if ($linkedUserId > 0) {
    $linkedUser = cal_user($pdo, $linkedUserId);
    $town = (int)($me['town_id'] ?? 0);
    if (!$linkedUser || $linkedUserId === $userId || ($linkedUser['status'] ?? '') !== 'approved'
        || $town <= 0 || (int)($linkedUser['town_id'] ?? 0) !== $town) {
        cal_fail('invalid_person');
    }
    $personName = '';
}

$spouseId = cal_spouse_id($pdo, $me);
if (!$spouseId) {
    $share = false;
}

$storedTime = $time === null ? '00:00:00' : $time . ':00';

try {
    $previous = null;
    if ($id > 0) {
        $st = $pdo->prepare('SELECT id, visit_date, visit_time, linked_user_id FROM calendar_visits WHERE id = ? AND user_id = ? LIMIT 1');
        $st->execute([$id, $userId]);
        $previous = $st->fetch(PDO::FETCH_ASSOC);
        if (!$previous) {
            cal_fail('not_found', 404);
        }
    }

    $wasShared = false;
    if ($id > 0 && $spouseId) {
        $st = $pdo->prepare("SELECT 1 FROM calendar_shared_events WHERE event_type = 'visit' AND event_id = ? AND owner_user_id = ? AND shared_with_user_id = ? LIMIT 1");
        $st->execute([$id, $userId, $spouseId]);
        $wasShared = (bool)$st->fetchColumn();
    }

    $pdo->beginTransaction();

    if ($id > 0) {
        $st = $pdo->prepare('UPDATE calendar_visits
                             SET title = ?, visit_date = ?, visit_time = ?, linked_user_id = ?, person_name = ?, location = ?, notes = ?
                             WHERE id = ? AND user_id = ?');
        $st->execute([$title, $date, $storedTime, $linkedUserId ?: null, $personName, $location, $notes, $id, $userId]);
    } else {
        $st = $pdo->prepare('INSERT INTO calendar_visits (user_id, title, visit_date, visit_time, linked_user_id, person_name, location, notes, created_at)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())');
        $st->execute([$userId, $title, $date, $storedTime, $linkedUserId ?: null, $personName, $location, $notes]);
        $id = (int)$pdo->lastInsertId();
    }

    // Sharing with the spouse: only this owner's own rows are touched
    $del = $pdo->prepare("DELETE FROM calendar_shared_events WHERE event_type = 'visit' AND event_id = ? AND owner_user_id = ?");
    $del->execute([$id, $userId]);
    if ($share) {
        $ins = $pdo->prepare("INSERT INTO calendar_shared_events (event_id, event_type, owner_user_id, shared_with_user_id, created_at)
                              VALUES (?, 'visit', ?, ?, NOW())");
        $ins->execute([$id, $userId, $spouseId]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Calendar upsert error: ' . $e->getMessage());
    cal_fail('server_error', 500);
}

cal_finish_response(['success' => true, 'id' => $id, 'uid' => 'visit-' . $id]);

// ===== NOTIFY (after the answer has gone) =====
$myName = cal_name($me['name'] ?? '', $me['surname'] ?? '');
$when = $date . ($time !== null ? ' ' . $time : '');
try {
    $isNewForLinked = $linkedUserId > 0 && (
        $previous === null
        || (int)($previous['linked_user_id'] ?? 0) !== $linkedUserId
        || (string)$previous['visit_date'] !== $date
        || cal_stored_time($previous['visit_time'] ?? null) !== $time
    );
    if ($isNewForLinked && cal_rate_ok('cal_notify:' . $userId, 30)) {
        createCalendarNotification($linkedUserId, 'visit_scheduled', [
            'person_name' => $myName,
            'date' => $date,
            'when' => $when,
        ]);
    }
    if ($share && !$wasShared && cal_rate_ok('cal_notify:' . $userId, 30)) {
        createCalendarNotification($spouseId, 'event_shared', [
            'from_name' => $myName,
            'event_title' => $title,
            'date' => $date,
            'uid' => 'visit-' . $id,
        ]);
    }
} catch (Throwable $e) {
    error_log('Calendar upsert notification error: ' . $e->getMessage());
}
