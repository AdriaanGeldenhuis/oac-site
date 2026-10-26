<?php
declare(strict_types=1);
/**
 * Gedagtes (thoughts) — shared server code for the page and the API.
 *
 * One thought per day (daily_thoughts.display_date is unique), written by an
 * elder in the admin. A thought is visible from its display_time onwards
 * (all day when no time is set). "Now" is South African time, computed in
 * PHP like the thought cron does, so the database's own clock never matters.
 *
 * The text is plain: the admin is a textarea. Older thoughts may carry the
 * few tags the admin used to allow (<p>, <br>, <b> ...); they are turned
 * into line breaks / removed on the way out, never sent as HTML.
 */

require_once dirname(__DIR__, 2) . '/includes/languages.php';

const GD_TZ = 'Africa/Johannesburg';
const GD_PAGE = 30;          // thoughts per page in the archive
const GD_SEARCH_WORDS = 5;

function gd_now(): DateTimeImmutable {
    static $tz = null;
    if ($tz === null) $tz = new DateTimeZone(GD_TZ);
    return new DateTimeImmutable('now', $tz);
}

function gd_lang(): string {
    $lang = (string)($_SESSION['language'] ?? 'af');
    return in_array($lang, SUPPORTED_LANGS, true) ? $lang : 'af';
}

function gd_e(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Cache-bust by file time, so browsers keep the assets until they change */
function gd_asset(string $path): string {
    $mtime = @filemtime(dirname(__DIR__, 2) . $path);
    return $path . '?v=' . ($mtime ?: '1');
}

function gd_str($v): string {
    return is_scalar($v) ? (string)$v : '';
}

function gd_json(array $data, int $status = 200): void {
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function gd_json_for_script($data): string {
    return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}';
}

/** Stored text → plain text with its line breaks */
function gd_plain(?string $s): string {
    $s = (string)$s;
    $s = preg_replace('~<\s*br\s*/?\s*>~i', "\n", $s) ?? $s;
    $s = preg_replace('~<\s*/\s*p\s*>~i', "\n\n", $s) ?? $s;
    $s = strip_tags($s);
    $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $s = str_replace(["\r\n", "\r"], "\n", $s);
    $s = preg_replace('/[\x00-\x09\x0B-\x1F\x7F]+/u', '', $s) ?? $s;
    $s = preg_replace("/[ \t]+\n/", "\n", $s) ?? $s;
    $s = preg_replace("/\n{3,}/", "\n\n", $s) ?? $s;
    return trim($s);
}

function gd_line(?string $s): string {
    $s = gd_plain($s);
    return trim(preg_replace('/\s+/u', ' ', $s) ?? $s);
}

/** A thought for the client */
function gd_out(array $r): array {
    return [
        'date' => (string)$r['display_date'],
        'text' => gd_plain($r['content'] ?? ''),
        'author' => gd_line($r['author'] ?? ''),
    ];
}

/** Today's thought once it is visible, or ['pending' => 'HH:MM'] while it is still to come */
function gd_today(PDO $pdo): array {
    $now = gd_now();
    $st = $pdo->prepare('SELECT content, author, display_date, display_time FROM daily_thoughts WHERE display_date = ? LIMIT 1');
    $st->execute([$now->format('Y-m-d')]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) return ['thought' => null, 'pending' => null];

    $time = $row['display_time'] ?? null;
    if ($time !== null && $time !== '' && substr((string)$time, 0, 8) > $now->format('H:i:s')) {
        // Say when, never what: the text stays hidden until its time
        return ['thought' => null, 'pending' => substr((string)$time, 0, 5)];
    }
    return ['thought' => gd_out($row), 'pending' => null];
}

/** LIKE pattern for "contains", with the wildcards in the text escaped */
function gd_like(string $s): string {
    return '%' . addcslashes($s, '\\%_') . '%';
}

/**
 * A page of earlier thoughts (before today), newest first.
 * ?before=YYYY-MM-DD continues the list, ?q= finds every word in the text or the author.
 */
function gd_archive(PDO $pdo, array $src): array {
    $today = gd_now()->format('Y-m-d');
    $where = ['display_date < ?'];
    $params = [$today];

    $before = trim(gd_str($src['before'] ?? ''));
    $isFirstPage = !(preg_match('/^\d{4}-\d{2}-\d{2}$/', $before)
        && checkdate((int)substr($before, 5, 2), (int)substr($before, 8, 2), (int)substr($before, 0, 4)));
    if (!$isFirstPage) {
        $where[] = 'display_date < ?';
        $params[] = $before;
    }

    $q = trim(preg_replace('/\s+/u', ' ', mb_substr(gd_str($src['q'] ?? ''), 0, 100, 'UTF-8')) ?? '');
    $words = $q === '' ? [] : array_slice(preg_split('/\s+/u', $q, -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, GD_SEARCH_WORDS);
    foreach ($words as $word) {
        $where[] = '(content LIKE ? OR author LIKE ?)';
        $like = gd_like($word);
        array_push($params, $like, $like);
    }

    $sql = 'SELECT content, author, display_date FROM daily_thoughts WHERE ' . implode(' AND ', $where)
         . ' ORDER BY display_date DESC LIMIT ' . (GD_PAGE + 1);
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    $hasMore = count($rows) > GD_PAGE;
    $rows = array_slice($rows, 0, GD_PAGE);

    $out = ['thoughts' => array_map('gd_out', $rows), 'has_more' => $hasMore];

    // The total only for the first page (the count line above the list)
    if ($isFirstPage) {
        $cst = $pdo->prepare('SELECT COUNT(*) FROM daily_thoughts WHERE ' . implode(' AND ', $where));
        $cst->execute($params);
        $out['total'] = (int)$cst->fetchColumn();
    }
    return $out;
}

/** Everything gedagtes.js says, in the page language */
function gd_js_strings(string $lang): array {
    $keys = [
        'share', 'copied', 'read_more', 'read_less', 'today', 'thought_of_day', 'time_yesterday',
        'gd_count_n', 'gd_count_one', 'gd_found_n', 'gd_found_one', 'gd_none_found',
        'gd_none_found_hint', 'gd_show_all', 'gd_empty', 'gd_empty_hint', 'gd_load_failed', 'gd_retry',
        'gd_copy', 'gd_copy_failed', 'gd_load_more', 'gd_share_label',
    ];
    $out = [];
    foreach ($keys as $k) {
        $out[$k] = __t($k, $lang);
    }
    $out['days'] = array_map(fn($k) => __t($k, $lang), ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday']);
    $out['days_short'] = array_map(fn($k) => __t($k, $lang), ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat']);
    $out['months'] = array_map(fn($k) => __t($k, $lang), ['january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december']);
    return $out;
}

/** Long date in the page language: "Sondag 25 Oktober 2026" */
function gd_long_date(string $date, string $lang): string {
    $d = new DateTimeImmutable($date);
    $days = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
    $months = ['january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december'];
    return __t($days[(int)$d->format('w')], $lang) . ' ' . (int)$d->format('j') . ' '
        . __t($months[(int)$d->format('n') - 1], $lang) . ' ' . $d->format('Y');
}
