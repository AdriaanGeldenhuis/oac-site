<?php
declare(strict_types=1);
/**
 * GET /admin/api/pending_count.php — how much waits for the signed-in
 * member, by admin_pending_counts() (includes/admin_api.php): members to
 * approve, spouse requests to approve, appointment requests made to them,
 * a spouse request made to them. The site header (header.js) shows the
 * total as a dot on the menu button and a count beside "Admin".
 * Read-only. Answer: {success, total, approvals, appointments, mine};
 * a visitor who is not signed in gets {success:false} (401), no redirect.
 */

require_once dirname(__DIR__, 2) . '/security/config.php';
require_once dirname(__DIR__, 2) . '/security/session.php';
require_once dirname(__DIR__, 2) . '/security/auth.php';
require_once dirname(__DIR__, 2) . '/includes/admin_api.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    admin_api_fail('adm_err_method', 405);
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    admin_api_json(['success' => false], 401);
}

// Only read from the session: let the page's other requests have it
session_write_close();

try {
    $stmt = $pdo->prepare('SELECT id, amp_id, town_id, congregation_id FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $me = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$me) {
        admin_api_json(['success' => false], 401);
    }

    $c = admin_pending_counts($pdo, $me);
    admin_api_json([
        'success' => true,
        'total' => $c['total'],
        'approvals' => $c['approvals'],
        'appointments' => $c['appointments'],
        'mine' => $c['mine'],
    ]);
} catch (Throwable $e) {
    admin_api_server_error($e, 'pending_count');
}
