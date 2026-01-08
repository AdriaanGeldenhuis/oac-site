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
$fromLang = trim((string)($_POST['from_lang'] ?? 'af'));
$toLang = $fromLang === 'af' ? 'en' : 'af';

if ($content === '') {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No content provided']);
    exit;
}

// Load Bible for verse replacement
$bible = loadBibleData($toLang);
$verseReplacements = [];
$verseCount = 0;

if ($bible && preg_match_all('/<p class="verse-ref">([^<]+)<\/p>\s*<p class="verse-text">(.+?)<\/p>/si', $content, $matches, PREG_SET_ORDER)) {
    foreach ($matches as $match) {
        $reference = trim($match[1]);
        $originalText = $match[2];

        if (preg_match('/^([A-Za-zÀ-ÿ\s]+)\s+(\d+):(\d+)(?:-(\d+))?$/u', $reference, $parts)) {
            $book = trim($parts[1]);
            $chapter = (int)$parts[2];
            $verseFrom = (int)$parts[3];
            $verseTo = isset($parts[4]) ? (int)$parts[4] : $verseFrom;

            if (isset($bible[$book][$chapter])) {
                $verses = [];
                foreach ($bible[$book][$chapter] as $v) {
                    if (isset($v['n'], $v['v'])) {
                        $num = (int)$v['n'];
                        if ($num >= $verseFrom && $num <= $verseTo) {
                            $verses[] = '<sup>' . $num . '</sup> ' . $v['v'];
                        }
                    }
                }

                if (!empty($verses)) {
                    $verseReplacements[$originalText] = implode(' ', $verses);
                    $verseCount++;
                }
            }
        }
    }
}

// Build prompt
$systemPrompt = $toLang === 'en'
    ? "Translate from Afrikaans to English. Preserve exact HTML structure and tags. Keep verse references intact. Do not add commentary. Respond with ONLY the translated HTML."
    : "Vertaal van Engels na Afrikaans. Behou die presiese HTML-struktuur en tags. Hou versverwysings onveranderd. Moenie kommentaar byvoeg nie. Antwoord met NET die vertaalde HTML.";

$messages = [
    ['role' => 'system', 'content' => $systemPrompt],
    ['role' => 'user', 'content' => $content]
];

try {
    $data = openai_chat($messages);
    $translated = $data['choices'][0]['message']['content'] ?? '';

    if ($translated === '') {
        throw new Exception('Empty AI response');
    }

    // Replace verses
    foreach ($verseReplacements as $original => $replacement) {
        $translated = str_replace($original, $replacement, $translated);
    }

    ob_end_clean();
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'translated' => $translated,
        'from' => $fromLang,
        'to' => $toLang,
        'verse_count' => $verseCount
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Translation failed',
        'detail' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

exit;
