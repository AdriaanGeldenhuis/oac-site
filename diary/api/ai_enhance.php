<?php
declare(strict_types=1);
/**
 * POST /diary/api/ai_enhance.php   (JSON body {text}, X-CSRF-Token header)
 * → {success, text}: a suggestion only. Nothing is saved here; the writer
 * chooses whether to use it.
 *
 * Settings live in diary/config.php on the server (not in git):
 * DIARY_OPENAI_API_KEY, DIARY_AI_ENHANCE_PROMPT_AF / _EN, DIARY_AI_MODEL,
 * DIARY_AI_MAX_TOKENS, DIARY_AI_TEMPERATURE, DIARY_AI_TIMEOUT.
 */

require_once dirname(__DIR__, 2) . '/security/auth_gate.php';
require_once dirname(__DIR__) . '/lib/diary.php';

const DY_AI_MAX_CHARS = 6000;

dy_require_post();

$userId = dy_user_id();
if ($userId <= 0) {
    dy_fail('session', 401);
}

$text = dy_clean_text(dy_str(dy_input()['text'] ?? ''));
if ($text === '') {
    dy_fail('empty_entry');
}
if (dy_len($text) > DY_AI_MAX_CHARS) {
    dy_fail('ai_too_long');
}

$configFile = dirname(__DIR__) . '/config.php';
if (is_file($configFile)) {
    require_once $configFile;
}
$apiKey = defined('DIARY_OPENAI_API_KEY') ? (string)DIARY_OPENAI_API_KEY : '';
if ($apiKey === '' || !function_exists('curl_init')) {
    dy_fail('ai_unavailable', 503);
}

// Every call costs money: 20 suggestions an hour per person
if (!dy_rate_ok('diary_ai_' . $userId, 20)) {
    dy_fail('rate_limited', 429);
}

$lang = dy_lang();
$systemPrompt = $lang === 'af'
    ? (defined('DIARY_AI_ENHANCE_PROMPT_AF') ? DIARY_AI_ENHANCE_PROMPT_AF : 'Verbeter die teks.')
    : (defined('DIARY_AI_ENHANCE_PROMPT_EN') ? DIARY_AI_ENHANCE_PROMPT_EN : 'Enhance the text.');
// The suggestion replaces the text as it is, in any of the app's six languages
$systemPrompt .= "\n\nReply with the improved text only, in the same language as the text you receive.";

$model = defined('DIARY_AI_MODEL') ? DIARY_AI_MODEL : 'gpt-4o-mini';
$maxTokens = defined('DIARY_AI_MAX_TOKENS') ? (int)DIARY_AI_MAX_TOKENS : 1000;
$temperature = defined('DIARY_AI_TEMPERATURE') ? (float)DIARY_AI_TEMPERATURE : 0.7;
$timeout = defined('DIARY_AI_TIMEOUT') ? (int)DIARY_AI_TIMEOUT : 30;

// Don't hold the session lock while waiting for the AI
session_write_close();

try {
    $payload = json_encode([
        'model' => $model,
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $text],
        ],
        'temperature' => $temperature,
        'max_tokens' => $maxTokens,
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        throw new RuntimeException('cURL error: ' . $curlError);
    }
    $result = json_decode((string)$response, true);
    if ($httpCode !== 200) {
        throw new RuntimeException('OpenAI HTTP ' . $httpCode . ': ' . ($result['error']['message'] ?? 'unknown'));
    }

    $choice = $result['choices'][0] ?? null;
    $suggestion = dy_clean_text((string)($choice['message']['content'] ?? ''));
    if ($suggestion === '') {
        throw new RuntimeException('Empty OpenAI answer');
    }
    // A cut-off answer would silently drop the end of the entry
    if (($choice['finish_reason'] ?? '') === 'length') {
        dy_fail('ai_too_long');
    }

    error_log(sprintf(
        'Diary AI: user=%d model=%s tokens=%d',
        $userId,
        $model,
        (int)($result['usage']['total_tokens'] ?? 0)
    ));

    dy_json(['success' => true, 'text' => $suggestion]);
} catch (Throwable $e) {
    error_log('Diary AI error: ' . $e->getMessage());
    dy_fail('ai_failed', 502);
}
