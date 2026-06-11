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

// GPT-4o - Better translations
define('OPENAI_MODEL', 'gpt-4o');

// Max tokens - reduced to speed up response
define('OPENAI_MAX_TOKENS', 8000);

// Temperature (lower = more accurate)
define('OPENAI_TEMPERATURE', 0.2);

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

function openai_chat(array $messages): array {
    error_log('openai_chat: Starting API call with ' . OPENAI_MODEL);

    $payload = json_encode([
        'model' => OPENAI_MODEL,
        'messages' => $messages,
        'max_tokens' => OPENAI_MAX_TOKENS,
        'temperature' => OPENAI_TEMPERATURE
    ], JSON_UNESCAPED_UNICODE);

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

    error_log('openai_chat: Success! Response length: ' . strlen($data['choices'][0]['message']['content']));
    return $data;
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
