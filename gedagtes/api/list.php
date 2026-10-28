<?php
declare(strict_types=1);
/**
 * GET /gedagtes/api/list.php?before=YYYY-MM-DD&q=
 * Earlier thoughts (before today, South African time), newest first,
 * GD_PAGE at a time. ?before= continues the list from the last date shown,
 * ?q= finds every word in the text or the author. The first page also
 * carries the total. Every thought comes in the reader's language when it
 * has been translated. Today's thought is drawn by gedagtes.php itself.
 */

require_once dirname(__DIR__, 2) . '/security/auth_gate.php';
require_once dirname(__DIR__) . '/lib/gedagtes.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    gd_json(['success' => false, 'error' => 'db_unavailable'], 500);
}

try {
    gd_json(['success' => true] + gd_archive($pdo, $_GET, gd_lang()));
} catch (Throwable $e) {
    error_log('Gedagtes list error: ' . $e->getMessage());
    gd_json(['success' => false, 'error' => 'server_error'], 500);
}
