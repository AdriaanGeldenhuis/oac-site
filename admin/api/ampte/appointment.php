<?php
declare(strict_types=1);
/**
 * POST /admin/api/ampte/appointment.php   (X-CSRF-Token)
 * Ask an office holder for an appointment (the Afsprake tab, amp 1–9).
 * The calendar reads ampte_appointments directly, so this writes that one
 * row and nothing else; the person asked is told in their own language.
 * Fields: target_user_id, appointment_date (Y-m-d), appointment_time (H:i),
 *         location, notes   Answer: {success, appointment_id} or {success:false, error}
 */

require_once __DIR__ . '/../../../security/auth_gate.php';
require_once __DIR__ . '/../../../security/rate_limit.php';
require_once __DIR__ . '/../../../includes/admin_api.php';
require_once __DIR__ . '/../notifications/helper.php';

admin_api_require_post();
$userId = admin_api_user_id();

// Appointments are made in SAST, whatever the server clock says
$tz = new DateTimeZone('Africa/Johannesburg');

$targetUserId = (int)($_POST['target_user_id'] ?? 0);
$date = trim((string)($_POST['appointment_date'] ?? ''));
$time = trim((string)($_POST['appointment_time'] ?? ''));
$location = mb_substr(trim((string)($_POST['location'] ?? '')), 0, 255);
$notes = mb_substr(trim((string)($_POST['notes'] ?? '')), 0, 2000);

if ($targetUserId <= 0 || $targetUserId === $userId
    || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}$/', $time)) {
    admin_api_fail('adm_err_invalid');
}

$when = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date . ' ' . $time, $tz);
$errors = DateTimeImmutable::getLastErrors();
if (!$when || ($errors && ($errors['warning_count'] || $errors['error_count'])) || $when <= new DateTimeImmutable('now', $tz)) {
    admin_api_fail('adm_err_past');
}

try {
    // The Afsprake tab is for amp 1–9
    $stmt = $pdo->prepare('SELECT name, surname, amp_id FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $me = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$me || (int)$me['amp_id'] < 1 || (int)$me['amp_id'] > 9) {
        admin_api_fail('adm_err_forbidden', 403);
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND status = 'approved' LIMIT 1");
    $stmt->execute([$targetUserId]);
    if (!$stmt->fetch()) {
        admin_api_fail('adm_err_not_found', 404);
    }

    // A flood of requests helps nobody
    if (!rate_limit_check('appt:' . $userId, 20, 15)) {
        admin_api_fail('adm_err_rate', 429);
    }

    // The same request twice (double tap, second tab) is one request
    $stmt = $pdo->prepare("SELECT id FROM ampte_appointments WHERE requester_id = ? AND target_user_id = ?
        AND appointment_date = ? AND appointment_time = ? AND status IN ('pending', 'confirmed') LIMIT 1");
    $stmt->execute([$userId, $targetUserId, $date, $time . ':00']);
    if ($stmt->fetch()) {
        admin_api_fail('adm_err_request_exists', 409);
    }

    $stmt = $pdo->prepare("INSERT INTO ampte_appointments
        (requester_id, target_user_id, appointment_date, appointment_time, location, notes, status)
        VALUES (?, ?, ?, ?, ?, ?, 'pending')");
    $stmt->execute([$userId, $targetUserId, $date, $time . ':00', $location, $notes]);
    $appointmentId = (int)$pdo->lastInsertId();

    createAdminNotification($targetUserId, 'appointment_request', [
        'from_name' => admin_full_name($me),
        'date' => $date,
        'time' => $time,
    ]);

    admin_api_json(['success' => true, 'appointment_id' => $appointmentId]);
} catch (Throwable $e) {
    admin_api_server_error($e, 'ampte/appointment');
}
