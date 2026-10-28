<?php
declare(strict_types=1);
/**
 * POST /admin/api/gedagte/translate.php — translate a thought into every
 * language it is still missing (elders, amp 1-5). Field: id.
 * Answers {success, missing: [...]} — the languages that are still missing.
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
@set_time_limit(180);

$userId = dt_admin_guard($pdo);

// POST only, with the session's CSRF token
admin_api_require_post();

$lang = admin_api_lang();
$id = (int)dt_post('id');
if ($id < 1) {
    admin_api_fail('adm_err_invalid');
}

try {
    $thought = dt_load($pdo, $id);
    if (!$thought) {
        admin_api_fail('adm_err_not_found', 404);
    }
    if (!dt_missing_langs($thought)) {
        admin_api_json(['success' => true, 'missing' => []]);
    }
    if (!dt_ai_ready()) {
        admin_api_json(['success' => false, 'error' => __t('gd_ai_off', $lang)], 503);
    }
    if (ai_limit_error($userId, $thought['content']) !== null) {
        admin_api_fail('adm_err_rate', 429);
    }

    $result = dt_translate_missing($pdo, $id);
    if ($result['missing'] && !$result['done']) {
        admin_api_json(['success' => false, 'error' => __t('gd_translate_failed', $lang), 'missing' => $result['missing']], 502);
    }
    admin_api_json(['success' => true, 'missing' => $result['missing']]);
} catch (Throwable $e) {
    admin_api_server_error($e, 'gedagte/translate');
}
