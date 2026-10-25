<?php
declare(strict_types=1);
/**
 * GET /diary/api/get.php?id=N
 * One of my entries, with its whole text.
 */

require_once dirname(__DIR__, 2) . '/security/auth_gate.php';
require_once dirname(__DIR__) . '/lib/diary.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    dy_fail('db_unavailable', 500);
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    dy_fail('bad_request');
}

try {
    $row = dy_find($pdo, dy_user_id(), $id);
    if (!$row) {
        dy_fail('not_found', 404);
    }
    dy_json(['success' => true, 'entry' => dy_entry_out($row, true)]);
} catch (Throwable $e) {
    error_log('Diary get error: ' . $e->getMessage());
    dy_fail('server_error', 500);
}
