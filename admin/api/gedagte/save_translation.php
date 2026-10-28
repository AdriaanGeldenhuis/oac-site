<?php
declare(strict_types=1);
/**
 * POST /admin/api/gedagte/save_translation.php — an elder corrects one
 * translation by hand (amp 1-5). Fields: id, lang, content, author.
 * The correction stays until the thought itself is changed.
 */

require_once dirname(__DIR__, 3) . '/security/config.php';
require_once dirname(__DIR__, 3) . '/security/session.php';
require_once dirname(__DIR__, 3) . '/security/auth.php';
require_once dirname(__DIR__, 3) . '/security/csrf.php';
require_once dirname(__DIR__, 3) . '/includes/admin_api.php';
require_once dirname(__DIR__, 3) . '/includes/daily_thoughts.php';

ob_start();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

dt_admin_guard($pdo);

// POST only, with the session's CSRF token
admin_api_require_post();

$lang = admin_api_lang();
$id = (int)dt_post('id');
$target = dt_post('lang');
$content = dt_plain(dt_post('content'));
$author = dt_line(dt_post('author'));

if ($id < 1 || !in_array($target, SUPPORTED_LANGS, true) || $content === '' || mb_strlen($author, 'UTF-8') > DT_MAX_AUTHOR) {
    admin_api_fail('adm_err_invalid');
}
// Room for a translation that runs longer than the original
if (mb_strlen($content, 'UTF-8') > DT_MAX_CHARS * 2) {
    admin_api_json(['success' => false, 'error' => str_replace('{n}', (string)(DT_MAX_CHARS * 2), __t('gd_too_long', $lang))], 400);
}

try {
    $thought = dt_load($pdo, $id);
    if (!$thought) {
        admin_api_fail('adm_err_not_found', 404);
    }
    if ($target === $thought['lang']) {
        admin_api_fail('adm_err_invalid'); // the original is changed in the form above
    }
    if (!dt_store_translation($pdo, $id, $target, $content, $author, $thought['hash'], true)) {
        admin_api_fail('adm_err_not_found', 409); // the thought changed meanwhile
    }
    admin_api_json(['success' => true]);
} catch (Throwable $e) {
    admin_api_server_error($e, 'gedagte/save_translation');
}
