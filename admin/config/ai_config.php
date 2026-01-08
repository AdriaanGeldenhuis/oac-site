<?php
/**
 * AI Configuration for Teaching Editor
 * =====================================
 */
declare(strict_types=1);

// =============================================================================
// LOAD SECRETS (API key stored separately, not in git)
// =============================================================================

// Load API key from secrets file (excluded from version control)
$secretsFile = __DIR__ . '/secrets.php';
if (file_exists($secretsFile)) {
    require_once $secretsFile;
} else {
    // Fallback: check environment variable
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

// OpenAI API endpoint
define('OPENAI_API_URL', 'https://api.openai.com/v1/chat/completions');

// Model to use
define('OPENAI_MODEL', 'gpt-4o-mini');

// Maximum tokens for AI responses (increased for long translations)
define('OPENAI_MAX_TOKENS', 8000);

// Temperature (0.0-2.0, lower = more focused)
define('OPENAI_TEMPERATURE', 0.3);

// =============================================================================
// BIBLE CONFIGURATION
// =============================================================================

define('BIBLE_DIR', dirname(__DIR__, 2) . '/bible/bibles/');

// Bible file mapping per language
define('BIBLE_VERSIONS', [
    'af' => 'af_1933_53.json',
    'en' => 'en_kjv1611.json',
    'zu' => 'zu_dummy.json',
    'xh' => 'xh_dummy.json',
    'pt' => 'pt_dummy.json'
]);

// =============================================================================
// OPENAI CHAT FUNCTION
// =============================================================================

/**
 * Make a chat completion request to OpenAI
 *
 * @param array $messages Array of message objects with 'role' and 'content'
 * @return array The API response data
 * @throws Exception on failure
 */
function openai_chat(array $messages): array {
    $ch = curl_init(OPENAI_API_URL);

    $payload = json_encode([
        'model' => OPENAI_MODEL,
        'messages' => $messages,
        'max_tokens' => OPENAI_MAX_TOKENS,
        'temperature' => OPENAI_TEMPERATURE
    ], JSON_UNESCAPED_UNICODE);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . OPENAI_API_KEY
        ],
        CURLOPT_TIMEOUT => 180,  // 3 minutes for long translations
        CURLOPT_CONNECTTIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        throw new Exception('cURL error: ' . $curlError);
    }

    $data = json_decode($response, true);

    if ($httpCode !== 200) {
        $errorMsg = $data['error']['message'] ?? 'Unknown API error';
        throw new Exception('API error (' . $httpCode . '): ' . $errorMsg);
    }

    if (!isset($data['choices'][0]['message']['content'])) {
        throw new Exception('Invalid API response structure');
    }

    return $data;
}

// =============================================================================
// BIBLE DATA LOADER
// =============================================================================

/**
 * Load Bible data for a specific language
 * Adds verse numbers (n) to each verse for easy lookup
 *
 * @param string $lang Language code (af, en, zu, xh, pt)
 * @return array|null Bible data or null if not available
 */
function loadBibleData(string $lang): ?array {
    $file = BIBLE_VERSIONS[$lang] ?? BIBLE_VERSIONS['en'] ?? null;

    if (!$file) {
        return null;
    }

    $filePath = BIBLE_DIR . $file;

    if (!file_exists($filePath)) {
        error_log('Bible file not found: ' . $filePath);
        return null;
    }

    $json = file_get_contents($filePath);
    if ($json === false) {
        error_log('Failed to read Bible file: ' . $filePath);
        return null;
    }

    $data = json_decode($json, true);
    if (!is_array($data) || empty($data)) {
        error_log('Invalid or empty Bible data: ' . $filePath);
        return null;
    }

    // Add verse numbers to the data structure
    // The Bible JSON has 'v' for verse text and 'h' for headers
    // We need to add 'n' (verse number) based on position
    foreach ($data as $book => &$chapters) {
        foreach ($chapters as $chapter => &$items) {
            $verseNum = 0;
            foreach ($items as &$item) {
                // Skip headers, only number verses
                if (isset($item['v']) && !isset($item['h'])) {
                    $verseNum++;
                    $item['n'] = $verseNum;
                }
            }
        }
    }

    return $data;
}
