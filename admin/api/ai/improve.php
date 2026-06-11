<?php
declare(strict_types=1);

// Load dependencies
require_once dirname(__DIR__, 3) . '/security/config.php';
require_once dirname(__DIR__, 3) . '/security/session.php';
require_once dirname(__DIR__, 3) . '/security/auth.php';
require_once dirname(__DIR__, 3) . '/includes/languages.php';
require_once dirname(__DIR__, 2) . '/config/ai_config.php';

// Allow enough time for the AI call
set_time_limit(180);

// Headers
ob_start();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Auth check
if (!auth_logged_in()) {
    ob_end_clean();
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Method guard
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Only elders (amp_id 1-5) may use the AI endpoints
if (!ai_user_is_elder($pdo, auth_user_id())) {
    ob_end_clean();
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Insufficient permissions']);
    exit;
}

// Check API key is configured
if (!defined('OPENAI_API_KEY') || OPENAI_API_KEY === '') {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'OpenAI API key not configured. Please add your API key to admin/config/secrets.php']);
    exit;
}

// Get inputs
$content = trim((string)($_POST['content'] ?? ''));
$lang = trim((string)($_POST['lang'] ?? 'af'));
if (!in_array($lang, SUPPORTED_LANGS, true)) {
    $lang = 'af';
}

if ($content === '') {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No content provided']);
    exit;
}

// Full language names for the prompt
$promptLangNames = [
    'af' => 'Afrikaans',
    'en' => 'English',
    'zu' => 'isiZulu (Zulu)',
    'xh' => 'isiXhosa (Xhosa)',
    'pt' => 'Portuguese',
    'st' => 'Sesotho (Southern Sotho)'
];
$langName = $promptLangNames[$lang];

// Build prompt - works for every supported language
$systemPrompt = "You are a professional {$langName} editor for religious content. " .
    "The text below is written in {$langName}. " .
    "Improve ONLY grammar, spelling, punctuation, and verb tenses. " .
    "Keep the text in {$langName} - do NOT translate it to another language. " .
    "Preserve the exact HTML structure, tags and class attributes. " .
    "Do not add, remove, or reorder content. " .
    "Do not change Bible verse references or quoted Bible verse text. " .
    "Respond with ONLY the improved HTML - no explanations, no markdown fences.";

$messages = [
    ['role' => 'system', 'content' => $systemPrompt],
    ['role' => 'user', 'content' => $content]
];

try {
    $data = openai_chat($messages);
    $improved = ai_clean_html_output($data['choices'][0]['message']['content'] ?? '');

    if ($improved === '') {
        throw new Exception('Empty AI response');
    }

    ob_end_clean();
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'improved' => $improved,
        'lang' => $lang
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'AI call failed',
        'detail' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

exit;
