<?php
declare(strict_types=1);
/**
 * Daily thought (Gedagte van die Dag) — one place for what the admin, the
 * two thought crons and the pages that show a thought share.
 *
 * One thought per date (daily_thoughts.display_date is unique), written by
 * an elder in its own language (daily_thoughts.lang). Every other app
 * language gets a translation in daily_thought_translations, made by the AI
 * when the thought is saved (and tried once more just before it is sent).
 * A translation belongs to the exact text it came from (source_hash): when
 * the elder changes the thought, the old translations no longer count and
 * are made again, so nobody reads an outdated wording in their language.
 * An elder may correct a translation by hand (is_manual = 1).
 *
 * Every member gets the push and the in-app notification in their own
 * language, with the whole thought: it is only cut (at a word, with "…")
 * when FCM's 4 KB data limit would be passed.
 *
 * "Now" is South African time computed in PHP, never the database clock.
 */

require_once __DIR__ . '/languages.php';

const DT_TZ = 'Africa/Johannesburg';
// The longest thought an elder may write: far more than a thought needs, only a
// guard for the TEXT column (64 KB holds it even at 4 bytes per character)
const DT_MAX_CHARS = 10000;
const DT_MAX_AUTHOR = 255;
const DT_PUSH_BYTES = 3000;     // push body; FCM allows 4096 bytes for all data fields together
const DT_LINK = '/gedagtes/gedagtes.php';

/** Language names as the AI is given them (one per SUPPORTED_LANGS code) */
const DT_AI_LANGS = [
    'af' => 'Afrikaans',
    'en' => 'English',
    'zu' => 'isiZulu (Zulu)',
    'xh' => 'isiXhosa (Xhosa)',
    'pt' => 'Portuguese (as written in Mozambique and Portugal)',
    'st' => 'Sesotho (Southern Sotho)',
];

/** Church offices and forms of address, so translations use the app's own words */
const DT_GLOSSARY_KEYS = ['apostle', 'overseer', 'evangelist', 'prophet', 'elder', 'priest', 'subdeacon', 'helper', 'brother', 'sister'];

function dt_now(): DateTimeImmutable {
    static $tz = null;
    if ($tz === null) $tz = new DateTimeZone(DT_TZ);
    return new DateTimeImmutable('now', $tz);
}

/** A supported language code, or $fallback */
function dt_lang($lang, string $fallback = 'af'): string {
    return (is_string($lang) && in_array($lang, SUPPORTED_LANGS, true)) ? $lang : $fallback;
}

/**
 * Stored text → plain text with its line breaks. The admin is a textarea;
 * older thoughts may still carry the few tags it used to allow (<p>, <br>, <b> ...).
 */
function dt_plain(?string $s): string {
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

/** One line of plain text (the author) */
function dt_line(?string $s): string {
    $s = dt_plain($s);
    return trim(preg_replace('/\s+/u', ' ', $s) ?? $s);
}

/** Ties a translation to the exact text, author and language it was made from */
function dt_hash(string $lang, string $content, string $author): string {
    return hash('sha256', $lang . "\x1F" . $content . "\x1F" . $author);
}

// ===== STORE =====

/**
 * Create / upgrade the two tables (once per request). Never call it inside a
 * transaction: MySQL commits on every CREATE / ALTER.
 */
function dt_ensure_schema(PDO $pdo): void {
    static $done = false;
    if ($done) return;

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS daily_thoughts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            content TEXT NOT NULL,
            author VARCHAR(255) DEFAULT NULL,
            lang VARCHAR(5) NOT NULL DEFAULT 'af',
            source_hash CHAR(64) DEFAULT NULL,
            display_date DATE NOT NULL,
            display_time TIME DEFAULT NULL,
            notification_sent TINYINT(1) DEFAULT 0,
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_date (display_date),
            INDEX idx_display_date (display_date),
            INDEX idx_created_by (created_by)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Older tables: add what they are missing (in this order, for the AFTERs)
    $cols = $pdo->query('SHOW COLUMNS FROM daily_thoughts')->fetchAll(PDO::FETCH_COLUMN, 0);
    $add = [
        'display_time' => 'TIME DEFAULT NULL AFTER display_date',
        'notification_sent' => 'TINYINT(1) DEFAULT 0 AFTER display_time',
        'lang' => "VARCHAR(5) NOT NULL DEFAULT 'af' AFTER author",
        'source_hash' => 'CHAR(64) DEFAULT NULL AFTER lang',
    ];
    foreach ($add as $col => $definition) {
        if (!in_array($col, $cols, true)) {
            $pdo->exec("ALTER TABLE daily_thoughts ADD COLUMN $col $definition");
        }
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS daily_thought_translations (
            thought_id INT NOT NULL,
            lang VARCHAR(5) NOT NULL,
            content TEXT NOT NULL,
            author VARCHAR(255) DEFAULT NULL,
            source_hash CHAR(64) NOT NULL,
            is_manual TINYINT(1) NOT NULL DEFAULT 0,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (thought_id, lang)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $done = true;
}

/**
 * A prepared query. When a table or column it needs is not there yet (the
 * first request after an update), the schema is brought up to date and the
 * query runs once more. Not for use inside a transaction.
 */
function dt_run(PDO $pdo, string $sql, array $params = []): PDOStatement {
    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return $st;
    } catch (PDOException $e) {
        if (!in_array((string)$e->getCode(), ['42S02', '42S22'], true)) {
            throw $e;
        }
        dt_ensure_schema($pdo);
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return $st;
    }
}

/**
 * A thought with its current translations:
 * id, content, author (plain), lang, hash, display_date, display_time,
 * notification_sent, created_by, translations [lang => [content, author, manual]].
 * A thought from before translations existed gets its hash here.
 */
function dt_load(PDO $pdo, int $id): ?array {
    $row = dt_run($pdo, 'SELECT id, content, author, lang, source_hash, display_date, display_time, notification_sent, created_by
                         FROM daily_thoughts WHERE id = ? LIMIT 1', [$id])->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;

    $lang = dt_lang($row['lang'] ?? null);
    $hash = (string)($row['source_hash'] ?? '');
    if ($hash === '') {
        $hash = dt_hash($lang, (string)$row['content'], (string)($row['author'] ?? ''));
        dt_run($pdo, 'UPDATE daily_thoughts SET source_hash = ? WHERE id = ? AND source_hash IS NULL', [$hash, $id]);
    }

    $translations = [];
    $st = dt_run($pdo, 'SELECT lang, content, author, is_manual FROM daily_thought_translations
                        WHERE thought_id = ? AND source_hash = ? AND lang <> ?', [$id, $hash, $lang]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $tr) {
        if (!in_array($tr['lang'], SUPPORTED_LANGS, true)) continue;
        $translations[$tr['lang']] = [
            'content' => dt_plain($tr['content']),
            'author' => dt_line($tr['author'] ?? ''),
            'manual' => (int)$tr['is_manual'] === 1,
        ];
    }

    return [
        'id' => (int)$row['id'],
        'content' => dt_plain($row['content']),
        'author' => dt_line($row['author'] ?? ''),
        'lang' => $lang,
        'hash' => $hash,
        'display_date' => (string)$row['display_date'],
        'display_time' => $row['display_time'] !== null ? substr((string)$row['display_time'], 0, 5) : null,
        'notification_sent' => (int)$row['notification_sent'] === 1,
        'created_by' => (int)$row['created_by'],
        'translations' => $translations,
    ];
}

/** Languages a loaded thought still has no current translation for */
function dt_missing_langs(array $t): array {
    $out = [];
    foreach (SUPPORTED_LANGS as $code) {
        if ($code !== $t['lang'] && !isset($t['translations'][$code])) $out[] = $code;
    }
    return $out;
}

/** The thought as a member reading $lang sees it: [content, author, lang] */
function dt_text_for(array $t, string $lang): array {
    $tr = $t['translations'][$lang] ?? null;
    if ($lang === $t['lang'] || $tr === null || $tr['content'] === '') {
        return ['content' => $t['content'], 'author' => $t['author'], 'lang' => $t['lang']];
    }
    // A name is the same in every language: an empty translated author keeps the original
    return ['content' => $tr['content'], 'author' => $tr['author'] !== '' ? $tr['author'] : $t['author'], 'lang' => $lang];
}

/**
 * Store a translation, but only while the thought still has the text it was
 * made from: a translation that finishes after the elder changed the thought
 * is dropped instead of overwriting a newer one.
 */
function dt_store_translation(PDO $pdo, int $id, string $lang, string $content, string $author, string $hash, bool $manual): bool {
    $st = dt_run($pdo, '
        INSERT INTO daily_thought_translations (thought_id, lang, content, author, source_hash, is_manual)
        SELECT id, ?, ?, ?, ?, ? FROM daily_thoughts WHERE id = ? AND source_hash = ? AND lang <> ?
        ON DUPLICATE KEY UPDATE content = VALUES(content), author = VALUES(author),
                                source_hash = VALUES(source_hash), is_manual = VALUES(is_manual)
    ', [$lang, $content, $author !== '' ? $author : null, $hash, $manual ? 1 : 0, $id, $hash, $lang]);
    if ($st->rowCount() > 0) return true;

    // 0 rows: either the thought changed meanwhile, or this same text was already stored for it
    return dt_run($pdo, 'SELECT 1 FROM daily_thought_translations tr
                         JOIN daily_thoughts dt ON dt.id = tr.thought_id AND dt.source_hash = tr.source_hash
                         WHERE tr.thought_id = ? AND tr.lang = ? AND tr.source_hash = ?',
        [$id, $lang, $hash])->fetchColumn() !== false;
}

/**
 * The admin endpoints' gate: a signed-in elder (amp 1-5), or a JSON error in
 * the member's language. Needs security/auth.php and includes/admin_api.php.
 */
function dt_admin_guard(PDO $pdo): int {
    if (!auth_logged_in()) {
        admin_api_fail('adm_err_session', 401);
    }
    $userId = (int)auth_user_id();
    try {
        $st = $pdo->prepare('SELECT amp_id FROM users WHERE id = ? LIMIT 1');
        $st->execute([$userId]);
        $amp = (int)($st->fetchColumn() ?: 0);
    } catch (Throwable $e) {
        admin_api_server_error($e, 'gedagte guard');
    }
    if ($amp < 1 || $amp > 5) {
        admin_api_fail('adm_err_forbidden', 403);
    }
    return $userId;
}

/** A POST field as a string ('' for a missing field or an array) */
function dt_post(string $key): string {
    $v = $_POST[$key] ?? '';
    return is_string($v) ? $v : '';
}

// ===== AI: translate and proofread =====

/** True when an OpenAI key is configured (admin/config/secrets.php or OPENAI_API_KEY) */
function dt_ai_ready(): bool {
    require_once dirname(__DIR__) . '/admin/config/ai_config.php';
    return defined('OPENAI_API_KEY') && OPENAI_API_KEY !== '';
}

/** Seconds to wait for one AI answer: a long thought takes longer to write out */
function dt_ai_timeout(string $content): int {
    return min(120, 60 + intdiv(mb_strlen($content, 'UTF-8'), 100));
}

function dt_glossary(string $from, string $to): string {
    $lines = [];
    foreach (DT_GLOSSARY_KEYS as $key) {
        $a = __t($key, $from);
        $b = __t($key, $to);
        if ($a !== $key && $b !== $key) $lines[] = '- ' . $a . ' = ' . $b;
    }
    return implode("\n", $lines);
}

/** The AI's answer → the text it holds: JSON {"content": ...} first, else the bare text */
function dt_ai_field(?string $answer, string $field): ?string {
    if ($answer === null) return null;
    $answer = trim($answer);
    if (preg_match('/^```[a-z]*\s*(.*?)\s*```$/is', $answer, $m)) $answer = trim($m[1]);
    $data = json_decode($answer, true);
    if (!is_array($data) || !array_key_exists($field, $data) || !is_string($data[$field])) return null;
    return $data[$field];
}

/** Clean an AI text and drop quotation marks it wrapped around the whole of it */
function dt_ai_clean(string $text, string $source): string {
    $text = dt_plain($text);
    if (!preg_match('/^["“”„«‘]/u', $source) && preg_match('/^["“„«]\s*(.+?)\s*["”«»]$/us', $text, $m)) {
        $text = $m[1];
    }
    return trim($text);
}

/** Does a translation look complete (not empty, not cut short, not left in the source language)? */
function dt_ai_plausible(string $translation, string $source): bool {
    $t = mb_strlen($translation, 'UTF-8');
    $s = mb_strlen($source, 'UTF-8');
    if ($t === 0 || $t > DT_MAX_CHARS * 3) return false;
    if ($s >= 40 && ($t < $s * 0.4 || $t > $s * 3)) return false;
    if ($s < 40 && $t > $s * 3 + 60) return false;
    if ($s > 25 && mb_strtolower($translation, 'UTF-8') === mb_strtolower($source, 'UTF-8')) return false;
    return true;
}

/**
 * Translate a thought into each of $targets (one request per language, all at
 * once; failures get one more round). Returns [lang => [content, author]]
 * for the translations that came back good; the others are left out.
 */
function dt_ai_translate(string $content, string $author, string $from, array $targets): array {
    $targets = array_values(array_filter($targets, fn($l) => $l !== $from && isset(DT_AI_LANGS[$l])));
    if (!$targets || $content === '' || !dt_ai_ready()) return [];

    $fromName = DT_AI_LANGS[$from] ?? DT_AI_LANGS['af'];
    $input = json_encode(['content' => $content, 'author' => $author], JSON_UNESCAPED_UNICODE);
    $out = [];

    for ($round = 0; $round < 2 && $targets; $round++) {
        $requests = [];
        foreach ($targets as $lang) {
            $to = DT_AI_LANGS[$lang];
            $system = "You translate the \"Thought of the Day\" of the Old Apostolic Church (Die Ou Apostoliese Kerk) "
                . "from {$fromName} into {$to}. Members read it on their phones as a short devotional message.\n\n"
                . "Rules:\n"
                . "1. Translate everything faithfully: the full meaning, nothing added, nothing left out, no explanations or summaries.\n"
                . "2. Write natural, warm and reverent {$to}, the way a fluent {$to}-speaking church member would say it, not word for word.\n"
                . "3. Spelling, grammar and punctuation must be flawless {$to}.\n"
                . "4. Keep the same paragraphs and line breaks.\n"
                . "5. Bible references: use the usual {$to} name of the Bible book and keep chapter and verse numbers exactly. "
                . "For a quoted Bible verse, use the wording of the standard {$to} Bible where you know it.\n"
                . "6. Keep people's names as they are. Translate church offices and forms of address with these words:\n"
                . dt_glossary($from, $lang) . "\n"
                . "7. Follow the source's capitals for God, Jesus, the Lord and the Holy Spirit (and for pronouns that refer to God).\n"
                . "8. Plain text only: no HTML, no Markdown, no quotation marks around the text, no title.\n\n"
                . "The user message is JSON with \"content\" (the thought) and \"author\" (who wrote or said it; may be empty).\n"
                . "Answer with JSON only: {\"content\": \"...\", \"author\": \"...\"}. Translate \"author\" the same way "
                . "(names unchanged), or give \"\" when it is empty.";
            $requests[$lang] = [
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $input],
                ],
                'extra' => ['response_format' => ['type' => 'json_object']],
            ];
        }

        $answers = openai_chat_parallel($requests, dt_ai_timeout($content));
        $failed = [];
        foreach ($targets as $lang) {
            $text = dt_ai_field($answers[$lang] ?? null, 'content');
            $text = $text !== null ? dt_ai_clean($text, $content) : '';
            if (!dt_ai_plausible($text, $content)) {
                error_log('Daily thought: translation into ' . $lang . ' rejected (round ' . ($round + 1) . ')');
                $failed[] = $lang;
                continue;
            }
            $by = $author !== '' ? dt_line(dt_ai_field($answers[$lang], 'author') ?? '') : '';
            $out[$lang] = [
                'content' => $text,
                'author' => mb_substr($by, 0, DT_MAX_AUTHOR, 'UTF-8'),
            ];
        }
        $targets = $failed;
    }
    return $out;
}

/**
 * Correct spelling, grammar and punctuation of a thought in its own language,
 * without rewording it. Null when the AI could not be reached or answered
 * something that is clearly not a correction.
 */
function dt_ai_proofread(string $content, string $lang): ?string {
    if ($content === '' || !dt_ai_ready()) return null;
    $name = DT_AI_LANGS[$lang] ?? DT_AI_LANGS['af'];

    $system = "You proofread the \"Thought of the Day\" of the Old Apostolic Church (Die Ou Apostoliese Kerk), written in {$name}.\n"
        . "Correct only real mistakes: spelling, grammar, punctuation, capitals, missing or doubled words and typing errors.\n"
        . "Keep the writer's own words, style, tone and meaning. Do not rephrase sentences that are already correct, "
        . "do not add or remove sentences and do not translate.\n"
        . "Keep the paragraphs and line breaks. Keep Bible references and quoted Bible verses as they are.\n"
        . "Plain text only: no HTML, no Markdown, no quotation marks around the text.\n"
        . "The user message is JSON with \"content\". Answer with JSON only: {\"content\": \"...\"}, "
        . "the text unchanged when it has no mistakes.";

    $answers = openai_chat_parallel(['proofread' => [
        'messages' => [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => json_encode(['content' => $content], JSON_UNESCAPED_UNICODE)],
        ],
        'extra' => ['response_format' => ['type' => 'json_object']],
    ]], dt_ai_timeout($content));

    $text = dt_ai_field($answers['proofread'] ?? null, 'content');
    if ($text === null) return null;
    $text = dt_ai_clean($text, $content);

    // A correction stays close to the original in length; anything else is a rewrite
    $a = mb_strlen($text, 'UTF-8');
    $b = mb_strlen($content, 'UTF-8');
    if ($a === 0 || $a > DT_MAX_CHARS || abs($a - $b) > max(15, $b * 0.3)) {
        error_log('Daily thought: proofread answer rejected');
        return null;
    }
    return $text;
}

/**
 * Translate every language a thought is still missing and store the results.
 * @return array{done: string[], missing: string[], ai: bool}
 */
function dt_translate_missing(PDO $pdo, int $id): array {
    $t = dt_load($pdo, $id);
    if (!$t) return ['done' => [], 'missing' => [], 'ai' => dt_ai_ready()];

    $missing = dt_missing_langs($t);
    if (!$missing || !dt_ai_ready()) return ['done' => [], 'missing' => $missing, 'ai' => dt_ai_ready()];

    $done = [];
    foreach (dt_ai_translate($t['content'], $t['author'], $t['lang'], $missing) as $lang => $tr) {
        if (dt_store_translation($pdo, $id, $lang, $tr['content'], $tr['author'], $t['hash'], false)) {
            $done[] = $lang;
        }
    }
    return ['done' => $done, 'missing' => array_values(array_diff($missing, $done)), 'ai' => true];
}

// ===== SENDING =====

/** Cut text to $maxBytes of UTF-8 at a word, ending in "…" (unchanged when it fits) */
function dt_fit(string $text, int $maxBytes): string {
    if (strlen($text) <= $maxBytes) return $text;
    $cut = mb_strcut($text, 0, max(1, $maxBytes - strlen('…')), 'UTF-8');
    // End at a word, unless that would throw away too much
    if (preg_match('/^(.*\S)\s/us', $cut, $m) && mb_strlen($m[1], 'UTF-8') >= mb_strlen($cut, 'UTF-8') * 0.7) {
        $cut = $m[1];
    }
    return rtrim($cut, " \t\n,;:-–—") . '…';
}

/** The thought as one message: text, then "— author" on its own line */
function dt_message(string $content, string $author): string {
    return $content . ($author !== '' ? "\n— " . $author : '');
}

/** The push body: the whole thought, fitted into the FCM budget (the author line always stays) */
function dt_push_body(string $content, string $author): string {
    $tail = $author !== '' ? "\n— " . $author : '';
    return dt_fit($content, max(400, DT_PUSH_BYTES - strlen($tail))) . $tail;
}

/**
 * Notify everyone a thought is for — the approved members in the creator's
 * town (everyone when the creator has no town) — each in their own language:
 * an in-app notification plus an FCM push to each of their phones.
 * The caller must have claimed the thought first (notification_sent 0 → 1),
 * so it is never sent twice. Returns the number of members.
 * $retryTranslations: first try the languages still missing (the save that
 * has just tried them passes false).
 */
function dt_send(PDO $pdo, int $id, bool $retryTranslations = true): int {
    require_once dirname(__DIR__) . '/admin/config/fcm_config.php';
    require_once dirname(__DIR__) . '/notifications/lib/notifications.php';

    // Translations that failed when the thought was saved get one more try
    if ($retryTranslations) {
        try {
            dt_translate_missing($pdo, $id);
        } catch (Throwable $e) {
            error_log('Daily thought #' . $id . ' translate before send: ' . $e->getMessage());
        }
    }

    $t = dt_load($pdo, $id);
    if (!$t) return 0;

    $townId = (int)(dt_run($pdo, 'SELECT town_id FROM users WHERE id = ? LIMIT 1', [$t['created_by']])->fetchColumn() ?: 0);
    $scope = $townId > 0 ? ' AND u.town_id = ?' : '';
    $scopeParams = $townId > 0 ? [$townId] : [];

    // Members by language
    $groups = [];
    $st = dt_run($pdo, "SELECT u.id, u.language FROM users u WHERE u.status = 'approved'" . $scope, $scopeParams);
    $langOf = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $u) {
        $lang = dt_lang($u['language'] ?? null);
        $langOf[(int)$u['id']] = $lang;
        $groups[$lang]['users'][] = (int)$u['id'];
        $groups[$lang]['tokens'] = $groups[$lang]['tokens'] ?? [];
    }
    if (!$groups) return 0;

    // Their phones (a missing token table only costs the push, not the in-app notification)
    $tokenUser = []; // token => member, so each phone's push opens that member's own notification
    try {
        $st = dt_run($pdo, "SELECT f.user_id, f.token FROM fcm_tokens f JOIN users u ON u.id = f.user_id
                            WHERE u.status = 'approved'" . $scope, $scopeParams);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $lang = $langOf[(int)$row['user_id']] ?? null;
            if ($lang !== null && (string)$row['token'] !== '') {
                $groups[$lang]['tokens'][] = (string)$row['token'];
                $tokenUser[(string)$row['token']] = (int)$row['user_id'];
            }
        }
    } catch (Throwable $e) {
        error_log('Daily thought #' . $id . ' tokens: ' . $e->getMessage());
    }

    // In-app notifications first (one transaction per language: fast on SQLite)
    $messages = [];
    $notificationIds = []; // member => their notification's id (tapping the push takes it down)
    try {
        $db = nt_db();
        $insert = $db->prepare('INSERT INTO notifications (user_id, title, message, type, link, icon, title_key, message_key, params)
                                VALUES (?, ?, ?, ?, ?, ?, ?, NULL, NULL)');
        foreach ($groups as $lang => $group) {
            $text = dt_text_for($t, $lang);
            $title = __t('notif_thought_title', $lang);
            $messages[$lang] = ['title' => $title, 'text' => $text];
            $db->beginTransaction();
            $groupIds = [];
            foreach ($group['users'] as $uid) {
                try {
                    $insert->execute([$uid, $title, dt_message($text['content'], $text['author']), 'thought', DT_LINK, '💭', 'notif_thought_title']);
                    $groupIds[$uid] = (int)$db->lastInsertId();
                } catch (Throwable $e) {
                    error_log('Daily thought #' . $id . ' notification for user ' . $uid . ': ' . $e->getMessage());
                }
            }
            $db->commit();
            $notificationIds += $groupIds; // only ids that were really stored
        }
    } catch (Throwable $e) {
        if (isset($db) && $db->inTransaction()) $db->rollBack();
        error_log('Daily thought #' . $id . ' in-app notifications: ' . $e->getMessage());
    }

    // Then the pushes: one parallel fan-out per language
    $members = 0;
    foreach ($groups as $lang => $group) {
        $members += count($group['users']);
        if (!$group['tokens']) continue;
        $title = $messages[$lang]['title'] ?? __t('notif_thought_title', $lang);
        $text = $messages[$lang]['text'] ?? dt_text_for($t, $lang);
        try {
            $perToken = [];
            foreach ($group['tokens'] as $token) {
                $uid = $tokenUser[$token] ?? 0;
                if (isset($notificationIds[$uid])) $perToken[$token] = fcmOpenData($uid, $notificationIds[$uid], DT_LINK);
            }
            $res = sendFcmNotification($group['tokens'], $title, dt_push_body($text['content'], $text['author']), [
                'type' => 'thought',
                'link' => DT_LINK,
                'titleKey' => 'notif_thought_title',
            ], $perToken);
            if (empty($res['success'])) {
                error_log('Daily thought #' . $id . ' push (' . $lang . '): ' . ($res['error'] ?? 'no phone accepted it'));
            }
        } catch (Throwable $e) {
            error_log('Daily thought #' . $id . ' push (' . $lang . '): ' . $e->getMessage());
        }
    }

    error_log('Daily thought #' . $id . ' sent to ' . $members . ' members (' . implode(', ', array_keys($groups)) . ')');
    return $members;
}

/**
 * Today's thought that is due (its time has come) and not sent yet: claim it
 * and send it. Shared by the auto-cron and the standalone cron.
 * Returns ['id' => thought id, 'members' => how many] when one was sent, else null.
 */
function dt_send_due(PDO $pdo): ?array {
    $now = dt_now();
    $id = dt_run($pdo, '
        SELECT id FROM daily_thoughts
        WHERE display_date = ? AND notification_sent = 0
          AND (display_time IS NULL OR display_time <= ?)
        LIMIT 1
    ', [$now->format('Y-m-d'), $now->format('H:i:s')])->fetchColumn();
    if ($id === false) return null;

    // Claim it atomically: a concurrent sender (the other cron, a save) can never send it twice
    $claim = dt_run($pdo, 'UPDATE daily_thoughts SET notification_sent = 1 WHERE id = ? AND notification_sent = 0', [(int)$id]);
    if ($claim->rowCount() === 0) return null;

    return ['id' => (int)$id, 'members' => dt_send($pdo, (int)$id)];
}
