<?php
declare(strict_types=1);
/**
 * POST /admin/api/ampte/cancel_appointment.php   (X-CSRF-Token)
 * Either person cancels a pending or confirmed appointment; the other is told.
 * The calendar reads ampte_appointments directly, so the status is all that
 * changes (nothing in anyone's calendar is deleted).
 * Fields: appointment_id   Answer: {success} or {success:false, error}
 */

require_once __DIR__ . '/../../../security/auth_gate.php';
require_once __DIR__ . '/../../../includes/admin_api.php';
require_once __DIR__ . '/../notifications/helper.php';

admin_api_require_post();
$userId = admin_api_user_id();
$appointmentId = (int)($_POST['appointment_id'] ?? 0);
if ($appointmentId <= 0) {
    admin_api_fail('adm_err_invalid');
}

try {
    $stmt = $pdo->prepare('SELECT requester_id, target_user_id, appointment_date, appointment_time FROM ampte_appointments WHERE id = ? LIMIT 1');
    $stmt->execute([$appointmentId]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

    $requester = (int)($appointment['requester_id'] ?? 0);
    $target = (int)($appointment['target_user_id'] ?? 0);
    if (!$appointment || ($userId !== $requester && $userId !== $target)) {
        admin_api_fail('adm_err_not_found', 404);
    }

    $up = $pdo->prepare("UPDATE ampte_appointments SET status = 'cancelled' WHERE id = ? AND status IN ('pending', 'confirmed')");
    $up->execute([$appointmentId]);
    if ($up->rowCount() === 0) {
        admin_api_fail('adm_err_done', 409);
    }

    $stmt = $pdo->prepare('SELECT name, surname FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $me = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    createAdminNotification($userId === $requester ? $target : $requester, 'appointment_cancelled', [
        'from_name' => admin_full_name($me),
        'date' => (string)$appointment['appointment_date'],
        'time' => substr((string)$appointment['appointment_time'], 0, 5),
    ]);

    admin_api_json(['success' => true]);
} catch (Throwable $e) {
    admin_api_server_error($e, 'ampte/cancel_appointment');
}
