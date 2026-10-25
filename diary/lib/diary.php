<?php
declare(strict_types=1);
/**
 * Diary — shared server code for the page and the APIs.
 *
 * A diary is private. Every query here is scoped to the signed-in user and
 * nothing ever returns someone else's entry. Entries show in their owner's
 * own calendar because calendar/lib/calendar.php reads `diaries` directly,
 * so no copy is written to calendar_events any more (copies made by older
 * versions are removed when their entry is edited or deleted).
 *
 * Dates and times are naive local strings in South African time, as MySQL
 * stores them. "Now" always comes from PHP with an explicit time zone.
 * A stored time of 00:00 means "no time", as in the calendar.
 */

require_once dirname(__DIR__, 2) . '/includes/languages.php';

const DY_TZ = 'Africa/Johannesburg';
const DY_MOODS = ['happy', 'sad', 'excited', 'calm', 'thoughtful', 'grateful', 'inspired', 'tired'];
const DY_WEATHERS = ['sunny', 'cloudy', 'rainy', 'stormy', 'snowy', 'windy'];
const DY_TITLE_MAX = 150;    // characters
const DY_BODY_MAX = 60000;   // bytes, so the text always fits a MySQL TEXT column
const DY_TAG_MAX = 24;       // characters per tag
const DY_TAGS_MAX = 10;
const DY_TAGS_BYTES = 255;   // the stored JSON stays within a VARCHAR(255)
const DY_PAGE = 40;          // entries per page in the list
const DY_EXCERPT = 280;      // characters of text shown in the list
const DY_SEARCH_WORDS = 5;

// ===== BASICS =====

function dy_now(): DateTimeImmutable {
    static $tz = null;
    if ($tz === null) $tz = new DateTimeZone(DY_TZ);
    return new DateTimeImmutable('now', $tz);
}

function dy_lang(): string {
    $lang = (string)($_SESSION['language'] ?? 'af');
    return in_array($lang, SUPPORTED_LANGS, true) ? $lang : 'af';
}

/** Translate, then fill {placeholders} */
function dy_t(string $key, ?string $lang = null, array $params = []): string {
    $text = __t($key, $lang ?? dy_lang());
    foreach ($params as $name => $value) {
        $text = str_replace('{' . $name . '}', (string)$value, $text);
    }
    return $text;
}

function dy_e(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Cache-bust by file time, so browsers keep the assets until they change */
function dy_asset(string $path): string {
    $mtime = @filemtime(dirname(__DIR__, 2) . $path);
    return $path . '?v=' . ($mtime ?: '1');
}

function dy_user_id(): int {
    return (int)($_SESSION['user_id'] ?? 0);
}

/** A value from the client as text; arrays and objects count as empty */
function dy_str($v): string {
    return is_scalar($v) ? (string)$v : '';
}

function dy_len(string $s): int {
    return mb_strlen($s, 'UTF-8');
}

function dy_cut(string $s, int $max): string {
    if (dy_len($s) <= $max) return $s;
    return rtrim(mb_substr($s, 0, $max - 1, 'UTF-8')) . '…';
}

/** Single line, trimmed, control characters removed */
function dy_clean_line(string $s): string {
    $s = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $s) ?? '';
    return trim(preg_replace('/\s{2,}/u', ' ', $s) ?? '');
}

/** Multi-line text: keep newlines (at most two in a row), drop other control characters */
function dy_clean_text(string $s): string {
    $s = str_replace(["\r\n", "\r"], "\n", $s);
    $s = preg_replace('/[\x00-\x09\x0B-\x1F\x7F]+/u', '', $s) ?? '';
    $s = preg_replace("/\n{3,}/", "\n\n", $s) ?? $s;
    return trim($s);
}

function dy_word_count(string $s): int {
    $n = preg_match_all('/[\p{L}\p{N}][\p{L}\p{N}\'’-]*/u', $s);
    return $n === false ? 0 : $n;
}

// ===== RESPONSES =====

function dy_json(array $data, int $status = 200): void {
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: private, no-store');
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function dy_fail(string $code, int $status = 400): void {
    dy_json(['success' => false, 'error' => $code], $status);
}

/** Every write: POST + the session CSRF token in X-CSRF-Token (as the calendar) */
function dy_require_post(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        dy_fail('method_not_allowed', 405);
    }
    $token = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!csrf_verify($token)) {
        dy_fail('csrf', 403);
    }
}

/** The JSON body of a POST */
function dy_input(): array {
    $raw = (string)file_get_contents('php://input');
    if (strlen($raw) > 200000) {
        dy_fail('too_long', 413);
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/** Rate limit (security/rate_limit.php). Fails open, like the login limiter. */
function dy_rate_ok(string $key, int $max): bool {
    require_once dirname(__DIR__, 2) . '/security/rate_limit.php';
    return rate_limit_check($key, $max, 60);
}

// ===== VALIDATION =====

/** Strict YYYY-MM-DD that is a real date */
function dy_date($s): ?string {
    $s = trim(dy_str($s));
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m)) return null;
    $y = (int)$m[1];
    if ($y < 1900 || $y > 2200 || !checkdate((int)$m[2], (int)$m[3], $y)) return null;
    return $s;
}

/** HH:MM (24h); null when invalid */
function dy_time($s): ?string {
    $s = trim(dy_str($s));
    if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)(:[0-5]\d)?$/', $s, $m)) return null;
    return $m[1] . ':' . $m[2];
}

/** A stored time as HH:MM, or null for "no time" (00:00, empty, invalid) */
function dy_stored_time($t): ?string {
    $t = dy_time((string)$t);
    return ($t === null || $t === '00:00') ? null : $t;
}

function dy_choice($value, array $allowed): ?string {
    $value = trim(dy_str($value));
    return in_array($value, $allowed, true) ? $value : null;
}

/** One tag: a short single line without the characters that would break the stored JSON */
function dy_tag_clean($tag): string {
    $tag = dy_clean_line(str_replace(['"', '\\', '#', ',', '[', ']'], ' ', dy_str($tag)));
    return dy_len($tag) > DY_TAG_MAX ? rtrim(mb_substr($tag, 0, DY_TAG_MAX, 'UTF-8')) : $tag;
}

/** At most DY_TAGS_MAX distinct tags whose JSON fits DY_TAGS_BYTES */
function dy_tags_clean($tags): array {
    if (!is_array($tags)) return [];
    $out = [];
    $seen = [];
    foreach ($tags as $tag) {
        if (!is_string($tag) && !is_int($tag)) continue;
        $tag = dy_tag_clean($tag);
        $key = mb_strtolower($tag, 'UTF-8');
        if ($tag === '' || isset($seen[$key])) continue;
        $try = array_merge($out, [$tag]);
        if (strlen((string)json_encode($try, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) > DY_TAGS_BYTES) break;
        $seen[$key] = true;
        $out = $try;
        if (count($out) >= DY_TAGS_MAX) break;
    }
    return $out;
}

function dy_tags_json(array $tags): ?string {
    return $tags ? json_encode($tags, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
}

function dy_tags_decode($json): array {
    if (!is_string($json) || $json === '') return [];
    $tags = json_decode($json, true);
    if (!is_array($tags)) return [];
    return array_values(array_filter(array_map(
        fn($t) => is_scalar($t) ? dy_clean_line((string)$t) : '',
        $tags
    ), fn($t) => $t !== ''));
}

/**
 * The fields of an entry as the client sends them, validated.
 * Returns [fields, null] or [null, error code].
 */
function dy_entry_from_input(array $in): array {
    $date = dy_date($in['date'] ?? '');
    if ($date === null) return [null, 'invalid_date'];

    $rawTime = trim(dy_str($in['time'] ?? ''));
    $time = $rawTime === '' ? null : dy_time($rawTime);
    if ($rawTime !== '' && $time === null) return [null, 'invalid_time'];

    $title = dy_clean_line(dy_str($in['title'] ?? ''));
    $body = dy_clean_text(dy_str($in['body'] ?? ''));
    if ($title === '' && $body === '') return [null, 'empty_entry'];
    if (strlen($body) > DY_BODY_MAX) return [null, 'too_long'];
    if (dy_len($title) > DY_TITLE_MAX) {
        $title = rtrim(mb_substr($title, 0, DY_TITLE_MAX, 'UTF-8')); // older entries may have longer titles
    }

    return [[
        'date' => $date,
        'time' => ($time ?? '00:00') . ':00',
        'title' => $title !== '' ? $title : null,
        'body' => $body !== '' ? $body : null,
        'mood' => dy_choice($in['mood'] ?? '', DY_MOODS),
        'weather' => dy_choice($in['weather'] ?? '', DY_WEATHERS),
        'tags' => dy_tags_json(dy_tags_clean($in['tags'] ?? [])),
    ], null];
}

// ===== ENTRIES =====

const DY_COLUMNS = 'id, date, time, title, body, mood, weather, tags, created_at, updated_at';

/** An entry for the client: the whole text, or an excerpt for the list */
function dy_entry_out(array $r, bool $full): array {
    $body = dy_clean_text((string)($r['body'] ?? ''));
    $out = [
        'id' => (int)$r['id'],
        'date' => (string)$r['date'],
        'time' => dy_stored_time($r['time'] ?? null),
        'title' => dy_clean_line((string)($r['title'] ?? '')),
        'mood' => dy_choice($r['mood'] ?? '', DY_MOODS),
        'weather' => dy_choice($r['weather'] ?? '', DY_WEATHERS),
        'tags' => dy_tags_decode($r['tags'] ?? null),
        'words' => dy_word_count($body),
    ];
    if ($full) {
        $out['body'] = $body;
        $out['created_at'] = $r['created_at'] ?? null;
        $out['updated_at'] = $r['updated_at'] ?? null;
    } else {
        $out['excerpt'] = dy_cut(dy_clean_line($body), DY_EXCERPT);
    }
    return $out;
}

/** One of my entries, or null */
function dy_find(PDO $pdo, int $userId, int $id): ?array {
    if ($userId <= 0 || $id <= 0) return null;
    $st = $pdo->prepare('SELECT ' . DY_COLUMNS . ' FROM diaries WHERE id = ? AND user_id = ? LIMIT 1');
    $st->execute([$id, $userId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/** LIKE pattern for "contains", with the wildcards in the text escaped */
function dy_like(string $s): string {
    return '%' . addcslashes($s, '\\%_') . '%';
}

/** [from, to] for this week (Monday–Sunday), month or year in SAST; null for "all" */
function dy_period_range(string $period): ?array {
    $now = dy_now();
    switch ($period) {
        case 'week':
            $monday = $now->modify('-' . ((int)$now->format('N') - 1) . ' days');
            return [$monday->format('Y-m-d'), $monday->modify('+6 days')->format('Y-m-d')];
        case 'month':
            return [$now->format('Y-m-01'), $now->format('Y-m-t')];
        case 'year':
            return [$now->format('Y-01-01'), $now->format('Y-12-31')];
        default:
            return null;
    }
}

/**
 * WHERE clause for my entries, filtered by ?q= (every word somewhere in the
 * title, text or tags), ?period= and ?tag= (exact tag). Positional
 * placeholders only: the database runs with real prepared statements.
 */
function dy_where(int $userId, array $src): array {
    $where = ['user_id = ?'];
    $params = [$userId];

    $q = dy_clean_line(dy_str($src['q'] ?? ''));
    if ($q !== '') {
        $words = array_slice(preg_split('/\s+/u', mb_substr($q, 0, 100, 'UTF-8'), -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, DY_SEARCH_WORDS);
        foreach ($words as $word) {
            $where[] = '(title LIKE ? OR body LIKE ? OR tags LIKE ?)';
            $like = dy_like($word);
            array_push($params, $like, $like, $like);
        }
    }

    $range = dy_period_range(dy_str($src['period'] ?? ''));
    if ($range !== null) {
        $where[] = 'date BETWEEN ? AND ?';
        array_push($params, $range[0], $range[1]);
    }

    $tag = dy_tag_clean($src['tag'] ?? '');
    if ($tag !== '') {
        $where[] = 'tags LIKE ?';
        $params[] = '%' . addcslashes((string)json_encode($tag, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), '\\%_') . '%';
    }

    return [implode(' AND ', $where), $params];
}

function dy_order(array $src): string {
    return (($src['sort'] ?? '') === 'oldest')
        ? 'date ASC, time ASC, id ASC'
        : 'date DESC, time DESC, id DESC';
}

/** Remove a copy that older versions wrote to calendar_events (nothing shows it any more) */
function dy_drop_calendar_copy(PDO $pdo, int $userId, int $entryId, $calendarEventId): void {
    $calId = (int)$calendarEventId;
    if ($calId <= 0) return;
    $pdo->prepare('DELETE FROM calendar_events WHERE id = ? AND user_id = ?')->execute([$calId, $userId]);
    $pdo->prepare('UPDATE diaries SET calendar_event_id = NULL WHERE id = ? AND user_id = ?')->execute([$entryId, $userId]);
}

// ===== PAGE =====

/** Everything diary.js says, in the page language */
function dy_js_strings(string $lang): array {
    $keys = [
        'new_entry', 'save', 'cancel', 'delete', 'edit', 'share', 'close', 'today', 'date', 'time', 'title',
        'mood', 'weather', 'tags', 'copied', 'ai_assist', 'loading_entries', 'write_thoughts_here',
        'my_thoughts_today', 'newest_first', 'oldest_first',
        'dy_yesterday', 'dy_entries_n', 'dy_entry_one', 'dy_found_n', 'dy_found_one', 'dy_words_n', 'dy_word_one',
        'dy_no_title', 'dy_empty_title', 'dy_empty_hint', 'dy_write_first', 'dy_none_found', 'dy_none_found_hint',
        'dy_show_all', 'dy_load_failed', 'dy_retry', 'dy_years_ago', 'dy_year_ago', 'dy_tag_filter',
        'dy_remove_filter', 'dy_reading', 'dy_writing', 'dy_edit_entry', 'dy_optional', 'dy_tag_ph', 'dy_add_tag',
        'dy_remove_tag', 'dy_calendar_note', 'dy_saving', 'dy_saved', 'dy_deleted', 'dy_delete_q', 'dy_cannot_undo',
        'dy_discard_q', 'dy_discard_text', 'dy_discard', 'dy_keep_writing', 'dy_draft_restored', 'dy_draft_discard',
        'dy_draft_kept', 'dy_undo', 'dy_written', 'dy_edited', 'dy_open_calendar', 'dy_prev', 'dy_next',
        'dy_share_title', 'dy_share_text', 'dy_share_text_hint', 'dy_copy_text', 'dy_copy_failed', 'dy_download_pdf',
        'dy_downloading', 'dy_export_title', 'dy_export_hint', 'dy_export_pdf', 'dy_export_pdf_hint', 'dy_export_txt',
        'dy_export_txt_hint', 'dy_ai_title', 'dy_ai_hint', 'dy_ai_use', 'dy_ai_keep', 'dy_ai_working', 'dy_ai_used',
        // Error codes from the APIs (diary.js maps them)
        'dy_err_empty', 'dy_err_date', 'dy_err_time', 'dy_err_too_long', 'dy_err_session', 'dy_err_offline',
        'dy_err_generic', 'dy_err_rate', 'dy_err_not_found', 'dy_ai_unavailable', 'dy_ai_too_long',
    ];
    $out = [];
    foreach ($keys as $k) {
        $out[$k] = __t($k, $lang);
    }
    foreach (DY_MOODS as $m) {
        $out['mood_' . $m] = __t('dy_mood_' . $m, $lang);
    }
    foreach (DY_WEATHERS as $w) {
        $out['weather_' . $w] = __t('dy_weather_' . $w, $lang);
    }
    $out['days'] = array_map(fn($k) => __t($k, $lang), ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday']);
    $out['days_short'] = array_map(fn($k) => __t($k, $lang), ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat']);
    $out['months'] = array_map(fn($k) => __t($k, $lang), ['january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december']);
    return $out;
}

function dy_json_for_script($data): string {
    return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}';
}

/** Long date in the page language: "Sondag 25 Oktober 2026" */
function dy_long_date(string $date, string $lang): string {
    $d = new DateTimeImmutable($date);
    $days = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
    $months = ['january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december'];
    return __t($days[(int)$d->format('w')], $lang) . ' ' . (int)$d->format('j') . ' '
        . __t($months[(int)$d->format('n') - 1], $lang) . ' ' . $d->format('Y');
}
