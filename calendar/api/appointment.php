<?php
declare(strict_types=1);
/**
 * POST /calendar/api/appointment.php  (X-CSRF-Token)
 * Answer an Ampte appointment from your own calendar, so everyone can act
 * on one (the Afsprake tab is only open to amp 1–9).
 *  - action=confirm: only the person who was asked, while it is pending
 *  - action=cancel:  either person, while it is pending or confirmed
 * The other person is told, in their own language.
 * Fields: action, id   Answer: {success, status} or {success:false, error}
 */

require_once __DIR__ . '/../../security/auth_gate.php';
require_once __DIR__ . '/../lib/calendar.php';
require_once __DIR__ . '/notifications/helper.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    cal_fail('db_unavailable', 500);
}
cal_require_post();

$me = cal_user($pdo, (int)$_SESSION['user_id']);
if (!$me) {
    cal_fail('forbidden', 403);
}
$userId = (int)$me['id'];
$action = (string)($_POST['action'] ?? '');
$id = (int)($_POST['id'] ?? 0);

if (!in_array($action, ['confirm', 'cancel'], true) || $id <= 0) {
    cal_fail('invalid_id');
}
if (!cal_rate_ok('cal_appt:' . $userId, 30)) {
    cal_fail('rate_limited', 429);
}

try {
    $st = $pdo->prepare('SELECT id, requester_id, target_user_id, appointment_date, appointment_time, status
                         FROM ampte_appointments WHERE id = ? LIMIT 1');
    $st->execute([$id]);
    $appt = $st->fetch(PDO::FETCH_ASSOC);

    $requester = (int)($appt['requester_id'] ?? 0);
    $target = (int)($appt['target_user_id'] ?? 0);
    if (!$appt || ($userId !== $requester && $userId !== $target)) {
        cal_fail('not_found', 404);
    }

    $pdo->beginTransaction();
    if ($action === 'confirm') {
        if ($userId !== $target) {
            $pdo->rollBack();
            cal_fail('forbidden', 403);
        }
        // Conditional update: a second click (or the other tab) changes nothing
        $up = $pdo->prepare("UPDATE ampte_appointments SET status = 'confirmed' WHERE id = ? AND status = 'pending'");
        $up->execute([$id]);
        $newStatus = 'confirmed';
    } else {
        $up = $pdo->prepare("UPDATE ampte_appointments SET status = 'cancelled' WHERE id = ? AND status IN ('pending', 'confirmed')");
        $up->execute([$id]);
        $newStatus = 'cancelled';
    }
    if ($up->rowCount() === 0) {
        $pdo->rollBack();
        cal_fail('already_done', 409);
    }
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Calendar appointment error: ' . $e->getMessage());
    cal_fail('server_error', 500);
}

cal_finish_response(['success' => true, 'status' => $newStatus]);

try {
    $date = (string)$appt['appointment_date'];
    createCalendarNotification($userId === $requester ? $target : $requester,
        $action === 'confirm' ? 'appointment_confirmed' : 'appointment_cancelled', [
            'from_name' => cal_name($me['name'] ?? '', $me['surname'] ?? ''),
            'date' => $date,
            'time' => (string)(cal_stored_time($appt['appointment_time'] ?? null) ?? ''),
            'uid' => $action === 'confirm' ? 'appointment-' . $id : '',
        ]);
} catch (Throwable $e) {
    error_log('Calendar appointment notification error: ' . $e->getMessage());
}
