<?php
declare(strict_types=1);
/**
 * GET /diary/api/export.php?format=pdf|txt&id=N
 * GET /diary/api/export.php?format=pdf|txt&q=&period=&tag=&sort=
 * A download of one of my entries, or of the entries the list shows with
 * the same filters. The PDF is a real PDF (calendar/lib/pdf.php), so
 * phones, the app's download manager and printers all open it.
 */

require_once dirname(__DIR__, 2) . '/security/auth_gate.php';
require_once dirname(__DIR__) . '/lib/diary.php';
require_once dirname(__DIR__, 2) . '/calendar/lib/pdf.php';

function dy_export_fail(int $status, string $message): void {
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: private, no-store');
    echo $message;
    exit;
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    dy_export_fail(500, 'Database unavailable');
}

$userId = dy_user_id();
$lang = dy_lang();
$format = dy_str($_GET['format'] ?? 'pdf');
if (!in_array($format, ['pdf', 'txt'], true)) {
    dy_export_fail(400, 'Invalid format');
}
$entryId = (int)($_GET['id'] ?? 0);

try {
    if ($entryId > 0) {
        $row = dy_find($pdo, $userId, $entryId);
        $rows = $row ? [$row] : [];
    } else {
        [$where, $params] = dy_where($userId, $_GET);
        $st = $pdo->prepare('SELECT ' . DY_COLUMNS . ' FROM diaries WHERE ' . $where . ' ORDER BY ' . dy_order($_GET));
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    }

    $who = $pdo->prepare('SELECT name, surname FROM users WHERE id = ? LIMIT 1');
    $who->execute([$userId]);
    $user = $who->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    error_log('Diary export error: ' . $e->getMessage());
    dy_export_fail(500, 'Could not fetch entries');
}

if (!$rows) {
    dy_export_fail(404, dy_t('dy_err_not_found', $lang));
}

$entries = array_map(fn($r) => dy_entry_out($r, true), $rows);
$owner = trim(trim((string)($user['name'] ?? '')) . ' ' . trim((string)($user['surname'] ?? '')));
$heading = __t('my_diary', $lang);
$today = dy_now()->format('Y-m-d');

/** "Sondag 25 Oktober 2026 · 14:30" */
$whenLine = function (array $e) use ($lang): string {
    return dy_long_date($e['date'], $lang) . ($e['time'] !== null ? ' · ' . $e['time'] : '');
};

/** "Gemoed: Dankbaar · Weer: Sonnig" */
$metaLine = function (array $e) use ($lang): string {
    $parts = [];
    if ($e['mood'] !== null) $parts[] = __t('mood', $lang) . ': ' . __t('dy_mood_' . $e['mood'], $lang);
    if ($e['weather'] !== null) $parts[] = __t('weather', $lang) . ': ' . __t('dy_weather_' . $e['weather'], $lang);
    return implode(' · ', $parts);
};

$tagLine = fn(array $e): string => $e['tags'] ? '#' . implode('  #', $e['tags']) : '';

// File name: "dagboek-2026-10-25.pdf" in the page language, ASCII only
$slug = strtolower((string)(@iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', __t('diary', $lang)) ?: 'diary'));
$slug = trim(preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '', '-') ?: 'diary';
$fileName = $slug . '-' . ($entryId > 0 ? $entries[0]['date'] : $today) . '.' . $format;

header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');

// ===== TEXT =====
if ($format === 'txt') {
    $out = $heading . "\n";
    if ($owner !== '') $out .= $owner . "\n";
    $out .= dy_long_date($today, $lang) . "\n\n";
    foreach ($entries as $e) {
        $out .= str_repeat('=', 48) . "\n" . $whenLine($e) . "\n";
        if ($e['title'] !== '') $out .= $e['title'] . "\n";
        if (($meta = $metaLine($e)) !== '') $out .= $meta . "\n";
        if (($tags = $tagLine($e)) !== '') $out .= $tags . "\n";
        $out .= str_repeat('-', 48) . "\n" . $e['body'] . "\n\n";
    }
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Content-Length: ' . strlen($out));
    echo $out;
    exit;
}

// ===== PDF =====
$ink = [0.11, 0.12, 0.16];
$muted = [0.42, 0.44, 0.49];
$amber = [0.69, 0.46, 0.08];
$rule = [0.86, 0.83, 0.77];
$m = CalPdf::MARGIN;
$width = CalPdf::W - 2 * $m;
$bottom = CalPdf::H - $m - 18;

$pdf = new CalPdf();
$page = 0;
$y = 0.0;

$newPage = function () use ($pdf, &$page, &$y, $m, $muted, $amber, $heading): void {
    $pdf->addPage();
    $page++;
    $pdf->rect(0, 0, CalPdf::W, 5, $amber);
    $label = $heading . '  ·  ' . $page;
    $pdf->text(CalPdf::W - $m - $pdf->width($label, 8), CalPdf::H - $m + 8, $label, 8, false, $muted);
    $y = $m + 6;
};

/** Room for $h points, or a new page */
$need = function (float $h) use (&$y, $bottom, $newPage): void {
    if ($y + $h > $bottom) $newPage();
};

$newPage();
$pdf->text($m, $y + 22, $heading, 24, true, $ink);
$y += 40;
$sub = trim($owner . ($owner !== '' ? '  ·  ' : '') . dy_long_date($today, $lang));
$pdf->text($m, $y, $sub, 9.5, false, $muted);
$y += 14;
$pdf->line($m, $y, CalPdf::W - $m, $y, $rule, 0.8);
$y += 26;

foreach ($entries as $i => $e) {
    $titleLines = $e['title'] !== '' ? $pdf->wrap($e['title'], $width, 14, true, 3) : [];
    $meta = trim($metaLine($e) . (($metaLine($e) !== '' && $tagLine($e) !== '') ? '   ' : '') . $tagLine($e));
    $metaLines = $meta !== '' ? $pdf->wrap($meta, $width, 9, false, 3) : [];

    // Keep the heading of an entry together with its first lines of text
    $need(16 + count($titleLines) * 18 + count($metaLines) * 12 + 40);

    $pdf->text($m, $y, $whenLine($e), 9.5, true, $amber);
    $y += 18;
    foreach ($titleLines as $line) {
        $pdf->text($m, $y, $line, 14, true, $ink);
        $y += 18;
    }
    foreach ($metaLines as $line) {
        $pdf->text($m, $y, $line, 9, false, $muted);
        $y += 12;
    }
    $y += 6;

    // Line by line, so the writer's line breaks and blank lines stay
    foreach ($e['body'] !== '' ? explode("\n", $e['body']) : [] as $para) {
        if (trim($para) === '') {
            $y += 8;
            continue;
        }
        foreach ($pdf->wrap($para, $width, 10.5, false, 100000) as $line) {
            $need(15.5);
            $pdf->text($m, $y, $line, 10.5, false, $ink);
            $y += 15.5;
        }
    }

    if ($i < count($entries) - 1) {
        $y += 8;
        $need(24);
        $pdf->line($m, $y, $m + 60, $y, $amber, 0.8);
        $y += 26;
    }
}

$bytes = $pdf->output($heading . ($owner !== '' ? ' — ' . $owner : ''));
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Content-Length: ' . strlen($bytes));
echo $bytes;
