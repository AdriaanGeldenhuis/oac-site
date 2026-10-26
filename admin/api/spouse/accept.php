<?php
declare(strict_types=1);
/**
 * POST /admin/api/spouse/accept.php   (X-CSRF-Token)
 * The member who was asked accepts a spouse request addressed to them.
 * Fields: request_id   Answer: {success} or {success:false, error}
 */

require_once __DIR__ . '/../../../security/auth_gate.php';
require_once __DIR__ . '/../../../includes/admin_api.php';
require_once __DIR__ . '/../notifications/helper.php';

admin_api_require_post();
$userId = admin_api_user_id();
$requestId = (int)($_POST['request_id'] ?? 0);
if ($requestId <= 0) {
    admin_api_fail('adm_err_invalid');
}

try {
    $pdo->beginTransaction();

    // ONLY the receiver can accept
    $stmt = $pdo->prepare("SELECT id, requester_id FROM spouse_requests WHERE id = ? AND receiver_id = ? AND status = 'pending' LIMIT 1 FOR UPDATE");
    $stmt->execute([$requestId, $userId]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$request) {
        $pdo->rollBack();
        admin_api_fail('adm_err_done', 409);
    }
    $requesterId = (int)$request['requester_id'];

    $people = $pdo->prepare('SELECT id, name, surname, spouse_user_id FROM users WHERE id IN (?, ?) FOR UPDATE');
    $people->execute([$userId, $requesterId]);
    $byId = [];
    foreach ($people->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $byId[(int)$row['id']] = $row;
    }
    if (!isset($byId[$userId], $byId[$requesterId])) {
        $pdo->rollBack();
        admin_api_fail('adm_err_not_found', 404);
    }
    if (!empty($byId[$userId]['spouse_user_id']) || !empty($byId[$requesterId]['spouse_user_id'])) {
        $pdo->rollBack();
        admin_api_fail('adm_err_already_married', 409);
    }

    $pdo->prepare("UPDATE spouse_requests SET status = 'accepted', updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$requestId]);

    $link = $pdo->prepare("UPDATE users SET spouse_user_id = ?, marital_status = 'getroud' WHERE id = ? AND spouse_user_id IS NULL");
    $link->execute([$requesterId, $userId]);
    $link->execute([$userId, $requesterId]);

    $pdo->commit();

    createAdminNotification($requesterId, 'spouse_accepted', [
        'from_id' => $userId,
        'from_name' => admin_full_name($byId[$userId]),
    ]);

    admin_api_json(['success' => true]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    admin_api_server_error($e, 'spouse/accept');
}
