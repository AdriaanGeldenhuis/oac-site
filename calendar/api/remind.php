<?php
declare(strict_types=1);
/**
 * POST /calendar/api/remind.php  (X-CSRF-Token)
 * Send the members of a room a reminder about an upcoming event in it.
 * Who may: whoever may post in that room (Gospel Media's rules). Who gets
 * it: the same people Gospel Media tells about a new post in that room.
 * Each gets a notification in their own language that opens the calendar
 * on the event. At most once an hour per event, ten an hour per person.
 * Fields: event_id   Answer: {success} or {success:false, error}
 */

require_once __DIR__ . '/../../security/auth_gate.php';
require_once __DIR__ . '/../lib/calendar.php';
require_once __DIR__ . '/notifications/helper.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    cal_fail('db_unavailable', 500);
}
cal_require_post();

$userId = (int)$_SESSION['user_id'];
$eventId = (int)($_POST['event_id'] ?? 0);
if ($eventId <= 0) {
    cal_fail('invalid_id');
}

try {
    $st = $pdo->prepare("SELECT id, room_id, text, event_at FROM posts WHERE id = ? AND type = 'event' LIMIT 1");
    $st->execute([$eventId]);
    $event = $st->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Calendar remind error: ' . $e->getMessage());
    cal_fail('server_error', 500);
}
if (!$event) {
    cal_fail('not_found', 404);
}

$rights = cal_room_rights($pdo, $userId, (int)$event['room_id']);
if (!$rights) {
    cal_fail('not_found', 404);
}
$room = $rights['room'];
$roomType = strtolower((string)($room['type'] ?? ''));
if (!$rights['post'] || !in_array($roomType, ['gemeente', 'opsienerskap', 'jeug', 'sondagskool'], true)) {
    cal_fail('forbidden', 403);
}

$date = substr((string)$event['event_at'], 0, 10);
$time = cal_stored_time(substr((string)$event['event_at'], 11, 5));
if (cal_date($date) === null || ($date . ' ' . ($time ?? '23:59')) < cal_now()->format('Y-m-d H:i')) {
    cal_fail('event_past');
}

if (!cal_rate_ok('cal_remind:' . $userId . ':' . $eventId, 1)) {
    cal_fail('reminder_recent', 429);
}
if (!cal_rate_ok('cal_remind:' . $userId, 10)) {
    cal_fail('rate_limited', 429);
}

cal_finish_response(['success' => true]);

// ===== FAN OUT (after the answer has gone) =====
try {
    $townId = (int)($room['town_id'] ?? 0);
    $gemeenteId = (int)($room['gemeente_id'] ?? 0);
    $roomId = (int)$room['id'];
    $members = [];

    // The same recipients as gospel_media/api/posts/create.php
    if ($roomType === 'gemeente' && $gemeenteId > 0) {
        $q = $pdo->prepare("SELECT id FROM users WHERE congregation_id = ? AND id != ? AND status = 'approved'");
        $q->execute([$gemeenteId, $userId]);
        $members = $q->fetchAll(PDO::FETCH_COLUMN);
    } elseif ($townId > 0) {
        $q = $pdo->prepare("SELECT DISTINCT u.id FROM users u
                            WHERE u.id != ? AND u.status = 'approved'
                              AND (u.town_id = ? OR u.id IN (SELECT rm.user_id FROM room_memberships rm WHERE rm.room_id = ?))");
        $q->execute([$userId, $townId, $roomId]);
        $members = $q->fetchAll(PDO::FETCH_COLUMN);
    }

    [$title] = cal_split_post_text((string)$event['text']);
    $sent = createCalendarNotifications($members, 'event_reminder', [
        'event_title' => $title,
        'when' => $date . ($time !== null ? ' ' . $time : ''),
        'room_name' => cal_room_label($roomType, (string)($room['name'] ?? ''), 'af'),
        'date' => $date,
        'uid' => 'event-' . $eventId,
    ]);
    error_log("Calendar reminder: event $eventId, room $roomId, $sent notified");
} catch (Throwable $e) {
    error_log('Calendar remind fan-out error: ' . $e->getMessage());
}
