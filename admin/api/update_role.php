<?php
/**
 * /api/admin/update_role.php
 * Fix: use amp_id (normalized via approvals_scope) instead of a non-existent `users.amp` column.
 * Returns JSON {success:true} or {error:'...'} with proper HTTP status codes.
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../session.php';
require_once __DIR__ . '/../../app/db_connect.php';
require_once __DIR__ . '/../../approvals_scope.php'; // for amp_norm()
// Load notification helper for role change notifications
// Optional notification helper (no-op fallback if missing)
$__notify = __DIR__ . '/../helpers/notify.php';
if (is_file($__notify)) { require_once $__notify; }
if (!function_exists('send_notification')) {
    function send_notification($pdo, $userId, $type, $payload = []) { /* no-op */ }
}

