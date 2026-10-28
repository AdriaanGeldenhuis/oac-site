<?php
declare(strict_types=1);
/**
 * POST /admin/api/gedagte/improve.php — check a thought's spelling, grammar
 * and punctuation before it is saved (elders, amp 1-5). Fields: content, lang.
 * Answers {success, improved, changed}; nothing is stored — the elder
 * chooses in the form whether to use the correction.
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
@set_time_limit(120);

$userId = dt_admin_guard($pdo);

// POST only, with the session's CSRF token
admin_api_require_post();

$lang = admin_api_lang();
$content = dt_plain(dt_post('content'));
$thoughtLang = dt_lang(dt_post('lang'), 'af');

if ($content === '') {
    admin_api_fail('adm_err_invalid');
}
if (mb_strlen($content, 'UTF-8') > DT_MAX_CHARS) {
    admin_api_json(['success' => false, 'error' => str_replace('{n}', (string)DT_MAX_CHARS, __t('gd_too_long', $lang))], 400);
}

try {
    if (!dt_ai_ready()) {
        admin_api_json(['success' => false, 'error' => __t('gd_ai_off', $lang)], 503);
    }
    if (ai_limit_error($userId, $content) !== null) {
        admin_api_fail('adm_err_rate', 429);
    }

    $improved = dt_ai_proofread($content, $thoughtLang);
    if ($improved === null) {
        admin_api_json(['success' => false, 'error' => __t('gd_check_failed', $lang)], 502);
    }
    admin_api_json(['success' => true, 'improved' => $improved, 'changed' => $improved !== $content]);
} catch (Throwable $e) {
    admin_api_server_error($e, 'gedagte/improve');
}
