<?php
declare(strict_types=1);
/**
 * GET|POST /calendar/api/export_pdf.php?user_id=N&from=YYYY-MM-DD&to=YYYY-MM-DD
 * A real PDF of a calendar for the period (default: this month), for the
 * people cal_view_rights() lets export it. Same content as view.php shows:
 * visits and appointments, never diary entries or notes.
 */

require_once __DIR__ . '/../../security/auth_gate.php';
require_once __DIR__ . '/../lib/ics.php';
require_once __DIR__ . '/../lib/pdf.php';

$src = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
$lang = cal_lang();

$me = cal_user($pdo, (int)$_SESSION['user_id']);
$target = cal_user($pdo, (int)($src['user_id'] ?? 0));
if (!$me || !$target || !cal_view_rights($me, $target)['export']) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Forbidden';
    exit;
}

[$from, $to] = cal_range($src);
if ($from === null) {
    $first = cal_now()->modify('first day of this month');
    $from = $first->format('Y-m-d');
    $to = $first->modify('last day of this month')->format('Y-m-d');
}

try {
    $items = cal_items_for_viewer($pdo, $target, (int)$me['id'], $from, $to, $lang);
} catch (Throwable $e) {
    error_log('Calendar PDF export error: ' . $e->getMessage());
    http_response_code(500);
    exit;
}

$fullName = cal_name($target['name'] ?? '', $target['surname'] ?? '');
$dayKeys = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];
$monthKeys = ['january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december'];
$typeLabel = [
    'visit' => __t('cal_visit', $lang),
    'appointment' => __t('appointment', $lang),
];

$fmtDate = function (string $date, bool $withDay) use ($lang, $dayKeys, $monthKeys): string {
    $d = new DateTimeImmutable($date);
    $month = mb_substr(__t($monthKeys[(int)$d->format('n') - 1], $lang), 0, 3);
    $out = (int)$d->format('j') . ' ' . $month . ' ' . $d->format('Y');
    return $withDay ? __t($dayKeys[(int)$d->format('w')], $lang) . ' ' . $out : $out;
};

// ===== DRAW =====
$pdf = new CalPdf();
$m = CalPdf::MARGIN;
$width = CalPdf::W - 2 * $m;
$cols = [
    ['key' => 'date', 'label' => __t('date', $lang), 'w' => 88],
    ['key' => 'time', 'label' => __t('time', $lang), 'w' => 52],
    ['key' => 'type', 'label' => __t('type', $lang), 'w' => 64],
    ['key' => 'title', 'label' => __t('title', $lang), 'w' => 168],
    ['key' => 'where', 'label' => __t('location', $lang), 'w' => $width - 372],
];
$ink = [0.12, 0.14, 0.18];
$muted = [0.42, 0.45, 0.51];
$sapphire = [0.17, 0.38, 0.84];
$pageNo = 0;
$y = 0.0;

$header = function () use ($pdf, $m, $width, $cols, $sapphire, $fullName, $lang, $from, $to, $fmtDate, &$pageNo, &$y): void {
    $pdf->addPage();
    $pageNo++;
    $pdf->rect(0, 0, CalPdf::W, 78, $sapphire);
    $pdf->text($m, 38, __t('calendar', $lang) . ': ' . $fullName, 18, true, [1, 1, 1]);
    $pdf->text($m, 58, $fmtDate($from, false) . ' – ' . $fmtDate($to, false), 10, false, [0.88, 0.92, 1]);
    $pdf->text(CalPdf::W - $m - 60, 58, __t('cal_page', $lang) . ' ' . $pageNo, 9, false, [0.88, 0.92, 1]);
    $y = 104;
    $x = $m;
    foreach ($cols as $c) {
        $pdf->text($x, $y, $c['label'], 8.5, true, [0.29, 0.33, 0.4]);
        $x += $c['w'];
    }
    $pdf->line($m, $y + 6, $m + $width, $y + 6, [0.17, 0.38, 0.84], 1);
    $y += 20;
};

$header();

if (!$items) {
    $pdf->text($m, $y + 10, __t('cal_nothing_period', $lang), 11, false, $muted);
}

$shade = false;
foreach ($items as $it) {
    $where = [];
    if (!empty($it['location'])) $where[] = (string)$it['location'];
    if ($it['type'] === 'visit' && !empty($it['person_name']) && $it['person_name'] !== $it['title']) {
        $where[] = (string)$it['person_name'];
    }
    $title = (string)$it['title'];
    if ($it['type'] === 'appointment' && ($it['status'] ?? '') === 'pending') {
        $title .= ' (' . __t('cal_status_pending', $lang) . ')';
    }
    $cells = [
        'date' => [$fmtDate($it['date'], true)],
        'time' => $pdf->wrap($it['time'] ?? __t('cal_all_day', $lang), 48, 9, false, 2),
        'type' => $pdf->wrap($typeLabel[$it['type']] ?? $it['type'], 60, 9, false, 2),
        'title' => $pdf->wrap($title, 164, 9.5, true, 4),
        'where' => $pdf->wrap(implode(' · ', $where), $cols[4]['w'] - 4, 9, false, 4),
    ];
    $cells['date'] = $pdf->wrap($cells['date'][0], 84, 9, false, 2);
    $lines = max(array_map('count', $cells));
    $rowH = 8 + $lines * 12;

    if ($y + $rowH > CalPdf::H - $m) {
        $header();
        $shade = false;
    }
    if ($shade) {
        $pdf->rect($m - 4, $y - 11, $width + 8, $rowH, [0.95, 0.96, 0.99]);
    }
    $shade = !$shade;

    $x = $m;
    foreach ($cols as $c) {
        $ly = $y;
        foreach ($cells[$c['key']] as $line) {
            $bold = $c['key'] === 'title';
            $pdf->text($x, $ly, $line, $bold ? 9.5 : 9, $bold, $c['key'] === 'time' || $c['key'] === 'type' ? $muted : $ink);
            $ly += 12;
        }
        $x += $c['w'];
    }
    $y += $rowH;
}

$pdf->text($m, CalPdf::H - 22, __t('cal_generated', $lang) . ' ' . cal_now()->format('Y-m-d H:i'), 8, false, $muted);

$body = $pdf->output(__t('calendar', $lang) . ' - ' . $fullName);
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . cal_file_name('kalender-' . $fullName . '-' . $from, 'pdf') . '"');
header('Content-Length: ' . strlen($body));
header('Cache-Control: private, no-store');
echo $body;
