<?php
declare(strict_types=1);
/**
 * POST /admin/api/spouse/cancel.php   (X-CSRF-Token)
 * Withdraw a spouse request you sent, while it is still pending.
 * Fields: request_id   Answer: {success} or {success:false, error}
 */

require_once __DIR__ . '/../../../security/auth_gate.php';
require_once __DIR__ . '/../../../includes/admin_api.php';

admin_api_require_post();
$userId = admin_api_user_id();
$requestId = (int)($_POST['request_id'] ?? 0);
if ($requestId <= 0) {
    admin_api_fail('adm_err_invalid');
}

try {
    // Only the requester can withdraw; one statement, so it cannot race an accept
    $del = $pdo->prepare("DELETE FROM spouse_requests WHERE id = ? AND requester_id = ? AND status = 'pending'");
    $del->execute([$requestId, $userId]);
    if ($del->rowCount() === 0) {
        admin_api_fail('adm_err_done', 409);
    }

    admin_api_json(['success' => true]);
} catch (Throwable $e) {
    admin_api_server_error($e, 'spouse/cancel');
}
