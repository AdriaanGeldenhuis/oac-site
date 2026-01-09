<?php
/**
 * AI Configuration for Teaching Editor
 * =====================================
 * Uses STREAMING to avoid timeout issues
 */
declare(strict_types=1);

// =============================================================================
// LOAD SECRETS (API key stored separately, not in git)
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

// GPT-4o - Stronger model, better translations
define('OPENAI_MODEL', 'gpt-4o');

// Maximum tokens for AI responses
define('OPENAI_MAX_TOKENS', 16000);

// Temperature (lower = more accurate translations)
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
    'pt' => 'pt_dummy.json'
]);

// =============================================================================
// OPENAI STREAMING CHAT FUNCTION
// =============================================================================

/**
 * Make a STREAMING chat completion request to OpenAI
 * Streaming prevents timeout by receiving data chunks as they're generated
 *
 * @param array $messages Array of message objects with 'role' and 'content'
 * @return array The API response data (assembled from stream)
 * @throws Exception on failure
 */
function openai_chat(array $messages): array {
    error_log('openai_chat: Starting STREAMING API call with model ' . OPENAI_MODEL);

    $payload = json_encode([
        'model' => OPENAI_MODEL,
        'messages' => $messages,
        'max_tokens' => OPENAI_MAX_TOKENS,
        'temperature' => OPENAI_TEMPERATURE,
        'stream' => true  // STREAMING ENABLED
    ], JSON_UNESCAPED_UNICODE);

    error_log('openai_chat: Payload size: ' . strlen($payload) . ' bytes');

    $ch = curl_init(OPENAI_API_URL);

    // Collect streamed content
    $fullContent = '';
    $finishReason = 'unknown';
    $streamError = null;

    // Callback function to process streaming data
    $writeCallback = function($ch, $data) use (&$fullContent, &$finishReason, &$streamError) {
        $lines = explode("\n", $data);

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip empty lines
            if (empty($line)) continue;

            // Check for data prefix
            if (strpos($line, 'data: ') !== 0) continue;

            $jsonStr = substr($line, 6); // Remove "data: " prefix

            // Check for stream end
            if ($jsonStr === '[DONE]') {
                continue;
            }

            $json = json_decode($jsonStr, true);
            if (!$json) continue;

            // Check for error in stream
            if (isset($json['error'])) {
                $streamError = $json['error']['message'] ?? 'Unknown stream error';
                continue;
            }

            // Extract content delta
            if (isset($json['choices'][0]['delta']['content'])) {
                $fullContent .= $json['choices'][0]['delta']['content'];
            }

            // Check finish reason
            if (isset($json['choices'][0]['finish_reason']) && $json['choices'][0]['finish_reason']) {
                $finishReason = $json['choices'][0]['finish_reason'];
            }
        }

        return strlen($data); // Must return length for cURL
    };

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . OPENAI_API_KEY,
            'Accept: text/event-stream'
        ],
        CURLOPT_WRITEFUNCTION => $writeCallback,
        CURLOPT_TIMEOUT => 600,        // 10 minutes total (streaming keeps alive)
        CURLOPT_CONNECTTIMEOUT => 30,  // 30 seconds to connect
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_RETURNTRANSFER => false // Using write callback instead
    ]);

    error_log('openai_chat: Connecting with STREAMING...');

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    $curlErrno = curl_errno($ch);
    $totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
    curl_close($ch);

    error_log('openai_chat: Stream complete. HTTP: ' . $httpCode . ', Time: ' . round($totalTime, 2) . 's');
    error_log('openai_chat: Content length: ' . strlen($fullContent) . ' chars');

    if ($curlError) {
        error_log('openai_chat: cURL ERROR: ' . $curlError);
        throw new Exception('Connection error: ' . $curlError);
    }

    if ($streamError) {
        error_log('openai_chat: Stream ERROR: ' . $streamError);
        throw new Exception('API error: ' . $streamError);
    }

    if ($httpCode !== 200) {
        error_log('openai_chat: HTTP ERROR: ' . $httpCode);
        throw new Exception('API error (HTTP ' . $httpCode . ')');
    }

    if (empty($fullContent)) {
        error_log('openai_chat: Empty response!');
        throw new Exception('Empty response from API');
    }

    if ($finishReason === 'length') {
        error_log('openai_chat: WARNING - Response was truncated!');
    }

    error_log('openai_chat: Success! finish_reason: ' . $finishReason);

    // Return in same format as non-streaming for compatibility
    return [
        'choices' => [
            [
                'message' => ['content' => $fullContent],
                'finish_reason' => $finishReason
            ]
        ]
    ];
}

// =============================================================================
// BIBLE DATA LOADER
// =============================================================================

/**
 * Load Bible data for a specific language
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
