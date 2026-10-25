<?php
declare(strict_types=1);
/**
 * GET /calendar/api/rooms.php
 * The rooms where the user may post an event: Gospel Media's own rules
 * (lib/permissions.php). Gemeenskap rooms are left out, as in the feed's
 * composer, which offers no event there.
 * Answer: {success, rooms: [{id, name, type}]}
 */

require_once __DIR__ . '/../../security/auth_gate.php';
require_once __DIR__ . '/../lib/calendar.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    cal_fail('db_unavailable', 500);
}

$userId = (int)$_SESSION['user_id'];
$lang = cal_lang();
$typeOrder = ['gemeente' => 0, 'opsienerskap' => 1, 'jeug' => 2, 'sondagskool' => 3];

try {
    $rooms = [];
    foreach (cal_rooms($pdo, $userId) as $id => $info) {
        $type = strtolower((string)($info['room']['type'] ?? ''));
        if (!$info['post'] || $type === 'gemeenskap') continue;
        $rooms[] = [
            'id' => (int)$id,
            'name' => cal_room_label($type, (string)($info['room']['name'] ?? ''), $lang),
            'type' => $type,
        ];
    }
    usort($rooms, fn($a, $b) => [$typeOrder[$a['type']] ?? 9, $a['name']] <=> [$typeOrder[$b['type']] ?? 9, $b['name']]);
    cal_json(['success' => true, 'rooms' => $rooms]);
} catch (Throwable $e) {
    error_log('Calendar rooms error: ' . $e->getMessage());
    cal_fail('server_error', 500);
}
