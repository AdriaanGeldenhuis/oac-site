<?php
declare(strict_types=1);
/**
 * POST /admin/api/spouse/unlink.php   (X-CSRF-Token)
 * An office bearer (amp 1–6) removes a spouse link inside their scope
 * (their congregation, else their town). marital_status stays as it is so
 * the members can set it themselves (e.g. weduwee / wewenaar).
 * Fields: user_id (either of the two)   Answer: {success} or {success:false, error}
 */

require_once __DIR__ . '/../../../security/auth_gate.php';
require_once __DIR__ . '/../../../includes/admin_api.php';
require_once __DIR__ . '/../notifications/helper.php';

admin_api_require_post();
$userId = admin_api_user_id();
$targetId = (int)($_POST['user_id'] ?? 0);
if ($targetId <= 0) {
    admin_api_fail('adm_err_invalid');
}

try {
    $stmt = $pdo->prepare('SELECT id, amp_id, town_id, congregation_id FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $me = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$me || (int)$me['amp_id'] < 1 || (int)$me['amp_id'] > 6) {
        admin_api_fail('adm_err_forbidden', 403);
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT id, name, surname, town_id, congregation_id, spouse_user_id FROM users WHERE id = ? LIMIT 1 FOR UPDATE');
    $stmt->execute([$targetId]);
    $target = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$target || empty($target['spouse_user_id'])) {
        $pdo->rollBack();
        admin_api_fail('adm_err_done', 409);
    }

    $spouseId = (int)$target['spouse_user_id'];
    $stmt->execute([$spouseId]);
    $spouse = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$spouse) {
        $pdo->rollBack();
        admin_api_fail('adm_err_not_found', 404);
    }

    if (!admin_spouse_in_scope($me, $target, $spouse)) {
        $pdo->rollBack();
        admin_api_fail('adm_err_forbidden', 403);
    }

    $pdo->prepare('UPDATE users SET spouse_user_id = NULL WHERE id IN (?, ?)')->execute([$targetId, $spouseId]);
    $pdo->commit();

    createAdminNotification($targetId, 'spouse_unlinked', ['from_name' => admin_full_name($spouse)]);
    createAdminNotification($spouseId, 'spouse_unlinked', ['from_name' => admin_full_name($target)]);

    admin_api_json(['success' => true]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    admin_api_server_error($e, 'spouse/unlink');
}
