<?php
declare(strict_types=1);
/**
 * POST /diary/api/update.php   (JSON body, X-CSRF-Token header)
 * {id, date, time, title, body, mood, weather, tags[]} → {success, entry}
 *
 * Only my own entry. A copy an older version wrote to calendar_events is
 * removed here, so stale private text does not linger in another table.
 */

require_once dirname(__DIR__, 2) . '/security/auth_gate.php';
require_once dirname(__DIR__) . '/lib/diary.php';

dy_require_post();

if (!isset($pdo) || !($pdo instanceof PDO)) {
    dy_fail('db_unavailable', 500);
}

$userId = dy_user_id();
$input = dy_input();
$id = (int)($input['id'] ?? 0);
if ($userId <= 0 || $id <= 0) {
    dy_fail('bad_request');
}

[$fields, $error] = dy_entry_from_input($input);
if ($error !== null) {
    dy_fail($error);
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

    $st = $pdo->prepare('UPDATE diaries
                         SET date = ?, time = ?, title = ?, body = ?, mood = ?, weather = ?, tags = ?, updated_at = NOW()
                         WHERE id = ? AND user_id = ?');
    $st->execute([
        $fields['date'], $fields['time'], $fields['title'], $fields['body'],
        $fields['mood'], $fields['weather'], $fields['tags'], $id, $userId,
    ]);

    dy_drop_calendar_copy($pdo, $userId, $id, $existing['calendar_event_id'] ?? null);

    $pdo->commit();

    $row = dy_find($pdo, $userId, $id);
    dy_json(['success' => true, 'entry' => $row ? dy_entry_out($row, true) : ['id' => $id]]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Diary update error: ' . $e->getMessage());
    dy_fail('server_error', 500);
}
