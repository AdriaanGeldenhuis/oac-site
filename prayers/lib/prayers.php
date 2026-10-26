<?php
declare(strict_types=1);
/**
 * Gebede (prayers) — shared server code for the page and the APIs.
 *
 * Every member sees every prayer (one prayer wall for the church); the
 * page can narrow it to the member's own town or to their own prayers.
 *
 * Who may do what (the APIs check, the page only mirrors it):
 *   - anyone signed in: share a prayer, pray along, send a heart, comment
 *   - the writer: edit and delete their own prayer / comment
 *   - officers (amp 1-6, as in Gospel Media's community rooms): delete
 *     any prayer or comment, never edit someone else's words
 *
 * Text is stored as plain text and only ever drawn with textContent.
 * Photos are re-encoded to WebP on the server (never stored as uploaded).
 */

require_once dirname(__DIR__, 2) . '/includes/languages.php';

const PR_TEXT_MAX = 3000;       // characters in a prayer
const PR_COMMENT_MAX = 1000;    // characters in a comment
const PR_PAGE = 15;             // prayers per page
const PR_PHOTO_MAX_BYTES = 15 * 1024 * 1024;
const PR_PHOTO_MAX_DIM = 1920;
const PR_UPLOAD_DIR = '/uploads/prayers/';
const PR_MOD_AMP_MIN = 1;
const PR_MOD_AMP_MAX = 6;

// ===== BASICS =====

function pr_lang(): string {
    $lang = (string)($_SESSION['language'] ?? 'af');
    return in_array($lang, SUPPORTED_LANGS, true) ? $lang : 'af';
}

function pr_t(string $key, ?string $lang = null, array $params = []): string {
    $text = __t($key, $lang ?? pr_lang());
    foreach ($params as $name => $value) {
        $text = str_replace('{' . $name . '}', (string)$value, $text);
    }
    return $text;
}

function pr_e(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function pr_asset(string $path): string {
    $mtime = @filemtime(dirname(__DIR__, 2) . $path);
    return $path . '?v=' . ($mtime ?: '1');
}

function pr_user_id(): int {
    return (int)($_SESSION['user_id'] ?? 0);
}

function pr_str($v): string {
    return is_scalar($v) ? (string)$v : '';
}

/** Multi-line text: newlines kept (at most two in a row), other control characters dropped */
function pr_clean_text(string $s): string {
    $s = str_replace(["\r\n", "\r"], "\n", $s);
    $s = preg_replace('/[\x00-\x09\x0B-\x1F\x7F]+/u', '', $s) ?? '';
    $s = preg_replace("/[ \t]+\n/", "\n", $s) ?? $s;
    $s = preg_replace("/\n{3,}/", "\n\n", $s) ?? $s;
    return trim($s);
}

function pr_len(string $s): int {
    return mb_strlen($s, 'UTF-8');
}

// ===== RESPONSES =====

function pr_json(array $data, int $status = 200): void {
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function pr_fail(string $code, int $status = 400): void {
    pr_json(['success' => false, 'error' => $code], $status);
}

/**
 * Send the answer now and keep running (to notify someone): the tap feels
 * instant. With PHP-FPM the request is finished; without it, Content-Length
 * and Connection: close let the browser stop waiting all the same.
 */
function pr_answer_and_continue(array $data): void {
    $body = (string)json_encode($data, JSON_UNESCAPED_UNICODE);
    ignore_user_abort(true);
    while (ob_get_level() > 0) ob_end_clean();
    if (!headers_sent()) {
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        header('Content-Length: ' . strlen($body));
        header('Connection: close');
    }
    echo $body;
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } else {
        flush();
    }
}

/** Every write: POST + the session CSRF token in X-CSRF-Token */
function pr_require_post(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        pr_fail('method_not_allowed', 405);
    }
    if (!csrf_verify((string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) {
        pr_fail('csrf', 403);
    }
}

/**
 * A multipart POST bigger than PHP's post_max_size arrives with $_POST and
 * $_FILES empty: say "photo too big", not "write something first".
 */
function pr_require_body(): void {
    if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        pr_fail('photo_too_big', 413);
    }
}

/** The JSON body of a POST */
function pr_input(): array {
    $raw = (string)file_get_contents('php://input');
    if (strlen($raw) > 50000) pr_fail('too_long', 413);
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/** Rate limit (security/rate_limit.php), per user, an hour long. Fails open like the login limiter. */
function pr_rate_ok(string $key, int $max): bool {
    require_once dirname(__DIR__, 2) . '/security/rate_limit.php';
    return rate_limit_check($key . ':' . pr_user_id(), $max, 60);
}

function pr_json_for_script($data): string {
    return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}';
}

// ===== PEOPLE =====

/** The signed-in member: name, office, town (cached per request) */
function pr_me(PDO $pdo): array {
    static $me = null;
    if ($me !== null) return $me;
    $st = $pdo->prepare('SELECT u.id, u.name, u.surname, u.photo, u.amp_id, u.gender, u.town_id, t.name AS town_name
                         FROM users u LEFT JOIN towns t ON t.id = u.town_id WHERE u.id = ? LIMIT 1');
    $st->execute([pr_user_id()]);
    $me = $st->fetch(PDO::FETCH_ASSOC) ?: ['id' => pr_user_id(), 'amp_id' => 10, 'town_id' => null];
    return $me;
}

function pr_is_moderator(PDO $pdo): bool {
    $amp = (int)(pr_me($pdo)['amp_id'] ?? 10);
    return $amp >= PR_MOD_AMP_MIN && $amp <= PR_MOD_AMP_MAX;
}

function pr_full_name(array $r): string {
    return trim(preg_replace('/\s+/u', ' ', (string)($r['name'] ?? '') . ' ' . (string)($r['surname'] ?? '')) ?? '');
}

/** "Oudste Adriaan Geldenhuis": office (in the reader's language) and name */
function pr_display_name(array $r, string $lang): string {
    $name = pr_full_name($r);
    $office = get_translated_office((int)($r['amp_id'] ?? 10) ?: 10, (string)($r['gender'] ?? 'man') ?: 'man', $lang);
    return trim($office . ' ' . $name);
}

function pr_initials(string $name): string {
    $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if (count($parts) >= 2) {
        return mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[count($parts) - 1], 0, 1));
    }
    return $parts ? mb_strtoupper(mb_substr($parts[0], 0, 2)) : '?';
}

/** A profile photo as a URL the page can show (as Gospel Media stores them), or null */
function pr_photo_url($photo): ?string {
    $photo = trim((string)$photo);
    if ($photo === '' || preg_match('/[\x00-\x1F"\'<>\\\\]/', $photo)) return null;
    if (preg_match('~^https://~i', $photo) || ($photo[0] === '/' && !str_starts_with($photo, '//'))) return $photo;
    if (preg_match('~^[\w./-]+$~', $photo) && strpos($photo, '..') === false) return '/assets/uploads/' . $photo;
    return null;
}

// ===== PRAYERS =====

/** One prayer row (with its counts) as the client gets it */
function pr_post_out(array $r, PDO $pdo, string $lang): array {
    $me = pr_user_id();
    $mine = (int)$r['user_id'] === $me;
    $name = pr_full_name($r);
    $ts = (int)($r['ts'] ?? 0);
    $uts = (int)($r['uts'] ?? 0);
    return [
        'id' => (int)$r['id'],
        'text' => pr_clean_text((string)($r['text'] ?? '')),
        'photo' => pr_upload_url($r['photo_url'] ?? null),
        'ts' => $ts,
        'edited' => $uts > 0 && $uts - $ts > 60,
        'author' => [
            'name' => pr_display_name($r, $lang),
            'initials' => pr_initials($name),
            'photo' => pr_photo_url($r['user_photo'] ?? null),
            'town' => (string)($r['town_name'] ?? ''),
        ],
        'mine' => $mine,
        'pray_count' => (int)($r['pray_count'] ?? 0),
        'heart_count' => (int)($r['heart_count'] ?? 0),
        'comment_count' => (int)($r['comment_count'] ?? 0),
        'prayed' => (int)($r['user_prayed'] ?? 0) > 0,
        'hearted' => (int)($r['user_hearted'] ?? 0) > 0,
        'can_edit' => $mine,
        'can_delete' => $mine || pr_is_moderator($pdo),
    ];
}

/**
 * The SELECT for prayers with everything a card needs. The town shown is
 * the writer's own town (older prayers were all stored with town 0).
 */
function pr_post_select(): string {
    return "
        SELECT p.id, p.user_id, p.text, p.photo_url,
               UNIX_TIMESTAMP(p.created_at) AS ts, UNIX_TIMESTAMP(p.updated_at) AS uts,
               u.name, u.surname, u.amp_id, u.gender, u.photo AS user_photo, t.name AS town_name,
               (SELECT COUNT(*) FROM prayers_reactions r JOIN users ru ON ru.id = r.user_id WHERE r.post_id = p.id AND r.type = 'pray') AS pray_count,
               (SELECT COUNT(*) FROM prayers_reactions r JOIN users ru ON ru.id = r.user_id WHERE r.post_id = p.id AND r.type = 'heart') AS heart_count,
               (SELECT COUNT(*) FROM prayers_comments c JOIN users cu ON cu.id = c.user_id WHERE c.post_id = p.id) AS comment_count,
               (SELECT COUNT(*) FROM prayers_reactions r WHERE r.post_id = p.id AND r.user_id = ? AND r.type = 'pray') AS user_prayed,
               (SELECT COUNT(*) FROM prayers_reactions r WHERE r.post_id = p.id AND r.user_id = ? AND r.type = 'heart') AS user_hearted
        FROM prayers_posts p
        JOIN users u ON u.id = p.user_id
        LEFT JOIN towns t ON t.id = u.town_id
        WHERE p.kind = 'prayer'";
}

/** One prayer for a card (after a save), or null */
function pr_post_by_id(PDO $pdo, int $id, string $lang): ?array {
    $me = pr_user_id();
    $st = $pdo->prepare(pr_post_select() . ' AND p.id = ? LIMIT 1');
    $st->execute([$me, $me, $id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ? pr_post_out($row, $pdo, $lang) : null;
}

/** The bare row (owner, photo) for permission checks, optionally locked for a write */
function pr_post_row(PDO $pdo, int $id, bool $lock = false): ?array {
    if ($id <= 0) return null;
    $st = $pdo->prepare("SELECT id, user_id, photo_url FROM prayers_posts WHERE id = ? AND kind = 'prayer' LIMIT 1" . ($lock ? ' FOR UPDATE' : ''));
    $st->execute([$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/** Text of a prayer / comment from the client, validated: [text, null] or [null, error] */
function pr_text_from($value, int $max): array {
    $text = pr_clean_text(pr_str($value));
    if ($text === '') return [null, 'empty'];
    if (pr_len($text) > $max) return [null, 'too_long'];
    return [$text, null];
}

// ===== COMMENTS =====

function pr_comment_out(array $r, PDO $pdo, string $lang): array {
    $mine = (int)$r['user_id'] === pr_user_id();
    $name = pr_full_name($r);
    return [
        'id' => (int)$r['id'],
        'post_id' => (int)$r['post_id'],
        'text' => pr_clean_text((string)($r['text'] ?? '')),
        'ts' => (int)($r['ts'] ?? 0),
        'author' => [
            'name' => pr_display_name($r, $lang),
            'initials' => pr_initials($name),
            'photo' => pr_photo_url($r['user_photo'] ?? null),
        ],
        'mine' => $mine,
        'can_edit' => $mine,
        'can_delete' => $mine || pr_is_moderator($pdo),
    ];
}

function pr_comment_select(): string {
    return "
        SELECT c.id, c.post_id, c.user_id, c.text, UNIX_TIMESTAMP(c.created_at) AS ts,
               u.name, u.surname, u.amp_id, u.gender, u.photo AS user_photo
        FROM prayers_comments c
        JOIN users u ON u.id = c.user_id";
}

// ===== PHOTOS =====

/** A stored photo path as a URL, only when it is one of ours */
function pr_upload_url($url): ?string {
    $url = (string)$url;
    // Older versions saved into dated folders: /uploads/prayers/20250920/p_....webp
    return preg_match('~^/uploads/prayers/(?:\d{8}/)?[A-Za-z0-9_-][A-Za-z0-9_.-]*\.(webp|jpe?g|png|gif)$~', $url) ? $url : null;
}

/** Remove a stored photo file (only files inside uploads/prayers/) */
function pr_photo_delete($url): void {
    $url = pr_upload_url($url);
    if ($url === null) return;
    $path = dirname(__DIR__, 2) . $url;
    if (is_file($path)) @unlink($path);
}

/** Room in memory_limit for $bytes more (raised for this request when the host allows it) */
function pr_memory_for(int $bytes): bool {
    $toBytes = function (string $v): int {
        $v = trim($v);
        if ($v === '' || $v === '-1') return -1;
        $n = (int)$v;
        switch (strtolower(substr($v, -1))) {
            case 'g': $n *= 1024;
            // no break
            case 'm': $n *= 1024;
            // no break
            case 'k': $n *= 1024;
        }
        return $n;
    };
    $limit = $toBytes((string)ini_get('memory_limit'));
    if ($limit < 0) return true;
    $need = memory_get_usage(true) + $bytes;
    if ($need <= $limit) return true;
    // A phone photo of 12-50 MP needs more than the usual 128M while GD holds it
    @ini_set('memory_limit', (string)(int)ceil($need / 1048576) . 'M');
    $raised = $toBytes((string)ini_get('memory_limit'));
    return $raised < 0 || $need <= $raised;
}

/**
 * The uploaded "photo" field, re-encoded as WebP (max 1920px, turned the
 * right way up), saved under uploads/prayers/.
 * Returns [url|null, error|null]: [null, null] when no photo was sent.
 */
function pr_photo_from_upload(): array {
    $f = $_FILES['photo'] ?? null;
    if (!is_array($f) || !isset($f['error']) || is_array($f['error'])) return [null, null];
    if ((int)$f['error'] === UPLOAD_ERR_NO_FILE) return [null, null];
    if ((int)$f['error'] === UPLOAD_ERR_INI_SIZE || (int)$f['error'] === UPLOAD_ERR_FORM_SIZE) return [null, 'photo_too_big'];
    if ((int)$f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file((string)$f['tmp_name'])) return [null, 'photo_failed'];
    if ((int)$f['size'] <= 0 || (int)$f['size'] > PR_PHOTO_MAX_BYTES) return [null, 'photo_too_big'];

    $tmp = (string)$f['tmp_name'];
    $info = @getimagesize($tmp);
    if (!$info || empty($info[0]) || empty($info[1])) return [null, 'photo_invalid'];
    [$w, $h] = [(int)$info[0], (int)$info[1]];
    if ($w * $h > 50000000 || !pr_memory_for($w * $h * 5 + 24 * 1024 * 1024)) return [null, 'photo_too_big'];

    $mime = (string)($info['mime'] ?? '');
    switch ($mime) {
        case 'image/jpeg': $img = @imagecreatefromjpeg($tmp); break;
        case 'image/png':  $img = @imagecreatefrompng($tmp); break;
        case 'image/gif':  $img = @imagecreatefromgif($tmp); break;
        case 'image/webp': $img = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmp) : false; break;
        default: return [null, 'photo_invalid'];
    }
    if (!$img) return [null, 'photo_invalid'];

    // Phone photos are often stored sideways with an EXIF note: turn them upright
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($tmp);
        $turn = [3 => 180, 6 => -90, 8 => 90][(int)($exif['Orientation'] ?? 1)] ?? 0;
        if ($turn !== 0) {
            $rotated = @imagerotate($img, $turn, 0);
            if ($rotated) {
                imagedestroy($img);
                $img = $rotated;
                [$w, $h] = [imagesx($img), imagesy($img)];
            }
        }
    }

    $ratio = min(1, PR_PHOTO_MAX_DIM / max($w, $h));
    if ($ratio < 1) {
        $nw = max(1, (int)round($w * $ratio));
        $nh = max(1, (int)round($h * $ratio));
        $resized = imagecreatetruecolor($nw, $nh);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagecopyresampled($resized, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($img);
        $img = $resized;
    } elseif (!imageistruecolor($img)) {
        imagepalettetotruecolor($img);
    }

    $dir = dirname(__DIR__, 2) . PR_UPLOAD_DIR;
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        imagedestroy($img);
        return [null, 'photo_failed'];
    }
    $name = 'prayer_' . bin2hex(random_bytes(12)) . '.webp';
    $ok = @imagewebp($img, $dir . $name, 82);
    imagedestroy($img);
    if (!$ok) return [null, 'photo_failed'];
    return [PR_UPLOAD_DIR . $name, null];
}

// ===== PAGE =====

/** Everything prayers.js says, in the page language */
function pr_js_strings(string $lang): array {
    $keys = [
        'cancel', 'close', 'send', 'comments', 'read_more', 'read_less', 'today', 'just_now', 'all',
        'write_encouraging_word', 'share_prayer_testimony',
        'time_yesterday', 'time_minutes_ago', 'time_hours_ago',
        'pr_pray', 'pr_praying', 'pr_heart', 'pr_comment', 'pr_tally_pray_one', 'pr_tally_pray_n', 'pr_tally_heart_one',
        'pr_tally_heart_n', 'pr_tally_comments_one', 'pr_tally_comments_n', 'pr_edited', 'pr_options',
        'pr_edit', 'pr_delete', 'pr_new_title', 'pr_edit_title', 'pr_text_ph', 'pr_add_photo', 'pr_change_photo',
        'pr_remove_photo', 'pr_share_btn', 'pr_save_btn', 'pr_posting', 'pr_saving', 'pr_shared', 'pr_saved',
        'pr_deleted', 'pr_delete_q', 'pr_delete_text', 'pr_discard_q', 'pr_discard', 'pr_keep',
        'pr_no_comments', 'pr_comment_delete_q', 'pr_comment_deleted', 'pr_comment_edit', 'pr_comment_saved',
        'pr_who_title', 'pr_tab_pray', 'pr_tab_heart', 'pr_who_none', 'pr_empty', 'pr_empty_hint', 'pr_empty_mine',
        'pr_empty_town', 'pr_load_failed', 'pr_retry', 'pr_load_more', 'pr_photo_alt', 'pr_chars_left',
        'pr_not_found', 'pr_you',
        'pr_err_session', 'pr_err_offline', 'pr_err_generic', 'pr_err_rate', 'pr_err_too_long', 'pr_err_empty',
        'pr_err_photo', 'pr_err_photo_big', 'pr_err_forbidden',
    ];
    $out = [];
    foreach ($keys as $k) {
        $out[$k] = __t($k, $lang);
    }
    $out['days'] = array_map(fn($k) => __t($k, $lang), ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday']);
    $out['months'] = array_map(fn($k) => __t($k, $lang), ['january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december']);
    return $out;
}
