<?php
declare(strict_types=1);
/**
 * POST /diary/api/delete.php   (JSON body {id}, X-CSRF-Token header)
 * Deletes one of my entries, and a copy an older version wrote to
 * calendar_events.
 */

require_once dirname(__DIR__, 2) . '/security/auth_gate.php';
require_once dirname(__DIR__) . '/lib/diary.php';

dy_require_post();

if (!isset($pdo) || !($pdo instanceof PDO)) {
    dy_fail('db_unavailable', 500);
}

$userId = dy_user_id();
$id = (int)(dy_input()['id'] ?? 0);
if ($userId <= 0 || $id <= 0) {
    dy_fail('bad_request');
}

try {
    $pdo->beginTransaction();

    $check = $pdo->prepare('SELECT calendar_event_id FROM diaries WHERE id = ? AND user_id = ? FOR UPDATE');
    $check->execute([$id, $userId]);
    $existing = $check->fetch(PDO::FETCH_ASSOC);
    if (!$existing) {
        $pdo->rollBack();
        dy_fail('not_found', 404);
    }

    $pdo->prepare('DELETE FROM diaries WHERE id = ? AND user_id = ?')->execute([$id, $userId]);

    $calId = (int)($existing['calendar_event_id'] ?? 0);
    if ($calId > 0) {
        $pdo->prepare('DELETE FROM calendar_events WHERE id = ? AND user_id = ?')->execute([$calId, $userId]);
    }

    $pdo->commit();
    dy_json(['success' => true]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Diary delete error: ' . $e->getMessage());
    dy_fail('server_error', 500);
}
