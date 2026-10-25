<?php
declare(strict_types=1);
/**
 * POST /calendar/api/delete.php  (X-CSRF-Token)
 * Delete one of your own visits, with its spouse share.
 *
 * Room events are deleted through /gospel_media/api/posts/delete.php (the
 * Gospel Media rules and clean-up of comments, reactions and photos);
 * diary entries through /diary/.
 *
 * Fields: id, type=visit   Answer: {success} or {success:false, error}
 */

require_once __DIR__ . '/../../security/auth_gate.php';
require_once __DIR__ . '/../lib/calendar.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    cal_fail('db_unavailable', 500);
}
cal_require_post();

$userId = (int)$_SESSION['user_id'];
$id = (int)($_POST['id'] ?? 0);
$type = (string)($_POST['type'] ?? 'visit');

if ($type !== 'visit') {
    cal_fail('unsupported_type');
}
if ($id <= 0) {
    cal_fail('invalid_id');
}

try {
    $pdo->beginTransaction();

    $del = $pdo->prepare('DELETE FROM calendar_visits WHERE id = ? AND user_id = ?');
    $del->execute([$id, $userId]);
    if ($del->rowCount() === 0) {
        $pdo->rollBack();
        cal_fail('not_found', 404);
    }

    $pdo->prepare("DELETE FROM calendar_shared_events WHERE event_type = 'visit' AND event_id = ? AND owner_user_id = ?")
        ->execute([$id, $userId]);

    $pdo->commit();
    cal_json(['success' => true]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Calendar delete error: ' . $e->getMessage());
    cal_fail('server_error', 500);
}
