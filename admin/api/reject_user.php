<?php
declare(strict_types=1);
/**
 * POST /admin/api/reject_user.php   (X-CSRF-Token)
 * Reject one pending member, under the same rule as approve_user.php.
 * Fields: user_id   Answer: {success, message} or {success:false, error}
 */

require_once __DIR__ . '/../../security/auth_gate.php';
require_once __DIR__ . '/../../includes/admin_api.php';

admin_api_require_post();
$userId = admin_api_user_id();

$targetUserId = (int)($_POST['user_id'] ?? 0);
if ($targetUserId <= 0) {
    admin_api_fail('adm_err_invalid');
}

try {
    $stmt = $pdo->prepare('SELECT amp_id, town_id, congregation_id FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $approver = $stmt->fetch(PDO::FETCH_ASSOC);

    $scope = $approver ? admin_approval_scope($approver) : null;
    if ($scope === null) {
        admin_api_fail('adm_err_forbidden', 403);
    }

    [$where, $params] = admin_approval_where($scope, 'u');
    $params[':target'] = $targetUserId;
    $stmt = $pdo->prepare("UPDATE users u SET u.status = 'rejected' WHERE u.id = :target AND {$where}");
    $stmt->execute($params);

    if ($stmt->rowCount() === 0) {
        admin_api_fail('adm_err_done', 409);
    }

    admin_api_json(['success' => true, 'message' => __t('adm_user_rejected', admin_api_lang())]);
} catch (Throwable $e) {
    admin_api_server_error($e, 'reject_user');
}
