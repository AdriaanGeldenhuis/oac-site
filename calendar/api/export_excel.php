<?php
declare(strict_types=1);
/**
 * GET|POST /calendar/api/export_excel.php?user_id=N&from=YYYY-MM-DD&to=YYYY-MM-DD
 * The same content as export_pdf.php as a CSV file that Excel opens
 * (UTF-8 with BOM). Cells that Excel would run as a formula are made safe.
 */

require_once __DIR__ . '/../../security/auth_gate.php';
require_once __DIR__ . '/../lib/ics.php';

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
    error_log('Calendar CSV export error: ' . $e->getMessage());
    http_response_code(500);
    exit;
}

/** =, +, -, @ (and tab/CR) at the start make Excel run the cell as a formula */
function cal_csv_cell($value): string {
    $s = (string)$value;
    if ($s !== '' && strpbrk($s[0], "=+-@\t\r") !== false) {
        $s = "'" . $s;
    }
    return $s;
}

$fullName = cal_name($target['name'] ?? '', $target['surname'] ?? '');
$typeLabel = ['visit' => __t('cal_visit', $lang), 'appointment' => __t('appointment', $lang)];
$statusLabel = ['pending' => __t('cal_status_pending', $lang), 'confirmed' => __t('cal_status_confirmed', $lang)];

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . cal_file_name('kalender-' . $fullName . '-' . $from, 'csv') . '"');
header('Cache-Control: private, no-store');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, [
    __t('date', $lang), __t('time', $lang), __t('type', $lang), __t('title', $lang),
    __t('location', $lang), __t('with_whom', $lang), 'Status',
], ',', '"', '');
foreach ($items as $it) {
    fputcsv($out, array_map('cal_csv_cell', [
        $it['date'],
        $it['time'] ?? __t('cal_all_day', $lang),
        $typeLabel[$it['type']] ?? $it['type'],
        $it['title'],
        $it['location'] ?? '',
        $it['person_name'] ?? '',
        $statusLabel[$it['status'] ?? ''] ?? '',
    ]), ',', '"', '');
}
fclose($out);
