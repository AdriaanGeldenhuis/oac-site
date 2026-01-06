<?php
declare(strict_types=1);

// Load dependencies
require_once dirname(__DIR__, 3) . '/security/config.php';
require_once dirname(__DIR__, 3) . '/security/session.php';
require_once dirname(__DIR__, 3) . '/security/auth.php';
require_once dirname(__DIR__, 2) . '/config/ai_config.php';

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

// Get inputs
$content = trim((string)($_POST['content'] ?? ''));
$lang = trim((string)($_POST['lang'] ?? 'af'));
$lang = $lang === 'en' ? 'en' : 'af';

if ($content === '') {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No content provided']);
    exit;
}

// Build prompt
$systemPrompt = $lang === 'af'
    ? "Jy is 'n professionele Afrikaanse redakteur. Verbeter SLEGS grammatika, spelling, leestekens en werkwoordtye. Behou die presiese HTML-struktuur en tags. Moenie inhoud byvoeg, verwyder of herrangskik nie. Antwoord met NET die verbeterde HTML."
    : "You are a professional English editor. Improve ONLY grammar, spelling, punctuation, and tenses. Preserve the exact HTML structure and tags. Do not add, remove, or reorder content. Respond with ONLY the improved HTML.";

$messages = [
    ['role' => 'system', 'content' => $systemPrompt],
    ['role' => 'user', 'content' => $content]
];

try {
    $data = openai_chat($messages);
    $improved = $data['choices'][0]['message']['content'] ?? '';

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
