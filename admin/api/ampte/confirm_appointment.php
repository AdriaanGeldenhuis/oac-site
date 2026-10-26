<?php
declare(strict_types=1);
/**
 * POST /admin/api/ampte/confirm_appointment.php   (X-CSRF-Token)
 * The person who was asked confirms a pending appointment. The calendar
 * reads ampte_appointments directly, so the status is all that changes.
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
    if (!$appointment) {
        admin_api_fail('adm_err_not_found', 404);
    }
    if ((int)$appointment['target_user_id'] !== $userId) {
        admin_api_fail('adm_err_forbidden', 403);
    }

    // Conditional: a second click (or the calendar in another tab) changes nothing
    $up = $pdo->prepare("UPDATE ampte_appointments SET status = 'confirmed' WHERE id = ? AND status = 'pending'");
    $up->execute([$appointmentId]);
    if ($up->rowCount() === 0) {
        admin_api_fail('adm_err_done', 409);
    }

    createAdminNotification((int)$appointment['requester_id'], 'appointment_confirmed', [
        'date' => (string)$appointment['appointment_date'],
        'time' => substr((string)$appointment['appointment_time'], 0, 5),
    ]);

    admin_api_json(['success' => true]);
} catch (Throwable $e) {
    admin_api_server_error($e, 'ampte/confirm_appointment');
}
