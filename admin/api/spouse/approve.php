<?php
declare(strict_types=1);
/**
 * POST /admin/api/spouse/approve.php   (X-CSRF-Token)
 * Link two members as spouses. The person who was asked may accept, and an
 * office bearer (amp 1–6) may approve a request inside their scope (their
 * congregation, else their town). The requester can never approve their own.
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

    $stmt = $pdo->prepare("SELECT id, requester_id, receiver_id FROM spouse_requests WHERE id = ? AND status = 'pending' LIMIT 1 FOR UPDATE");
    $stmt->execute([$requestId]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$request) {
        $pdo->rollBack();
        admin_api_fail('adm_err_done', 409);
    }

    $requesterId = (int)$request['requester_id'];
    $receiverId = (int)$request['receiver_id'];
    if ($userId === $requesterId) {
        $pdo->rollBack();
        admin_api_fail('adm_err_forbidden', 403);
    }

    // Lock both members; they must still be free
    $people = $pdo->prepare('SELECT id, name, surname, amp_id, town_id, congregation_id, spouse_user_id FROM users WHERE id IN (?, ?, ?) FOR UPDATE');
    $people->execute([$requesterId, $receiverId, $userId]);
    $byId = [];
    foreach ($people->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $byId[(int)$row['id']] = $row;
    }
    if (!isset($byId[$requesterId], $byId[$receiverId], $byId[$userId])) {
        $pdo->rollBack();
        admin_api_fail('adm_err_not_found', 404);
    }

    if ($userId !== $receiverId && !admin_spouse_in_scope($byId[$userId], $byId[$requesterId], $byId[$receiverId])) {
        $pdo->rollBack();
        admin_api_fail('adm_err_forbidden', 403);
    }

    if (!empty($byId[$requesterId]['spouse_user_id']) || !empty($byId[$receiverId]['spouse_user_id'])) {
        $pdo->rollBack();
        admin_api_fail('adm_err_already_married', 409);
    }

    $pdo->prepare("UPDATE spouse_requests SET status = 'accepted', updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$requestId]);

    $link = $pdo->prepare("UPDATE users SET spouse_user_id = ?, marital_status = 'getroud' WHERE id = ? AND spouse_user_id IS NULL");
    $link->execute([$receiverId, $requesterId]);
    $link->execute([$requesterId, $receiverId]);

    $pdo->commit();

    // Tell both of them, except whoever just did it
    if ($requesterId !== $userId) {
        createAdminNotification($requesterId, 'spouse_accepted', [
            'from_id' => $receiverId,
            'from_name' => admin_full_name($byId[$receiverId]),
        ]);
    }
    if ($receiverId !== $userId) {
        createAdminNotification($receiverId, 'spouse_accepted', [
            'from_id' => $requesterId,
            'from_name' => admin_full_name($byId[$requesterId]),
        ]);
    }

    admin_api_json(['success' => true]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    admin_api_server_error($e, 'spouse/approve');
}
