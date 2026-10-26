<?php
declare(strict_types=1);
/**
 * POST /admin/api/spouse/reject.php   (X-CSRF-Token)
 * Turn down a spouse request. The person who was asked may, and an office
 * bearer (amp 1–6) may inside their scope (their congregation, else their town).
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
    $stmt = $pdo->prepare("SELECT id, requester_id, receiver_id FROM spouse_requests WHERE id = ? AND status = 'pending' LIMIT 1");
    $stmt->execute([$requestId]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$request) {
        admin_api_fail('adm_err_done', 409);
    }

    $requesterId = (int)$request['requester_id'];
    $receiverId = (int)$request['receiver_id'];

    $people = $pdo->prepare('SELECT id, name, surname, amp_id, town_id, congregation_id FROM users WHERE id IN (?, ?, ?)');
    $people->execute([$requesterId, $receiverId, $userId]);
    $byId = [];
    foreach ($people->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $byId[(int)$row['id']] = $row;
    }
    if (!isset($byId[$requesterId], $byId[$receiverId], $byId[$userId])) {
        admin_api_fail('adm_err_not_found', 404);
    }

    if ($userId !== $receiverId && !admin_spouse_in_scope($byId[$userId], $byId[$requesterId], $byId[$receiverId])) {
        admin_api_fail('adm_err_forbidden', 403);
    }

    // Conditional, so two clicks (or two office bearers) answer it once
    $up = $pdo->prepare("UPDATE spouse_requests SET status = 'rejected', updated_at = CURRENT_TIMESTAMP WHERE id = ? AND status = 'pending'");
    $up->execute([$requestId]);
    if ($up->rowCount() === 0) {
        admin_api_fail('adm_err_done', 409);
    }

    createAdminNotification($requesterId, 'spouse_rejected', [
        'from_id' => $userId,
        'from_name' => admin_full_name($byId[$userId]),
    ]);

    admin_api_json(['success' => true]);
} catch (Throwable $e) {
    admin_api_server_error($e, 'spouse/reject');
}
