<?php
declare(strict_types=1);
/**
 * Kennisgewings (notifications) — shared server code for the page and the APIs.
 *
 * Every area writes its notifications to the same SQLite store
 * (data/notifications.db), one row per recipient. created_at is SQLite's
 * datetime('now'): UTC without a zone, so it is converted here and the
 * client gets a Unix time it never has to parse.
 *
 * A row may carry translation keys (title_key / message_key + params): the
 * reader then sees it in their own language. Older rows hold Afrikaans
 * text, which known patterns translate (nt_legacy_title / _message).
 */

require_once dirname(__DIR__, 2) . '/includes/languages.php';

const NT_TZ = 'Africa/Johannesburg';
const NT_PAGE = 40;

/** Filter groups on the page: which stored types belong to which */
const NT_GROUPS = [
    'prayer' => ['prayer'],
    'gospel' => ['gospel', 'comment', 'reaction', 'post', 'tag'],
    'calendar' => ['calendar', 'reminder', 'appointment', 'event', 'visit', 'diary'],
    'thought' => ['thought'],
];

function nt_lang(): string {
    $lang = (string)($_SESSION['language'] ?? 'af');
    return in_array($lang, SUPPORTED_LANGS, true) ? $lang : 'af';
}

function nt_e(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function nt_asset(string $path): string {
    $mtime = @filemtime(dirname(__DIR__, 2) . $path);
    return $path . '?v=' . ($mtime ?: '1');
}

function nt_user_id(): int {
    return (int)($_SESSION['user_id'] ?? 0);
}

function nt_json(array $data, int $status = 200): void {
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function nt_fail(string $code, int $status = 400): void {
    nt_json(['success' => false, 'error' => $code], $status);
}

/** Every write: POST + the session CSRF token in X-CSRF-Token */
function nt_require_post(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        nt_fail('method_not_allowed', 405);
    }
    if (!csrf_verify((string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) {
        nt_fail('csrf', 403);
    }
}

function nt_input(): array {
    $raw = (string)file_get_contents('php://input');
    if (strlen($raw) > 20000) nt_fail('too_long', 413);
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function nt_json_for_script($data): string {
    return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}';
}

// ===== STORE =====

/** The SQLite store, created / upgraded once per request */
function nt_db(): PDO {
    static $db = null;
    if ($db !== null) return $db;

    $dir = dirname(__DIR__, 2) . '/data';
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Failed to create data directory');
    }
    $db = new PDO('sqlite:' . $dir . '/notifications.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->exec('PRAGMA busy_timeout = 3000');
    $db->exec("
        CREATE TABLE IF NOT EXISTS notifications (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            title TEXT NOT NULL,
            message TEXT,
            type TEXT DEFAULT 'info',
            is_read INTEGER DEFAULT 0,
            created_at TEXT DEFAULT (datetime('now')),
            link TEXT,
            icon TEXT,
            title_key TEXT,
            message_key TEXT,
            params TEXT
        )
    ");
    $cols = $db->query('PRAGMA table_info(notifications)')->fetchAll(PDO::FETCH_COLUMN, 1);
    foreach (['title_key', 'message_key', 'params'] as $col) {
        if (!in_array($col, $cols, true)) {
            $db->exec("ALTER TABLE notifications ADD COLUMN $col TEXT");
        }
    }
    // Every list asks "this user's, newest first"
    $db->exec('CREATE INDEX IF NOT EXISTS idx_notifications_user ON notifications (user_id, id)');
    return $db;
}

function nt_group_of(string $type): string {
    foreach (NT_GROUPS as $group => $types) {
        if (in_array($type, $types, true)) return $group;
    }
    return 'other';
}

/** [SQL condition, params] for a filter: all, unread, or one of the groups */
function nt_filter_sql(string $filter): array {
    if ($filter === 'unread') return ['is_read = 0', []];
    if (isset(NT_GROUPS[$filter])) {
        $types = NT_GROUPS[$filter];
        return ['type IN (' . implode(',', array_fill(0, count($types), '?')) . ')', $types];
    }
    if ($filter === 'other') {
        $types = array_merge(...array_values(NT_GROUPS));
        return ['(type IS NULL OR type NOT IN (' . implode(',', array_fill(0, count($types), '?')) . '))', $types];
    }
    return ['1 = 1', []];
}

/** How many there are in every filter (the chips) */
function nt_counts(PDO $db, int $userId): array {
    $counts = ['all' => 0, 'unread' => 0, 'prayer' => 0, 'gospel' => 0, 'calendar' => 0, 'thought' => 0, 'other' => 0];
    $st = $db->prepare('SELECT type, is_read, COUNT(*) AS n FROM notifications WHERE user_id = ? GROUP BY type, is_read');
    $st->execute([$userId]);
    foreach ($st->fetchAll() as $r) {
        $n = (int)$r['n'];
        $counts['all'] += $n;
        if ((int)$r['is_read'] === 0) $counts['unread'] += $n;
        $counts[nt_group_of((string)($r['type'] ?? ''))] += $n;
    }
    return $counts;
}

/**
 * Only links inside this site are followed: a path ("/calendar/..."), or a
 * full address on the app's own host, which becomes its path.
 */
function nt_safe_link(?string $link): ?string {
    $link = trim((string)$link);
    if ($link === '' || preg_match('/[\x00-\x1F\x7F\\\\]/', $link)) return null;
    if (preg_match('~^https?://~i', $link)) {
        $host = strtolower((string)parse_url($link, PHP_URL_HOST));
        $own = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
        if ($host === '' || ($host !== 'oacapp.co.za' && $host !== 'www.oacapp.co.za' && $host !== $own)) return null;
        $path = (string)parse_url($link, PHP_URL_PATH);
        $query = parse_url($link, PHP_URL_QUERY);
        $frag = parse_url($link, PHP_URL_FRAGMENT);
        $link = ($path !== '' ? $path : '/') . ($query ? '?' . $query : '') . ($frag ? '#' . $frag : '');
    }
    if ($link[0] !== '/' || (isset($link[1]) && $link[1] === '/')) return null;
    return $link;
}

// ===== OPENING: a tapped notification is taken down =====

/**
 * The key that signs "open" links. Made once (random) and kept in the
 * store itself; INSERT OR IGNORE lets two first requests race safely.
 * Null when it cannot be had: links then go straight to the page.
 */
function nt_open_key(): ?string {
    static $key = false;
    if ($key !== false) return $key;
    $key = null;
    try {
        $db = nt_db();
        $db->exec('CREATE TABLE IF NOT EXISTS nt_meta (k TEXT PRIMARY KEY, v TEXT NOT NULL)');
        $get = $db->prepare('SELECT v FROM nt_meta WHERE k = ?');
        $get->execute(['open_key']);
        $v = $get->fetchColumn();
        if ($v === false) {
            $db->prepare('INSERT OR IGNORE INTO nt_meta (k, v) VALUES (?, ?)')->execute(['open_key', bin2hex(random_bytes(32))]);
            $get->execute(['open_key']);
            $v = $get->fetchColumn();
        }
        if (is_string($v) && strlen($v) >= 32) $key = $v;
    } catch (Throwable $e) {
        error_log('Notifications open key: ' . $e->getMessage());
    }
    return $key;
}

/** The signature that lets open.php take notification $id of $userId down */
function nt_open_sig(int $userId, int $id): ?string {
    if ($userId <= 0 || $id <= 0) return null;
    $key = nt_open_key();
    return $key === null ? null : substr(hash_hmac('sha256', 'open|' . $userId . '|' . $id, $key), 0, 24);
}

/**
 * Where a tap on notification $id goes: /notifications/open.php deletes it,
 * then goes on to its link. $to (pushes): where to go should it already be
 * gone. Null when no link can be signed: the caller uses the plain link.
 */
function nt_open_path(int $userId, int $id, ?string $to = null): ?string {
    $sig = nt_open_sig($userId, $id);
    if ($sig === null) return null;
    $query = ['id' => $id, 's' => $sig];
    $to = nt_safe_link($to);
    if ($to !== null) $query['to'] = $to;
    return '/notifications/open.php?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
}

/** SQLite's "YYYY-MM-DD HH:MM:SS" (UTC) as a Unix time */
function nt_ts(?string $created): int {
    $created = trim((string)$created);
    if ($created === '') return time();
    try {
        return (new DateTimeImmutable($created, new DateTimeZone('UTC')))->getTimestamp();
    } catch (Throwable $e) {
        return time();
    }
}

// ===== TRANSLATION =====

/** A key's text in the reader's language with {params} filled, or the stored text */
function nt_translate(string $text, ?string $key, ?string $paramsJson, string $lang): string {
    if ($key) {
        $translated = __t($key, $lang);
        if ($translated !== $key) {
            $params = $paramsJson ? json_decode($paramsJson, true) : null;
            if (is_array($params)) {
                foreach ($params as $name => $value) {
                    if (!is_scalar($value) && $value !== null) continue;
                    $translated = str_replace('{' . $name . '}', (string)$value, $translated);
                }
            }
            return $translated;
        }
    }
    return $text;
}

/** Older rows without keys: known Afrikaans titles in the reader's language */
function nt_legacy_title(string $title, string $lang): string {
    if ($lang === 'af') return $title;
    $patterns = [
        '/^💬\s*Nuwe Kommentaar$/u' => 'notif_new_comment',
        '/^(❤️|🙏)\s*Reaksie$/u' => 'notif_reaction',
        '/^✍️\s*Nuwe Plasing$/u' => 'notif_new_post',
        '/^🏷️\s*Ge-tag$/u' => 'notif_tagged',
        '/^⏰\s*Herinnering$/u' => 'notif_event_reminder',
        '/^📅\s*(Aktiwiteit|Gebeurtenis) Gedeel$/u' => 'notif_event_shared',
        '/^📓\s*Dagboek Herinnering$/u' => 'notif_diary_reminder',
        '/^🏠\s*Besoek Geskeduleer$/u' => 'notif_visit_scheduled',
        '/^✅\s*Account Goedgekeur$/u' => 'notif_account_approved',
        '/^❌\s*Account Afgekeur$/u' => 'notif_account_rejected',
        '/^💍\s*Eggenoot Versoek$/u' => 'notif_spouse_request',
        '/^💕\s*Eggenoot Gekoppel$/u' => 'notif_spouse_accepted',
        '/^📅\s*Afspraak Versoek$/u' => 'notif_appointment_request',
        '/^✅\s*Afspraak Bevestig$/u' => 'notif_appointment_confirmed',
        '/^⭐\s*Amp Verander$/u' => 'notif_ampte_change',
    ];
    foreach ($patterns as $pattern => $key) {
        if (preg_match($pattern, $title, $m)) {
            $translated = __t($key, $lang);
            if ($translated !== $key) {
                if ($key === 'notif_reaction' && isset($m[1])) {
                    $translated = str_replace('{emoji}', $m[1], $translated);
                }
                return $translated;
            }
        }
    }
    return $title;
}

/** Older rows without keys: known Afrikaans messages in the reader's language */
function nt_legacy_message(string $message, string $lang): string {
    if ($lang === 'af' || $message === '') return $message;

    $named = [
        '/^(.+?) het op jou plasing gereageer\.$/u' => 'notif_new_comment_msg',
        "/^(.+?) het jou in 'n plasing ge-tag\.$/u" => 'notif_tagged_msg',
        '/^(.+?) wil met jou as eggenoot koppel\.$/u' => 'notif_spouse_request_msg',
        '/^(.+?) het jou versoek aanvaar!$/u' => 'notif_spouse_accepted_msg',
    ];
    foreach ($named as $pattern => $key) {
        if (preg_match($pattern, $message, $m)) {
            return str_replace('{name}', $m[1], __t($key, $lang));
        }
    }
    if (preg_match('/^(.+?) het (❤️|🙏) op jou plasing gegee\.$/u', $message, $m)) {
        return str_replace(['{name}', '{emoji}'], [$m[1], $m[2]], __t('notif_reaction_msg', $lang));
    }
    if (preg_match('/^(.+?) het in (.+?) geplaas\.$/u', $message, $m)) {
        return str_replace(['{name}', '{room}'], [$m[1], $m[2]], __t('notif_new_post_msg', $lang));
    }
    if (strpos($message, 'Jou rekening is goedgekeur') !== false) return __t('notif_account_approved_msg', $lang);
    if (strpos($message, 'Jou rekening is afgekeur') !== false) return __t('notif_account_rejected_msg', $lang);
    if (strpos($message, 'Jou afspraak is bevestig') !== false) return __t('notif_appointment_confirmed_msg', $lang);
    if (preg_match('/^Jou amp is verander na: (.+)$/u', $message, $m)) {
        return str_replace('{amp}', $m[1], __t('notif_ampte_change_msg', $lang));
    }
    return $message;
}

/** A row for the client, in the reader's language */
function nt_out(array $r, string $lang): array {
    $titleKey = $r['title_key'] ?? null;
    $messageKey = $r['message_key'] ?? null;
    $params = $r['params'] ?? null;

    $type = (string)($r['type'] ?? 'info');

    $title = nt_translate((string)($r['title'] ?? ''), $titleKey, $params, $lang);
    if (!$titleKey) $title = nt_legacy_title($title, $lang);
    $message = nt_translate((string)($r['message'] ?? ''), $messageKey, $params, $lang);
    // A thought is free text (already in the member's language): the legacy
    // patterns would "translate" a sentence like "... het in ... geplaas."
    if (!$messageKey && $type !== 'thought') $message = nt_legacy_message($message, $lang);

    // The page draws its own icon, so a leading emoji in the title is dropped
    // (the push on the phone keeps it; a reaction's emoji is in the message too)
    $bare = preg_replace('/^[\p{Extended_Pictographic}\x{FE0F}\x{200D}\x{20E3}\s]+/u', '', $title);
    if (is_string($bare) && trim($bare) !== '') $title = $bare;

    $id = (int)$r['id'];
    $link = nt_safe_link($r['link'] ?? null);

    return [
        'id' => $id,
        'title' => trim($title),
        'message' => trim($message),
        'type' => $type,
        'group' => nt_group_of($type),
        'link' => $link,
        // Tapping goes through open.php, which takes the notification down
        'open' => $link !== null ? nt_open_path((int)($r['user_id'] ?? 0), $id) : null,
        'read' => (int)($r['is_read'] ?? 0) === 1,
        'ts' => nt_ts($r['created_at'] ?? null),
    ];
}

// ===== PAGE =====

function nt_js_strings(string $lang): array {
    $keys = [
        'today', 'just_now', 'delete', 'cancel', 'close', 'mark_all_read', 'mark_as_read',
        'time_yesterday', 'time_minutes_ago', 'time_hours_ago', 'nt_unread_n', 'nt_unread_one', 'nt_all_read',
        'nt_deleted', 'nt_deleted_n', 'nt_undo', 'nt_all_marked', 'nt_clear_read_q', 'nt_clear_read_text',
        'nt_clear_read', 'nt_cleared_n', 'nt_nothing_to_clear', 'nt_empty', 'nt_empty_hint', 'nt_none_filter',
        'nt_show_all', 'nt_load_failed', 'nt_retry', 'nt_load_more', 'nt_unread_label',
        'nt_err_session', 'nt_err_offline', 'nt_err_generic', 'nt_delete_one',
        'nt_group_prayer', 'nt_group_gospel', 'nt_group_calendar', 'nt_group_thought', 'nt_group_other',
    ];
    $out = [];
    foreach ($keys as $k) {
        $out[$k] = __t($k, $lang);
    }
    $out['days'] = array_map(fn($k) => __t($k, $lang), ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday']);
    $out['months'] = array_map(fn($k) => __t($k, $lang), ['january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december']);
    return $out;
}
