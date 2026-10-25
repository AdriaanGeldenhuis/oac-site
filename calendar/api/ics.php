<?php
declare(strict_types=1);
/**
 * GET /calendar/api/ics.php?type=event|visit|appointment|diary&id=N
 * One item as an .ics file for the phone's own calendar app. Used by the
 * calendar and by Gospel Media's "Add to calendar" (one format everywhere).
 * Only what the user may see in their own calendar can be downloaded.
 */

require_once __DIR__ . '/../../security/auth_gate.php';
require_once __DIR__ . '/../lib/ics.php';

function cal_ics_not_found(): void {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Not found';
    exit;
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    exit;
}

$me = cal_user($pdo, (int)$_SESSION['user_id']);
$type = (string)($_GET['type'] ?? '');
$id = (int)($_GET['id'] ?? 0);
if (!$me || $id <= 0 || !in_array($type, ['event', 'visit', 'appointment', 'diary'], true)) {
    cal_ics_not_found();
}
$userId = (int)$me['id'];
$lang = cal_lang();
$item = null;

try {
    $spouseId = cal_spouse_id($pdo, $me);
    $sharedWithMe = function (string $kind) use ($pdo, $id, $userId, $spouseId): bool {
        if ($spouseId <= 0) return false;
        $st = $pdo->prepare('SELECT 1 FROM calendar_shared_events WHERE event_type = ? AND event_id = ? AND owner_user_id = ? AND shared_with_user_id = ? LIMIT 1');
        $st->execute([$kind, $id, $spouseId, $userId]);
        return (bool)$st->fetchColumn();
    };

    if ($type === 'event') {
        $found = cal_event_items($pdo, $userId, [], null, null, $lang, [$id]);
        $candidate = $found[0] ?? null;
        if ($candidate && (cal_room_rights($pdo, $userId, (int)$candidate['room_id']) || $sharedWithMe('event'))) {
            $item = $candidate;
        }
    } elseif ($type === 'visit') {
        $st = $pdo->prepare('SELECT v.id, v.user_id, v.title, v.visit_date, v.visit_time, v.linked_user_id, v.person_name,
                                    v.location, v.notes, lu.name AS linked_name, lu.surname AS linked_surname
                             FROM calendar_visits v LEFT JOIN users lu ON lu.id = v.linked_user_id
                             WHERE v.id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row && cal_date((string)$row['visit_date']) !== null
            && ((int)$row['user_id'] === $userId || $sharedWithMe('visit'))) {
            $item = cal_visit_row_item($row, true);
        }
    } elseif ($type === 'appointment') {
        foreach (cal_appointment_items($pdo, $userId, null, null, $lang, true, $userId) as $candidate) {
            if ($candidate['id'] === $id) {
                $item = $candidate;
                break;
            }
        }
    } else {
        foreach (cal_diary_items($pdo, $userId, null, null, $lang) as $candidate) {
            if ($candidate['id'] === $id) {
                $item = $candidate;
                break;
            }
        }
    }
} catch (Throwable $e) {
    error_log('Calendar ics error: ' . $e->getMessage());
    http_response_code(500);
    exit;
}

if (!$item) {
    cal_ics_not_found();
}

cal_send_ics([$item], $item['title'] . '-' . $item['date']);
