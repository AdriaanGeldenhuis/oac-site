<?php
declare(strict_types=1);
/**
 * POST /admin/api/spouse/request.php   (X-CSRF-Token)
 * Ask another member to be your spouse. One pending request at a time,
 * in any direction, and only between two unmarried members.
 * Fields: receiver_id   Answer: {success} or {success:false, error}
 */

require_once __DIR__ . '/../../../security/auth_gate.php';
require_once __DIR__ . '/../../../includes/admin_api.php';
require_once __DIR__ . '/../notifications/helper.php';

admin_api_require_post();
$userId = admin_api_user_id();
$receiverId = (int)($_POST['receiver_id'] ?? 0);
if ($receiverId <= 0 || $receiverId === $userId) {
    admin_api_fail('adm_err_invalid');
}

try {
    $pdo->beginTransaction();

    $people = $pdo->prepare("SELECT id, name, surname, spouse_user_id, status FROM users WHERE id IN (?, ?) FOR UPDATE");
    $people->execute([$userId, $receiverId]);
    $byId = [];
    foreach ($people->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $byId[(int)$row['id']] = $row;
    }
    if (!isset($byId[$userId], $byId[$receiverId]) || ($byId[$receiverId]['status'] ?? '') !== 'approved') {
        $pdo->rollBack();
        admin_api_fail('adm_err_not_found', 404);
    }
    if (!empty($byId[$userId]['spouse_user_id']) || !empty($byId[$receiverId]['spouse_user_id'])) {
        $pdo->rollBack();
        admin_api_fail('adm_err_already_married', 409);
    }

    $check = $pdo->prepare("SELECT id FROM spouse_requests WHERE status = 'pending'
        AND (requester_id IN (?, ?) OR receiver_id IN (?, ?)) LIMIT 1");
    $check->execute([$userId, $receiverId, $userId, $receiverId]);
    if ($check->fetch()) {
        $pdo->rollBack();
        admin_api_fail('adm_err_request_exists', 409);
    }

    $pdo->prepare("INSERT INTO spouse_requests (requester_id, receiver_id, status) VALUES (?, ?, 'pending')")
        ->execute([$userId, $receiverId]);

    $pdo->commit();

    createAdminNotification($receiverId, 'spouse_request', [
        'from_id' => $userId,
        'from_name' => admin_full_name($byId[$userId]),
    ]);

    admin_api_json(['success' => true]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    admin_api_server_error($e, 'spouse/request');
}
