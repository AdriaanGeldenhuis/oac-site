<?php
declare(strict_types=1);
/**
 * GET /calendar/api/spouse_status.php
 * Is the user linked to a spouse (both accounts pointing at each other),
 * and is a spouse request waiting for them? Linking itself happens in
 * /admin/account.php.
 * Answer: {has_spouse, spouse_id, spouse_name, pending_requests}
 */

require_once __DIR__ . '/../../security/auth_gate.php';
require_once __DIR__ . '/../lib/calendar.php';

$answer = ['has_spouse' => false, 'spouse_id' => null, 'spouse_name' => '', 'pending_requests' => 0];

try {
    $me = cal_user($pdo, (int)$_SESSION['user_id']);
    if ($me) {
        $spouseId = cal_spouse_id($pdo, $me);
        if ($spouseId > 0) {
            $spouse = cal_user($pdo, $spouseId);
            $answer['has_spouse'] = true;
            $answer['spouse_id'] = $spouseId;
            $answer['spouse_name'] = $spouse ? cal_name($spouse['name'] ?? '', $spouse['surname'] ?? '') : '';
        } else {
            $st = $pdo->prepare("SELECT COUNT(*) FROM spouse_requests WHERE receiver_id = ? AND status = 'pending'");
            $st->execute([(int)$me['id']]);
            $answer['pending_requests'] = (int)$st->fetchColumn();
        }
    }
} catch (Throwable $e) {
    error_log('Spouse status error: ' . $e->getMessage());
}

cal_json($answer);
