<?php
declare(strict_types=1);
/**
 * GET /calendar/api/users.php
 * People a visit can be linked to: approved members of your own town
 * (upsert.php accepts exactly this list and nobody else).
 * Answer: {success, users: [{id, name, surname}]}
 */

require_once __DIR__ . '/../../security/auth_gate.php';
require_once __DIR__ . '/../lib/calendar.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    cal_fail('db_unavailable', 500);
}

$me = cal_user($pdo, (int)$_SESSION['user_id']);
$town = (int)($me['town_id'] ?? 0);
if (!$me || $town <= 0) {
    cal_json(['success' => true, 'users' => []]);
}

try {
    $st = $pdo->prepare("SELECT id, name, surname FROM users
                         WHERE status = 'approved' AND town_id = ? AND id != ?
                         ORDER BY name ASC, surname ASC
                         LIMIT 2000");
    $st->execute([$town, (int)$me['id']]);

    $users = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $users[] = ['id' => (int)$row['id'], 'name' => (string)$row['name'], 'surname' => (string)$row['surname']];
    }
    cal_json(['success' => true, 'users' => $users]);
} catch (Throwable $e) {
    error_log('Calendar users error: ' . $e->getMessage());
    cal_fail('server_error', 500);
}
