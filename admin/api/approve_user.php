<?php
declare(strict_types=1);
/**
 * POST /admin/api/approve_user.php   (X-CSRF-Token)
 * Approve one pending member. Who may approve whom is decided by
 * admin_approval_scope() (includes/admin_api.php), the same rule the
 * Approvals tab uses to list them.
 * Fields: user_id   Answer: {success, message} or {success:false, error}
 */

require_once __DIR__ . '/../../security/auth_gate.php';
require_once __DIR__ . '/../../includes/admin_api.php';
require_once __DIR__ . '/notifications/helper.php';

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

    // The member has to be pending AND inside this approver's scope
    [$where, $params] = admin_approval_where($scope, 'u');
    $params[':target'] = $targetUserId;
    $stmt = $pdo->prepare("UPDATE users u SET u.status = 'approved' WHERE u.id = :target AND {$where}");
    $stmt->execute($params);

    if ($stmt->rowCount() === 0) {
        // Not pending any more, or not theirs to approve
        admin_api_fail('adm_err_done', 409);
    }

    createAdminNotification($targetUserId, 'account_approved', []);

    admin_api_json(['success' => true, 'message' => __t('adm_user_approved', admin_api_lang())]);
} catch (Throwable $e) {
    admin_api_server_error($e, 'approve_user');
}
