<?php
/**
 * Calendar remind API
 *
 * Accepts a POST request with an event_id and creates a reminder post
 * in the appropriate room.  The reminder post text summarises the
 * event and uses the current user's context.  Only users with an
 * approved membership in the event's room may create reminders.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../session.php';
require_once __DIR__ . '/../../app/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$eventId = isset($_POST['event_id']) ? (int)$_POST['event_id'] : 0;
if ($eventId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid event_id']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

try {
    // Fetch the event post and its room
    $stmt = $pdo->prepare(
        'SELECT p.id, p.text, p.event_at, p.event_place, p.room_id
         FROM posts p
         WHERE p.id = :id AND p.type = "event" LIMIT 1'
    );
    $stmt->execute(['id' => $eventId]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$event) {
        http_response_code(404);
        echo json_encode(['error' => 'Event not found']);
        exit;
    }
    $roomId = (int)$event['room_id'];
    // Verify user has approved membership in this room
    $mStmt = $pdo->prepare('SELECT id FROM room_memberships WHERE room_id = :rid AND user_id = :uid AND status = "approved" LIMIT 1');
    $mStmt->execute(['rid' => $roomId, 'uid' => $userId]);
    if (!$mStmt->fetch(PDO::FETCH_ASSOC)) {
        http_response_code(403);
        echo json_encode(['error' => 'Not authorised to remind for this event']);
        exit;
    }
    // Build reminder post text
    $dt = null;
    if ($event['event_at']) {
        $dt = new DateTime($event['event_at']);
    }
    $dateStr = $dt ? $dt->format('l H:i') : '';
    $text = '';
    if ($dateStr) {
        $text = 'Sien julle ' . $dateStr;
    }
    if ($event['text']) {
        $text .= ' – ' . $event['text'];
    }
    // Insert reminder post
    $pdo->beginTransaction();
    $ins = $pdo->prepare('INSERT INTO posts (room_id, user_id, type, text, created_at) VALUES (:room_id,:user_id,:type,:text,NOW())');
    $ins->execute([
        'room_id' => $roomId,
        'user_id' => $userId,
        'type'    => 'post',
        'text'    => $text
    ]);
    $pdo->commit();
    echo json_encode(['success' => true]);
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
    exit;
}