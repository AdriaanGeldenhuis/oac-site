<?php
declare(strict_types=1);
/**
 * Calendar — shared server code for the pages and the APIs.
 *
 * Rules live in one place each:
 *  - Room events (posts.type = 'event'): who may see, post and moderate is
 *    decided by gospel_media/lib/permissions.php, exactly like the feed.
 *  - Looking at someone else's calendar: cal_view_rights() only.
 *  - Diary entries are only ever returned to their owner.
 *
 * Dates and times are naive local strings (Africa/Johannesburg), as stored
 * by MySQL (which runs on SAST). "Now" is always taken from PHP with an
 * explicit time zone, never from the server default.
 */

require_once dirname(__DIR__, 2) . '/includes/languages.php';
require_once dirname(__DIR__, 2) . '/gospel_media/lib/permissions.php';

const CAL_TZ = 'Africa/Johannesburg';
const CAL_MAX_SPAN_DAYS = 400;

// ===== BASICS =====

function cal_tz(): DateTimeZone {
    static $tz = null;
    if ($tz === null) $tz = new DateTimeZone(CAL_TZ);
    return $tz;
}

function cal_now(): DateTimeImmutable {
    return new DateTimeImmutable('now', cal_tz());
}

function cal_lang(): string {
    $lang = (string)($_SESSION['language'] ?? 'af');
    return in_array($lang, SUPPORTED_LANGS, true) ? $lang : 'af';
}

function cal_valid_lang(?string $lang): string {
    return in_array((string)$lang, SUPPORTED_LANGS, true) ? (string)$lang : 'af';
}

/** Translate, then fill {placeholders} */
function cal_t(string $key, ?string $lang = null, array $params = []): string {
    $text = __t($key, $lang ?? cal_lang());
    foreach ($params as $name => $value) {
        $text = str_replace('{' . $name . '}', (string)$value, $text);
    }
    return $text;
}

function cal_len(string $s): int {
    return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s);
}

function cal_cut(string $s, int $max): string {
    if (cal_len($s) <= $max) return $s;
    return rtrim(function_exists('mb_substr') ? mb_substr($s, 0, $max - 1, 'UTF-8') : substr($s, 0, $max - 1)) . '…';
}

/** Single-line, trimmed, control characters removed */
function cal_clean_line(string $s): string {
    $s = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $s) ?? '';
    return trim(preg_replace('/\s{2,}/u', ' ', $s) ?? '');
}

/** Multi-line text: keep newlines, drop other control characters */
function cal_clean_text(string $s): string {
    $s = str_replace(["\r\n", "\r"], "\n", $s);
    $s = preg_replace('/[\x00-\x09\x0B-\x1F\x7F]+/u', '', $s) ?? '';
    return trim($s);
}

// ===== RESPONSES =====

function cal_json($data, int $status = 200): void {
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function cal_fail(string $code, int $status = 400): void {
    cal_json(['success' => false, 'error' => $code], $status);
}

/** Every write: POST + the session CSRF token in X-CSRF-Token (as Gospel Media) */
function cal_require_post(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        cal_fail('method_not_allowed', 405);
    }
    $token = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? ''));
    if (!csrf_verify($token)) {
        cal_fail('csrf', 403);
    }
}

/** Send the JSON answer now and keep running (notifications) without making the user wait */
function cal_finish_response(array $data): void {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } else {
        if (ob_get_level() > 0) @ob_end_flush();
        flush();
    }
}

/** Rate limit (security/rate_limit.php). Fails open, like the login limiter. */
function cal_rate_ok(string $key, int $max): bool {
    require_once dirname(__DIR__, 2) . '/security/rate_limit.php';
    return rate_limit_check($key, $max, 60);
}

// ===== VALIDATION =====

/** Strict YYYY-MM-DD that is a real date */
function cal_date(?string $s): ?string {
    $s = trim((string)$s);
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m)) return null;
    $y = (int)$m[1];
    if ($y < 1900 || $y > 2200 || !checkdate((int)$m[2], (int)$m[3], $y)) return null;
    return $s;
}

/** HH:MM (24h); returns null when empty or invalid */
function cal_time(?string $s): ?string {
    $s = trim((string)$s);
    if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)(:[0-5]\d)?$/', $s, $m)) return null;
    return $m[1] . ':' . $m[2];
}

/** A stored time: '00:00', '', NULL and invalid values mean "all day" */
function cal_stored_time($t): ?string {
    $t = cal_time((string)$t);
    return ($t === null || $t === '00:00') ? null : $t;
}

/**
 * The window asked for (?from=&to=). Both optional; "to" defaults to a
 * year after "from". Returns [null, null] when no window was asked for.
 */
function cal_range(array $src): array {
    $from = cal_date($src['from'] ?? null);
    $to = cal_date($src['to'] ?? null);
    if ($from === null && $to === null) return [null, null];

    if ($from === null) {
        $from = (new DateTimeImmutable($to))->modify('-1 year')->format('Y-m-d');
    }
    if ($to === null) {
        $to = (new DateTimeImmutable($from))->modify('+1 year')->format('Y-m-d');
    }
    if ($to < $from) {
        [$from, $to] = [$to, $from];
    }
    $span = (new DateTimeImmutable($from))->diff(new DateTimeImmutable($to))->days;
    if ($span > CAL_MAX_SPAN_DAYS) {
        $to = (new DateTimeImmutable($from))->modify('+' . CAL_MAX_SPAN_DAYS . ' days')->format('Y-m-d');
    }
    return [$from, $to];
}

// ===== PEOPLE =====

function cal_user(PDO $pdo, int $id): ?array {
    if ($id <= 0) return null;
    $st = $pdo->prepare('SELECT id, name, surname, amp_id, town_id, congregation_id, spouse_user_id,
                                gender, photo, language, birthdate, status
                         FROM users WHERE id = ? LIMIT 1');
    $st->execute([$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/** amp_id 1–10; anything else counts as 10 (Broer/Suster, the least rights) */
function cal_amp(array $u): int {
    $amp = (int)($u['amp_id'] ?? 10);
    return ($amp >= 1 && $amp <= 10) ? $amp : 10;
}

function cal_name(?string $name, ?string $surname): string {
    return trim(trim((string)$name) . ' ' . trim((string)$surname));
}

/** The spouse id, only when both accounts point at each other */
function cal_spouse_id(PDO $pdo, array $me): int {
    $sid = (int)($me['spouse_user_id'] ?? 0);
    if ($sid <= 0 || $sid === (int)$me['id']) return 0;
    $st = $pdo->prepare('SELECT spouse_user_id FROM users WHERE id = ? LIMIT 1');
    $st->execute([$sid]);
    return ((int)$st->fetchColumn() === (int)$me['id']) ? $sid : 0;
}

/**
 * May $me look at $target's calendar, export it, ask for an appointment?
 * The view rule is the one calendar/view.php always used (unchanged):
 *  - your own calendar;
 *  - a higher office over a lower one: amp 6+ in the same congregation,
 *    amp 2–5 in the same town, amp 1 everywhere;
 *  - amp 1–5 for anyone in their own town.
 * Exporting needs amp 1–9; asking for an appointment needs amp 1–9 (the
 * Afsprake tab) and someone else.
 */
function cal_view_rights(array $me, array $target): array {
    $meId = (int)$me['id'];
    $targetId = (int)$target['id'];
    $myAmp = cal_amp($me);
    $targetAmp = cal_amp($target);
    $myTown = (int)($me['town_id'] ?? 0);
    $myCong = (int)($me['congregation_id'] ?? 0);
    $targetTown = (int)($target['town_id'] ?? 0);
    $targetCong = (int)($target['congregation_id'] ?? 0);

    $view = false;
    if ($meId === $targetId) {
        $view = true;
    } elseif ($myAmp < $targetAmp) {
        if ($myAmp >= 6) {
            $view = $myCong > 0 && $myCong === $targetCong;
        } elseif ($myAmp >= 2) {
            $view = $myTown > 0 && $myTown === $targetTown;
        } else {
            $view = true;
        }
    } elseif ($myAmp <= 5 && $myTown > 0 && $myTown === $targetTown) {
        $view = true;
    }

    $officer = $myAmp >= 1 && $myAmp <= 9;
    return [
        'view' => $view,
        'export' => $view && $officer,
        'appoint' => $view && $officer && $meId !== $targetId,
    ];
}

// ===== ROOMS =====

/** Room name as the feed shows it: "Gemeente Pretoria", translated */
function cal_room_label(string $type, string $name, string $lang): string {
    $t = strtolower($type);
    $prefixes = ['gemeente' => 'Gemeente ', 'opsienerskap' => 'Opsienerskap ', 'jeug' => 'Jeug ', 'sondagskool' => 'Sondagskool '];
    if (isset($prefixes[$t]) && stripos($name, $prefixes[$t]) === 0) {
        $name = substr($name, strlen($prefixes[$t]));
    }
    $name = trim($name);
    $keys = ['gemeente' => 'congregation', 'opsienerskap' => 'oversight', 'jeug' => 'youth', 'sondagskool' => 'sunday_school'];
    if (isset($keys[$t])) {
        return trim(__t($keys[$t], $lang) . ' ' . $name);
    }
    return $name !== '' ? $name : __t('room', $lang);
}

/**
 * Every room the user may open in Gospel Media, with what they may do there:
 * [room_id => ['room' => row, 'post' => bool, 'moderate' => bool]].
 * Candidates are the rooms any access path can reach (gemeenskap, own town,
 * own congregation, memberships); the Gospel rules then decide.
 */
function cal_rooms(PDO $pdo, int $userId): array {
    static $cache = [];
    if (isset($cache[$userId])) return $cache[$userId];

    $me = cal_user($pdo, $userId);
    if (!$me) return $cache[$userId] = [];
    $town = (int)($me['town_id'] ?? 0);
    $cong = (int)($me['congregation_id'] ?? 0);

    $st = $pdo->prepare("SELECT * FROM rooms
                         WHERE type = 'gemeenskap'
                            OR (town_id = ? AND town_id > 0)
                            OR (gemeente_id = ? AND gemeente_id > 0)
                            OR id IN (SELECT room_id FROM room_memberships WHERE user_id = ?)");
    $st->execute([$town, $cong, $userId]);

    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $room) {
        if (!user_has_access_to_room($pdo, $userId, $room)) continue;
        $out[(int)$room['id']] = [
            'room' => $room,
            'post' => user_can_post_in_room($pdo, $userId, $room),
            // Room-level moderation: the Gospel rule for a post by someone else
            'moderate' => user_can_edit_post($pdo, $userId, ['user_id' => 0, 'room_id' => (int)$room['id']], $room),
        ];
    }
    return $cache[$userId] = $out;
}

/** One room (or null) with the same rights as cal_rooms() */
function cal_room_rights(PDO $pdo, int $userId, int $roomId): ?array {
    $rooms = cal_rooms($pdo, $userId);
    return $rooms[$roomId] ?? null;
}

// ===== ITEMS =====

/** Posts keep the event in one text: the first line is the title */
function cal_split_post_text(string $text): array {
    $text = cal_clean_text($text);
    $parts = preg_split('/\n/', $text, 2) ?: [''];
    $first = cal_clean_line($parts[0] ?? '');
    $rest = trim($parts[1] ?? '');
    if (cal_len($first) > 140) {
        return [cal_cut($first, 140), $text];
    }
    return [$first, $rest];
}

/** Base shape shared by every item (welcome.js reads type/date/time/title/location/person_name) */
function cal_item(string $type, int $id, string $date, ?string $time): array {
    return [
        'uid' => $type . '-' . $id,
        'id' => $id,
        'type' => $type,
        'title' => '',
        'date' => $date,
        'time' => $time,
        'all_day' => $time === null,
        'location' => null,
        'notes' => null,
        'user_id' => 0,
        'is_shared' => 0,
        'can_edit' => false,
        'can_delete' => false,
    ];
}

function cal_event_items(PDO $pdo, int $userId, array $roomIds, ?string $from, ?string $to, string $lang, array $postIdsOnly = []): array {
    if (!$roomIds && !$postIdsOnly) return [];
    $rooms = cal_rooms($pdo, $userId);

    $where = ["p.type = 'event'", 'p.event_at IS NOT NULL', "p.event_at > '1900-01-01'"];
    $params = [];
    if ($postIdsOnly) {
        $where[] = 'p.id IN (' . implode(',', array_fill(0, count($postIdsOnly), '?')) . ')';
        $params = array_merge($params, array_values($postIdsOnly));
    } else {
        $where[] = 'p.room_id IN (' . implode(',', array_fill(0, count($roomIds), '?')) . ')';
        $params = array_merge($params, array_values($roomIds));
    }
    if ($from !== null) {
        $where[] = 'p.event_at >= ?';
        $params[] = $from . ' 00:00:00';
    }
    if ($to !== null) {
        $where[] = 'p.event_at <= ?';
        $params[] = $to . ' 23:59:59';
    }

    $st = $pdo->prepare('SELECT p.id, p.room_id, p.user_id, p.text, p.event_at, p.event_place,
                                r.type AS room_type, r.name AS room_name, r.town_id AS room_town_id, r.gemeente_id AS room_gemeente_id,
                                u.name AS author_name, u.surname AS author_surname
                         FROM posts p
                         LEFT JOIN rooms r ON r.id = p.room_id
                         LEFT JOIN users u ON u.id = p.user_id
                         WHERE ' . implode(' AND ', $where) . '
                         ORDER BY p.event_at ASC');
    $st->execute($params);

    $now = cal_now()->format('Y-m-d H:i');
    $items = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $date = substr((string)$r['event_at'], 0, 10);
        if (cal_date($date) === null) continue;
        $time = cal_stored_time(substr((string)$r['event_at'], 11, 5));
        [$title, $notes] = cal_split_post_text((string)$r['text']);

        $item = cal_item('event', (int)$r['id'], $date, $time);
        $roomId = (int)$r['room_id'];
        $rights = $rooms[$roomId] ?? null;
        $own = (int)$r['user_id'] === $userId;
        $canEdit = $rights !== null && ($own || $rights['moderate']);
        $when = $date . ' ' . ($time ?? '23:59');

        $item['title'] = $title !== '' ? $title : __t('event', $lang);
        $item['notes'] = $notes !== '' ? $notes : null;
        if ($canEdit) {
            $item['text'] = (string)$r['text']; // as stored, so editing keeps it exactly
        }
        $item['location'] = ($r['event_place'] ?? '') !== '' ? (string)$r['event_place'] : null;
        $item['user_id'] = (int)$r['user_id'];
        $item['author_name'] = cal_name($r['author_name'] ?? '', $r['author_surname'] ?? '');
        $item['room_id'] = $roomId;
        $item['room_type'] = strtolower((string)($r['room_type'] ?? ''));
        $item['room_name'] = cal_room_label((string)($r['room_type'] ?? ''), (string)($r['room_name'] ?? ''), $lang);
        $item['room_town_id'] = (int)($r['room_town_id'] ?? 0);
        $item['room_gemeente_id'] = (int)($r['room_gemeente_id'] ?? 0);
        $item['can_edit'] = $canEdit;
        $item['can_delete'] = $canEdit;
        $item['can_remind'] = $rights !== null && $rights['post'] && $when >= $now;
        $item['shared'] = false;
        $items[] = $item;
    }
    return $items;
}

function cal_visit_row_item(array $r, bool $withNotes): array {
    $item = cal_item('visit', (int)$r['id'], (string)$r['visit_date'], cal_stored_time($r['visit_time'] ?? null));
    $linkedName = cal_name($r['linked_name'] ?? '', $r['linked_surname'] ?? '');
    $person = trim((string)($r['person_name'] ?? ''));
    $item['title'] = cal_clean_line((string)($r['title'] ?? ''));
    $item['location'] = ($r['location'] ?? '') !== '' ? (string)$r['location'] : null;
    $item['notes'] = ($withNotes && ($r['notes'] ?? '') !== '') ? (string)$r['notes'] : null;
    $item['user_id'] = (int)$r['user_id'];
    $item['linked_user_id'] = !empty($r['linked_user_id']) ? (int)$r['linked_user_id'] : null;
    $item['person_name'] = $linkedName !== '' ? $linkedName : ($person !== '' ? $person : null);
    if ($item['title'] === '') {
        $item['title'] = (string)($item['person_name'] ?? '');
    }
    return $item;
}

function cal_visit_items(PDO $pdo, int $ownerId, ?string $from, ?string $to, bool $withNotes): array {
    $where = ['v.user_id = ?', "v.visit_date > '1900-01-01'"];
    $params = [$ownerId];
    if ($from !== null) { $where[] = 'v.visit_date >= ?'; $params[] = $from; }
    if ($to !== null) { $where[] = 'v.visit_date <= ?'; $params[] = $to; }

    $st = $pdo->prepare('SELECT v.id, v.user_id, v.title, v.visit_date, v.visit_time, v.linked_user_id,
                                v.person_name, v.location, v.notes,
                                lu.name AS linked_name, lu.surname AS linked_surname
                         FROM calendar_visits v
                         LEFT JOIN users lu ON lu.id = v.linked_user_id
                         WHERE ' . implode(' AND ', $where) . '
                         ORDER BY v.visit_date ASC, v.visit_time ASC');
    $st->execute($params);

    $items = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if (cal_date((string)$r['visit_date']) === null) continue;
        $items[] = cal_visit_row_item($r, $withNotes);
    }
    return $items;
}

function cal_diary_items(PDO $pdo, int $ownerId, ?string $from, ?string $to, string $lang): array {
    $where = ['user_id = ?', "date > '1900-01-01'"];
    $params = [$ownerId];
    if ($from !== null) { $where[] = 'date >= ?'; $params[] = $from; }
    if ($to !== null) { $where[] = 'date <= ?'; $params[] = $to; }

    $st = $pdo->prepare('SELECT id, user_id, title, date, time, body FROM diaries
                         WHERE ' . implode(' AND ', $where) . ' ORDER BY date ASC, time ASC');
    $st->execute($params);

    $items = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if (cal_date((string)$r['date']) === null) continue;
        $item = cal_item('diary', (int)$r['id'], (string)$r['date'], cal_stored_time($r['time'] ?? null));
        $body = cal_clean_text((string)($r['body'] ?? ''));
        $title = cal_clean_line((string)($r['title'] ?? ''));
        if ($title === '') {
            $title = $body !== '' ? cal_cut(cal_clean_line($body), 60) : __t('diary', $lang);
        }
        $item['title'] = $title;
        $item['notes'] = $body !== '' ? cal_cut($body, 400) : null;
        $item['user_id'] = (int)$r['user_id'];
        $items[] = $item;
    }
    return $items;
}

/**
 * Ampte appointments ($userId is the requester or the one asked), pending
 * or confirmed. The title names the other person, in $lang.
 */
function cal_appointment_items(PDO $pdo, int $userId, ?string $from, ?string $to, string $lang, bool $withNotes, int $viewerId): array {
    $where = ['(a.requester_id = ? OR a.target_user_id = ?)', "a.status IN ('pending', 'confirmed')", "a.appointment_date > '1900-01-01'"];
    $params = [$userId, $userId];
    if ($from !== null) { $where[] = 'a.appointment_date >= ?'; $params[] = $from; }
    if ($to !== null) { $where[] = 'a.appointment_date <= ?'; $params[] = $to; }

    $st = $pdo->prepare('SELECT a.id, a.requester_id, a.target_user_id, a.appointment_date, a.appointment_time,
                                a.location, a.notes, a.status,
                                r.name AS r_name, r.surname AS r_surname, t.name AS t_name, t.surname AS t_surname
                         FROM ampte_appointments a
                         LEFT JOIN users r ON r.id = a.requester_id
                         LEFT JOIN users t ON t.id = a.target_user_id
                         WHERE ' . implode(' AND ', $where) . '
                         ORDER BY a.appointment_date ASC, a.appointment_time ASC');
    $st->execute($params);

    $items = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if (cal_date((string)$r['appointment_date']) === null) continue;
        $isRequester = (int)$r['requester_id'] === $userId;
        $otherId = $isRequester ? (int)$r['target_user_id'] : (int)$r['requester_id'];
        $otherName = $isRequester ? cal_name($r['t_name'] ?? '', $r['t_surname'] ?? '') : cal_name($r['r_name'] ?? '', $r['r_surname'] ?? '');
        $status = (string)$r['status'];

        $item = cal_item('appointment', (int)$r['id'], (string)$r['appointment_date'], cal_stored_time($r['appointment_time'] ?? null));
        $item['title'] = cal_t('cal_appt_with', $lang, ['name' => $otherName !== '' ? $otherName : '?']);
        $item['location'] = ($r['location'] ?? '') !== '' ? (string)$r['location'] : null;
        $item['notes'] = ($withNotes && ($r['notes'] ?? '') !== '') ? (string)$r['notes'] : null;
        $item['user_id'] = (int)$r['requester_id'];
        $item['status'] = $status;
        $item['role'] = $isRequester ? 'requester' : 'target';
        $item['other_id'] = $otherId;
        $item['other_name'] = $otherName;
        $item['person_name'] = $otherName !== '' ? $otherName : null;
        // Only the people in the appointment act on it (from their own calendar)
        $party = $viewerId === $userId;
        $item['can_confirm'] = $party && !$isRequester && $status === 'pending';
        $item['can_cancel'] = $party;
        $items[] = $item;
    }
    return $items;
}

/**
 * Birthdays in the window: the congregation (the people the birthday cron
 * already tells about it), yourself and your spouse. Name only, no age.
 */
function cal_birthday_items(PDO $pdo, array $me, int $spouseId, string $from, string $to): array {
    $cong = (int)($me['congregation_id'] ?? 0);
    $st = $pdo->prepare("SELECT id, name, surname, birthdate FROM users
                         WHERE status = 'approved' AND birthdate IS NOT NULL
                           AND ((congregation_id = ? AND congregation_id > 0) OR id = ? OR id = ?)");
    $st->execute([$cong, (int)$me['id'], $spouseId > 0 ? $spouseId : -1]);

    $start = new DateTimeImmutable($from);
    $end = new DateTimeImmutable($to);
    $items = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $bd = substr((string)$r['birthdate'], 0, 10);
        if (cal_date($bd) === null) continue;
        $month = (int)substr($bd, 5, 2);
        $day = (int)substr($bd, 8, 2);
        for ($y = (int)$start->format('Y'); $y <= (int)$end->format('Y'); $y++) {
            // 29 February is celebrated on the 28th in other years
            $d = ($month === 2 && $day === 29 && !checkdate(2, 29, $y)) ? 28 : $day;
            $date = sprintf('%04d-%02d-%02d', $y, $month, $d);
            if ($date < $from || $date > $to) continue;
            $item = cal_item('birthday', (int)$r['id'], $date, null);
            $item['uid'] = 'birthday-' . (int)$r['id'] . '-' . $y;
            $item['title'] = cal_name($r['name'] ?? '', $r['surname'] ?? '');
            $item['user_id'] = (int)$r['id'];
            $item['is_self'] = (int)$r['id'] === (int)$me['id'];
            $items[] = $item;
        }
    }
    return $items;
}

function cal_sort_items(array &$items): void {
    $order = ['birthday' => 0, 'event' => 1, 'appointment' => 2, 'visit' => 3, 'diary' => 4];
    usort($items, function (array $a, array $b) use ($order): int {
        return [$a['date'], $a['time'] === null ? 0 : 1, (string)$a['time'], $order[$a['type']] ?? 9, $a['id']]
           <=> [$b['date'], $b['time'] === null ? 0 : 1, (string)$b['time'], $order[$b['type']] ?? 9, $b['id']];
    });
}

/**
 * Everything on the user's own calendar. Without a window this returns the
 * full history (the old contract, minus birthdays).
 */
function cal_items_for_owner(PDO $pdo, array $me, ?string $from, ?string $to, string $lang): array {
    $userId = (int)$me['id'];
    $spouseId = cal_spouse_id($pdo, $me);

    $items = cal_event_items($pdo, $userId, array_keys(cal_rooms($pdo, $userId)), $from, $to, $lang);
    $items = array_merge(
        $items,
        cal_visit_items($pdo, $userId, $from, $to, true),
        cal_diary_items($pdo, $userId, $from, $to, $lang),
        cal_appointment_items($pdo, $userId, $from, $to, $lang, true, $userId)
    );
    foreach ($items as &$it) {
        if ($it['type'] === 'visit') {
            $it['can_edit'] = true;
            $it['can_delete'] = true;
            $it['shared'] = false;
        }
    }
    unset($it);

    // What I share with my spouse (to tick the box when editing)
    if ($spouseId > 0) {
        $st = $pdo->prepare('SELECT event_type, event_id FROM calendar_shared_events
                             WHERE owner_user_id = ? AND shared_with_user_id = ?');
        $st->execute([$userId, $spouseId]);
        $mine = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $s) {
            $mine[$s['event_type'] . '-' . (int)$s['event_id']] = true;
        }
        foreach ($items as &$it) {
            if (isset($mine[$it['uid']]) && in_array($it['type'], ['event', 'visit'], true)) {
                $it['shared'] = true;
            }
        }
        unset($it);

        $items = array_merge($items, cal_shared_items($pdo, $userId, $spouseId, $from, $to, $lang, $items));
    }

    if ($from !== null && $to !== null) {
        $items = array_merge($items, cal_birthday_items($pdo, $me, $spouseId, $from, $to));
    }

    cal_sort_items($items);
    return $items;
}

/** Visits and room events my (current, mutual) spouse shared with me. Never diaries. */
function cal_shared_items(PDO $pdo, int $userId, int $spouseId, ?string $from, ?string $to, string $lang, array $already): array {
    $seen = [];
    foreach ($already as $it) $seen[$it['uid']] = true;

    $st = $pdo->prepare("SELECT event_type, event_id FROM calendar_shared_events
                         WHERE shared_with_user_id = ? AND owner_user_id = ? AND event_type IN ('visit', 'event')");
    $st->execute([$userId, $spouseId]);
    $visitIds = [];
    $postIds = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $s) {
        $key = $s['event_type'] . '-' . (int)$s['event_id'];
        if (isset($seen[$key])) continue;
        if ($s['event_type'] === 'visit') $visitIds[] = (int)$s['event_id'];
        else $postIds[] = (int)$s['event_id'];
    }

    $out = [];
    if ($visitIds) {
        $in = implode(',', array_fill(0, count($visitIds), '?'));
        $params = array_merge([$spouseId], $visitIds);
        $range = '';
        if ($from !== null) { $range .= ' AND v.visit_date >= ?'; $params[] = $from; }
        if ($to !== null) { $range .= ' AND v.visit_date <= ?'; $params[] = $to; }
        $q = $pdo->prepare("SELECT v.id, v.user_id, v.title, v.visit_date, v.visit_time, v.linked_user_id,
                                   v.person_name, v.location, v.notes, lu.name AS linked_name, lu.surname AS linked_surname
                            FROM calendar_visits v
                            LEFT JOIN users lu ON lu.id = v.linked_user_id
                            WHERE v.user_id = ? AND v.id IN ($in) AND v.visit_date > '1900-01-01'" . $range);
        $q->execute($params);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
            if (cal_date((string)$r['visit_date']) === null) continue;
            $item = cal_visit_row_item($r, true);
            $item['is_shared'] = 1;
            $out[] = $item;
        }
    }
    if ($postIds) {
        foreach (cal_event_items($pdo, $userId, [], $from, $to, $lang, $postIds) as $item) {
            $item['is_shared'] = 1;
            $item['can_edit'] = false;
            $item['can_delete'] = false;
            $item['can_remind'] = false;
            $out[] = $item;
        }
    }
    return $out;
}

/**
 * What someone allowed by cal_view_rights() sees of another calendar:
 * visits and appointments with title, time and place. No diary, no notes.
 */
function cal_items_for_viewer(PDO $pdo, array $target, int $viewerId, ?string $from, ?string $to, string $lang): array {
    $targetId = (int)$target['id'];
    $items = array_merge(
        cal_visit_items($pdo, $targetId, $from, $to, false),
        cal_appointment_items($pdo, $targetId, $from, $to, $lang, false, $viewerId)
    );
    foreach ($items as &$it) {
        $it['can_edit'] = false;
        $it['can_delete'] = false;
        $it['linked_user_id'] = null;
    }
    unset($it);
    cal_sort_items($items);
    return $items;
}

/** The link that opens the calendar on a day (and an item) */
function cal_link(string $date, string $uid = ''): string {
    $hash = 'd=' . rawurlencode($date);
    if ($uid !== '') $hash .= '&i=' . rawurlencode($uid);
    return '/calendar/calendar.php#' . $hash;
}
