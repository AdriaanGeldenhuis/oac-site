<?php
/**
 * AI Configuration for Teaching Editor
 * =====================================
 */
declare(strict_types=1);

// =============================================================================
// LOAD SECRETS
// =============================================================================

$secretsFile = __DIR__ . '/secrets.php';
if (file_exists($secretsFile)) {
    require_once $secretsFile;
} else {
    $envKey = getenv('OPENAI_API_KEY');
    if ($envKey) {
        define('OPENAI_API_KEY', $envKey);
    } else {
        define('OPENAI_API_KEY', '');
    }
}

// =============================================================================
// OPENAI API CONFIGURATION
// =============================================================================

define('OPENAI_API_URL', 'https://api.openai.com/v1/chat/completions');

// gpt-5.4-mini: newer and better than gpt-4o for translation, and roughly
// 3x cheaper ($0.75/$4.50 per 1M tokens vs $2.50/$10.00 for gpt-4o)
define('OPENAI_MODEL', 'gpt-5.4-mini');

// Generous output budget so long teachings are never cut off mid-translation
// (only tokens actually generated are billed)
define('OPENAI_MAX_TOKENS', 16000);

// Temperature (lower = more accurate) - only used by legacy models;
// gpt-5 family models do not accept a temperature parameter
define('OPENAI_TEMPERATURE', 0.2);

// Reasoning effort for gpt-5 family models. 'none' = fast and cheap,
// which is right for translation/editing (no reasoning tokens billed)
define('OPENAI_REASONING_EFFORT', 'none');

// =============================================================================
// BIBLE CONFIGURATION
// =============================================================================

define('BIBLE_DIR', dirname(__DIR__, 2) . '/bible/bibles/');

define('BIBLE_VERSIONS', [
    'af' => 'af_1933_53.json',
    'en' => 'en_kjv1611.json',
    'zu' => 'zu_dummy.json',
    'xh' => 'xh_dummy.json',
    'pt' => 'pt_dummy.json',
    'st' => 'st_dummy.json'
]);

// =============================================================================
// OPENAI CHAT FUNCTION (NON-STREAMING - MORE RELIABLE)
// =============================================================================

/**
 * The request body for a chat call. $extra adds fields such as
 * response_format.
 */
function openai_body(array $messages, array $extra = []): array {
    $body = [
        'model' => OPENAI_MODEL,
        'messages' => $messages
    ];

    // gpt-5 family / o-series are reasoning models: they require
    // max_completion_tokens, reject temperature, and accept reasoning_effort.
    // Legacy models (gpt-4o, gpt-4.1, ...) use max_tokens + temperature.
    if (preg_match('/^(gpt-5|o\d)/', OPENAI_MODEL)) {
        $body['max_completion_tokens'] = OPENAI_MAX_TOKENS;
        if (defined('OPENAI_REASONING_EFFORT') && OPENAI_REASONING_EFFORT !== '') {
            $body['reasoning_effort'] = OPENAI_REASONING_EFFORT;
        }
    } else {
        $body['max_tokens'] = OPENAI_MAX_TOKENS;
        $body['temperature'] = OPENAI_TEMPERATURE;
    }

    return array_merge($body, $extra);
}

function openai_chat(array $messages): array {
    error_log('openai_chat: Starting API call with ' . OPENAI_MODEL);

    $body = openai_body($messages);

    $payload = json_encode($body, JSON_UNESCAPED_UNICODE);

    error_log('openai_chat: Payload size: ' . strlen($payload) . ' bytes');

    $ch = curl_init(OPENAI_API_URL);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . OPENAI_API_KEY
        ],
        CURLOPT_TIMEOUT => 180,         // 3 minutes per language
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_ENCODING => ''
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    $totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
    curl_close($ch);

    error_log('openai_chat: HTTP ' . $httpCode . ', Time: ' . round($totalTime, 2) . 's');

    if ($curlError) {
        error_log('openai_chat: cURL ERROR: ' . $curlError);
        throw new Exception('Connection error: ' . $curlError);
    }

    $data = json_decode($response, true);

    if ($httpCode !== 200) {
        $errorMsg = $data['error']['message'] ?? 'Unknown error';
        error_log('openai_chat: API ERROR: ' . $errorMsg);
        throw new Exception('API error: ' . $errorMsg);
    }

    if (!isset($data['choices'][0]['message']['content'])) {
        error_log('openai_chat: Invalid response: ' . substr($response, 0, 500));
        throw new Exception('Invalid API response');
    }

    if (($data['choices'][0]['finish_reason'] ?? '') === 'length') {
        error_log('openai_chat: WARNING - output hit the token limit and may be truncated');
    }

    error_log('openai_chat: Success! Response length: ' . strlen($data['choices'][0]['message']['content']));
    return $data;
}

/**
 * Several chat calls at the same time (e.g. one translation per language),
 * so the wait is the slowest call instead of all of them added up.
 *
 * @param array $requests key => ['messages' => [...], 'extra' => [...]]
 * @return array key => the answer text, or null for a call that failed
 *               (error, HTTP status other than 200, or cut off at the token limit)
 */
function openai_chat_parallel(array $requests, int $timeout = 60): array {
    $out = [];
    if (!$requests) {
        return $out;
    }

    $multi = curl_multi_init();
    $handles = [];
    foreach ($requests as $key => $req) {
        $payload = json_encode(openai_body($req['messages'] ?? [], $req['extra'] ?? []), JSON_UNESCAPED_UNICODE);
        $ch = curl_init(OPENAI_API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . OPENAI_API_KEY
            ],
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_ENCODING => ''
        ]);
        curl_multi_add_handle($multi, $ch);
        $handles[$key] = $ch;
    }

    do {
        $status = curl_multi_exec($multi, $active);
        if ($active && curl_multi_select($multi, 1.0) === -1) {
            usleep(10000); // select not supported: avoid a busy loop
        }
    } while ($active && $status === CURLM_OK);

    foreach ($handles as $key => $ch) {
        $response = (string)curl_multi_getcontent($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_multi_remove_handle($multi, $ch);
        curl_close($ch);

        $out[$key] = null;
        $data = json_decode($response, true);
        if ($httpCode !== 200) {
            error_log('openai_chat_parallel [' . $key . ']: HTTP ' . $httpCode . ' '
                . ($curlError !== '' ? $curlError : (string)($data['error']['message'] ?? '')));
            continue;
        }
        $text = $data['choices'][0]['message']['content'] ?? null;
        if (!is_string($text) || ($data['choices'][0]['finish_reason'] ?? '') === 'length') {
            error_log('openai_chat_parallel [' . $key . ']: empty or cut-off answer');
            continue;
        }
        $out[$key] = $text;
    }
    curl_multi_close($multi);

    return $out;
}

// =============================================================================
// HELPERS
// =============================================================================

/**
 * Check whether a user holds an Elder-or-higher office (amp_id 1-5).
 * Used to gate the AI endpoints so only elders can spend API credits.
 */
function ai_user_is_elder(PDO $pdo, ?int $userId): bool {
    if (!$userId) {
        return false;
    }
    try {
        $stmt = $pdo->prepare('SELECT amp_id FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $ampId = (int)($row['amp_id'] ?? 0);
        return $ampId >= 1 && $ampId <= 5;
    } catch (Throwable $e) {
        error_log('ai_user_is_elder: ' . $e->getMessage());
        return false;
    }
}

// Biggest teaching the AI endpoints accept (a long teaching is ~30 KB)
define('AI_MAX_CONTENT_BYTES', 120000);

/**
 * A POST field as a trimmed string ('' for arrays like content[]=x)
 */
function ai_post_str(string $key, string $default = ''): string {
    $v = $_POST[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

/**
 * Size cap + per-user rate limit for the AI endpoints (they spend API credit).
 * @return array{0:int,1:string}|null  [http status, error] or null when fine
 */
function ai_limit_error(int $userId, string $content): ?array {
    if (strlen($content) > AI_MAX_CONTENT_BYTES) {
        return [413, 'Content too large for the AI (max ' . (int)(AI_MAX_CONTENT_BYTES / 1000) . ' KB)'];
    }
    require_once dirname(__DIR__, 2) . '/security/rate_limit.php';
    // translate-all = 5 calls, improve = 1: 150 an hour is plenty for real use
    if (function_exists('rate_limit_check') && !rate_limit_check('ai:' . $userId, 150, 15)) {
        return [429, 'Too many AI requests - try again in 15 minutes'];
    }
    return null;
}

/**
 * Strip markdown code fences (```html ... ```) that models sometimes
 * wrap around HTML output.
 */
function ai_clean_html_output(string $html): string {
    $html = trim($html);
    if (preg_match('/^```[a-z]*\s*(.*?)\s*```$/is', $html, $m)) {
        $html = $m[1];
    }
    return trim($html);
}

// =============================================================================
// BIBLE DATA LOADER
// =============================================================================

function loadBibleData(string $lang): ?array {
    $file = BIBLE_VERSIONS[$lang] ?? BIBLE_VERSIONS['en'] ?? null;
    if (!$file) return null;

    $filePath = BIBLE_DIR . $file;
    if (!file_exists($filePath)) {
        error_log('Bible file not found: ' . $filePath);
        return null;
    }

    $json = file_get_contents($filePath);
    if ($json === false) return null;

    $data = json_decode($json, true);
    if (!is_array($data) || empty($data)) return null;

    foreach ($data as $book => &$chapters) {
        foreach ($chapters as $chapter => &$items) {
            $verseNum = 0;
            foreach ($items as &$item) {
                if (isset($item['v']) && !isset($item['h'])) {
                    $verseNum++;
                    $item['n'] = $verseNum;
                }
            }
        }
    }

    return $data;
}
