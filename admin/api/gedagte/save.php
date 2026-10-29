<?php
declare(strict_types=1);
/**
 * POST /admin/api/gedagte/save.php — save a daily thought (elders, amp 1-5).
 *
 * Fields: content, author, lang (the language the thought is written in),
 * display_date (YYYY-MM-DD), display_time (HH:MM, empty = all day), and id
 * when an existing thought is being edited.
 * send_now=1 ("Send now"): the date and time become this moment (South
 * African time, the fields are ignored) and the thought goes out at once,
 * also when it was sent before; today's thought, if any, is replaced. It is
 * claimed as sent when it is written, so the crons leave it alone while it is
 * still being translated.
 *
 * One thought per date: a new thought on a taken date replaces it, and an
 * edited thought moved to a taken date replaces the one there.
 * Before answering, the thought is translated into every other app language,
 * so the elder sees at once which languages are done (the save itself never
 * fails because of the AI; a missing language is tried again before sending).
 *
 * Notifications: when the display moment has already come (today, time
 * passed) they go out after the answer; a later moment is left to the thought
 * crons. Editing a thought that was already sent (a typo fixed) never sends it
 * again, and a past date is archive only.
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

// Keep running after the answer so the translations and notifications can finish
ignore_user_abort(true);
@set_time_limit(0);

$userId = dt_admin_guard($pdo);

// POST only, with the session's CSRF token
admin_api_require_post();

$lang = admin_api_lang();
$id = (int)dt_post('id');
$content = dt_plain(dt_post('content'));
$author = dt_line(dt_post('author'));
$thoughtLang = dt_lang(dt_post('lang'), 'af');
$displayDate = trim(dt_post('display_date'));
$displayTime = trim(dt_post('display_time'));
$sendNow = dt_post('send_now') === '1';
if ($sendNow) {
    $now = dt_now();
    $displayDate = $now->format('Y-m-d');
    $displayTime = $now->format('H:i');
}

if ($content === '') {
    admin_api_fail('adm_err_invalid');
}
if (mb_strlen($content, 'UTF-8') > DT_MAX_CHARS) {
    admin_api_json(['success' => false, 'error' => str_replace('{n}', (string)DT_MAX_CHARS, __t('gd_too_long', $lang))], 400);
}
if (mb_strlen($author, 'UTF-8') > DT_MAX_AUTHOR) {
    admin_api_fail('adm_err_invalid');
}
if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $displayDate, $d) || !checkdate((int)$d[2], (int)$d[3], (int)$d[1])) {
    admin_api_fail('adm_err_invalid');
}
if ($displayTime !== '' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $displayTime)) {
    admin_api_fail('adm_err_invalid');
}

$hash = dt_hash($thoughtLang, $content, $author);
$timeFull = $displayTime !== '' ? $displayTime . ':00' : null;

try {
    dt_ensure_schema($pdo); // before the transaction: DDL commits

    $pdo->beginTransaction();

    $editing = null;
    if ($id > 0) {
        $st = $pdo->prepare('SELECT id, display_date, notification_sent FROM daily_thoughts WHERE id = ? FOR UPDATE');
        $st->execute([$id]);
        $editing = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$editing) {
            $pdo->rollBack();
            admin_api_fail('adm_err_not_found', 404);
        }
    }

    $st = $pdo->prepare('SELECT id FROM daily_thoughts WHERE display_date = ? FOR UPDATE');
    $st->execute([$displayDate]);
    $onDate = $st->fetchColumn();
    $onDate = $onDate !== false ? (int)$onDate : null;

    $update = $pdo->prepare('
        UPDATE daily_thoughts
        SET content = ?, author = ?, lang = ?, source_hash = ?, display_date = ?, display_time = ?,
            notification_sent = ?, created_by = ?
        WHERE id = ?
    ');

    if ($editing) {
        $thoughtId = (int)$editing['id'];
        if ($onDate !== null && $onDate !== $thoughtId) {
            // Moved onto a date that has a thought: this one takes its place
            $pdo->prepare('DELETE FROM daily_thought_translations WHERE thought_id = ?')->execute([$onDate]);
            $pdo->prepare('DELETE FROM daily_thoughts WHERE id = ?')->execute([$onDate]);
        }
        // Already sent and still on its date: a correction, not a new thought
        $sent = ((string)$editing['display_date'] === $displayDate && (int)$editing['notification_sent'] === 1) ? 1 : 0;
        if ($sendNow) $sent = 1; // claimed here, sent below (again, if it went out before)
        $update->execute([$content, $author !== '' ? $author : null, $thoughtLang, $hash, $displayDate, $timeFull, $sent, $userId, $thoughtId]);
    } elseif ($onDate !== null) {
        // A new thought on a taken date replaces it
        $thoughtId = $onDate;
        $update->execute([$content, $author !== '' ? $author : null, $thoughtLang, $hash, $displayDate, $timeFull, $sendNow ? 1 : 0, $userId, $thoughtId]);
    } else {
        $pdo->prepare('
            INSERT INTO daily_thoughts (content, author, lang, source_hash, display_date, display_time, notification_sent, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ')->execute([$content, $author !== '' ? $author : null, $thoughtLang, $hash, $displayDate, $timeFull, $sendNow ? 1 : 0, $userId]);
        $thoughtId = (int)$pdo->lastInsertId();
    }

    // Translations of an earlier wording (or of another source language) no longer count
    $pdo->prepare('DELETE FROM daily_thought_translations WHERE thought_id = ? AND (source_hash <> ? OR lang = ?)')
        ->execute([$thoughtId, $hash, $thoughtLang]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    admin_api_server_error($e, 'gedagte/save');
}

// --- Translate into every other language (never fails the save) ---
$ai = false;
$missing = [];
$tried = false;
try {
    $ai = dt_ai_ready();
    $thought = dt_load($pdo, $thoughtId);
    $missing = $thought ? dt_missing_langs($thought) : [];
    // ai_limit_error: the per-elder AI rate limit shared with the teaching editor
    if ($missing && $ai && ai_limit_error($userId, $content) === null) {
        $tried = true;
        $missing = dt_translate_missing($pdo, $thoughtId)['missing'];
    }
} catch (Throwable $e) {
    error_log('Daily thought #' . $thoughtId . ' translate on save: ' . $e->getMessage());
}

// --- Notify now only when the display moment has already come today (always for "Send now") ---
$now = dt_now();
$shouldNotify = $sendNow || ($displayDate === $now->format('Y-m-d')
    && ($timeFull === null || $timeFull <= $now->format('H:i:s')));

// Answer the browser before the FCM fan-out: with many members the pushes
// take a while, and the thought is already saved.
while (ob_get_level() > 0) ob_end_clean();
echo json_encode(['success' => true, 'id' => $thoughtId, 'ai' => $ai, 'missing' => array_values($missing)], JSON_UNESCAPED_UNICODE);
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    flush();
}

if ($shouldNotify) {
    try {
        // Claim it first, so the crons can never send it a second time meanwhile ("Send now" was claimed on write)
        $claimed = $sendNow;
        if (!$claimed) {
            $claim = $pdo->prepare('UPDATE daily_thoughts SET notification_sent = 1 WHERE id = ? AND notification_sent = 0');
            $claim->execute([$thoughtId]);
            $claimed = $claim->rowCount() > 0;
        }
        if ($claimed) {
            dt_send($pdo, $thoughtId, !$tried);
        }
    } catch (Throwable $e) {
        error_log('Daily thought #' . $thoughtId . ' send on save: ' . $e->getMessage());
    }
}
exit;
