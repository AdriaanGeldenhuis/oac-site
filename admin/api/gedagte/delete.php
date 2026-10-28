<?php
declare(strict_types=1);
/** POST /admin/api/gedagte/delete.php — delete a thought and its translations (elders, amp 1-5). Field: id */

require_once dirname(__DIR__, 3) . '/security/config.php';
require_once dirname(__DIR__, 3) . '/security/session.php';
require_once dirname(__DIR__, 3) . '/security/auth.php';
require_once dirname(__DIR__, 3) . '/security/csrf.php';
require_once dirname(__DIR__, 3) . '/includes/admin_api.php';
require_once dirname(__DIR__, 3) . '/includes/daily_thoughts.php';

ob_start();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

dt_admin_guard($pdo);

// POST only, with the session's CSRF token
admin_api_require_post();

$id = (int)dt_post('id');
if ($id < 1) {
    admin_api_fail('adm_err_invalid');
}

try {
    dt_ensure_schema($pdo); // before the transaction: DDL commits

    $pdo->beginTransaction();
    $pdo->prepare('DELETE FROM daily_thought_translations WHERE thought_id = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM daily_thoughts WHERE id = ?')->execute([$id]);
    $pdo->commit();

    admin_api_json(['success' => true]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    admin_api_server_error($e, 'gedagte/delete');
}
