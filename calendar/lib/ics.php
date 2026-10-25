<?php
declare(strict_types=1);
/**
 * iCalendar (.ics) output — one format for the whole site.
 * Used by calendar/api/ics.php (the calendar and Gospel Media's
 * "Add to calendar") and by profile/api/export_calendar.php.
 *
 * Times are stored as naive SAST; here they become real UTC instants, so
 * the phone shows the right hour wherever it is set. Items without a time
 * are all-day events. Events from a Gospel Media room keep the UID Gospel
 * Media always used, so adding the same event twice updates it.
 */

require_once __DIR__ . '/calendar.php';

const CAL_ICS_HOST = 'oacapp.co.za';

function cal_ics_escape(string $s): string {
    $s = str_replace(["\r\n", "\r"], "\n", $s);
    return str_replace(['\\', ';', ',', "\n"], ['\\\\', '\\;', '\\,', '\\n'], $s);
}

/** Fold to 75 octets per line without splitting a UTF-8 character */
function cal_ics_fold(string $line): string {
    if (strlen($line) <= 75) return $line;
    $out = '';
    $cur = '';
    $limit = 75;
    $chars = preg_split('//u', $line, -1, PREG_SPLIT_NO_EMPTY) ?: str_split($line);
    foreach ($chars as $ch) {
        if (strlen($cur) + strlen($ch) > $limit) {
            $out .= $cur . "\r\n ";
            $cur = '';
            $limit = 74; // continuation lines start with a space
        }
        $cur .= $ch;
    }
    return $out . $cur;
}

function cal_ics_uid(array $item): string {
    $id = (int)$item['id'];
    switch ($item['type']) {
        case 'event':       return 'gm-post-' . $id . '@' . CAL_ICS_HOST;
        case 'visit':       return 'cal-visit-' . $id . '@' . CAL_ICS_HOST;
        case 'appointment': return 'cal-appt-' . $id . '@' . CAL_ICS_HOST;
        case 'diary':       return 'cal-diary-' . $id . '@' . CAL_ICS_HOST;
        default:            return 'cal-' . preg_replace('/[^a-z0-9-]/i', '', (string)($item['uid'] ?? $id)) . '@' . CAL_ICS_HOST;
    }
}

/** VEVENT lines for one calendar item (cal_item() shape) */
function cal_ics_vevent(array $item, int $minutes = 60): array {
    $utc = new DateTimeZone('UTC');
    $lines = [
        'BEGIN:VEVENT',
        'UID:' . cal_ics_uid($item),
        'DTSTAMP:' . gmdate('Ymd\THis\Z'),
    ];

    if (!empty($item['time'])) {
        $start = new DateTimeImmutable($item['date'] . ' ' . $item['time'] . ':00', cal_tz());
        $end = $start->modify('+' . max(15, $minutes) . ' minutes');
        $lines[] = 'DTSTART:' . $start->setTimezone($utc)->format('Ymd\THis\Z');
        $lines[] = 'DTEND:' . $end->setTimezone($utc)->format('Ymd\THis\Z');
    } else {
        $day = new DateTimeImmutable($item['date']);
        $lines[] = 'DTSTART;VALUE=DATE:' . $day->format('Ymd');
        $lines[] = 'DTEND;VALUE=DATE:' . $day->modify('+1 day')->format('Ymd');
    }

    $lines[] = 'SUMMARY:' . cal_ics_escape(cal_clean_line((string)($item['title'] ?? '')));
    if (!empty($item['location'])) {
        $lines[] = 'LOCATION:' . cal_ics_escape(cal_clean_line((string)$item['location']));
    }
    $desc = trim((string)($item['notes'] ?? ''));
    if (!empty($item['room_name'])) {
        $desc = trim($item['room_name'] . ($desc !== '' ? "\n\n" . $desc : ''));
    }
    if ($desc !== '') {
        $lines[] = 'DESCRIPTION:' . cal_ics_escape($desc);
    }
    $lines[] = 'URL:https://' . CAL_ICS_HOST . cal_link((string)$item['date'], (string)($item['uid'] ?? ''));
    $lines[] = 'END:VEVENT';
    return $lines;
}

function cal_ics_document(array $items, string $calName = ''): string {
    $lines = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//OAC//Kalender//AF',
        'CALSCALE:GREGORIAN',
        'METHOD:PUBLISH',
    ];
    if ($calName !== '') {
        $lines[] = 'X-WR-CALNAME:' . cal_ics_escape($calName);
    }
    foreach ($items as $item) {
        foreach (cal_ics_vevent($item) as $l) $lines[] = $l;
    }
    $lines[] = 'END:VCALENDAR';
    return implode("\r\n", array_map('cal_ics_fold', $lines)) . "\r\n";
}

/** Safe ASCII file name: "kalender-2026-10-30.ics" */
function cal_file_name(string $base, string $ext): string {
    $base = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $base) ?: $base) ?? '');
    $base = trim($base, '-');
    return ($base !== '' ? substr($base, 0, 60) : 'kalender') . '.' . $ext;
}

function cal_send_ics(array $items, string $fileBase, string $calName = ''): void {
    $body = cal_ics_document($items, $calName);
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . cal_file_name($fileBase, 'ics') . '"');
    header('Cache-Control: private, no-store');
    header('Content-Length: ' . strlen($body));
    echo $body;
    exit;
}
