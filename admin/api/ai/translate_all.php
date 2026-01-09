<?php
declare(strict_types=1);

/**
 * Translate content to a SINGLE target language
 * Called multiple times by frontend (once per language)
 * Uses AI for text translation but preserves Bible verses from actual Bible files
 */

// ALWAYS output JSON
header('Content-Type: application/json; charset=utf-8');

// Global error handler to catch all PHP errors and return JSON
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function($e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'PHP Error',
        'detail' => $e->getMessage(),
        'file' => basename($e->getFile()) . ':' . $e->getLine(),
        'trace' => $e->getTraceAsString()
    ], JSON_UNESCAPED_UNICODE);
    exit;
});

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// 3 minutes for ONE language should be plenty
set_time_limit(180);
ini_set('max_execution_time', '180');

// Load dependencies - check files exist first
$baseDir = dirname(__DIR__, 3);
$requiredFiles = [
    'security/config.php',
    'security/session.php',
    'security/auth.php',
    'includes/languages.php'
];

// Check all required files exist
foreach ($requiredFiles as $file) {
    $fullPath = $baseDir . '/' . $file;
    if (!file_exists($fullPath)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Missing file: ' . $file, 'path' => $fullPath]);
        exit;
    }
}

// Also check ai_config
$aiConfigPath = dirname(__DIR__, 2) . '/config/ai_config.php';
if (!file_exists($aiConfigPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Missing file: ai_config.php', 'path' => $aiConfigPath]);
    exit;
}

// Now load them
try {
    require_once $baseDir . '/security/config.php';
    require_once $baseDir . '/security/session.php';
    require_once $baseDir . '/security/auth.php';
    require_once $baseDir . '/includes/languages.php';
    require_once $aiConfigPath;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to load: ' . $e->getMessage(), 'file' => basename($e->getFile()) . ':' . $e->getLine()]);
    exit;
}

// Start output buffering
ob_start();
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

// Check API key is configured
if (!defined('OPENAI_API_KEY') || OPENAI_API_KEY === '') {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'OpenAI API key not configured. Please add your API key to admin/config/secrets.php']);
    exit;
}

// Get inputs
$content = trim((string)($_POST['content'] ?? ''));
$sourceLang = trim((string)($_POST['source_lang'] ?? 'af'));
$targetLang = trim((string)($_POST['target_lang'] ?? ''));

if ($content === '') {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No content provided']);
    exit;
}

if ($targetLang === '') {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No target_lang provided']);
    exit;
}

// Validate languages
if (!in_array($sourceLang, SUPPORTED_LANGS, true)) {
    $sourceLang = 'af';
}

if (!in_array($targetLang, SUPPORTED_LANGS, true)) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid target language: ' . $targetLang]);
    exit;
}

// If source == target, just return the content as-is
if ($sourceLang === $targetLang) {
    ob_end_clean();
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'target_lang' => $targetLang,
        'translation' => $content
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Language names for prompts
$langNames = [
    'af' => 'Afrikaans',
    'en' => 'English',
    'zu' => 'isiZulu',
    'xh' => 'isiXhosa',
    'pt' => 'Portuguese'
];

// Extract verse references to preserve them
$versePattern = '/<p class="(?:verse-ref|vref)">([^<]+)<\/p>\s*<p class="(?:verse-text|vtxt)">(.+?)<\/p>/si';
$verseMatches = [];
preg_match_all($versePattern, $content, $verseMatches, PREG_SET_ORDER);

try {
    error_log('=== Translating to: ' . $targetLang . ' ===');
    error_log('Content length: ' . strlen($content) . ' chars');

    $sourceName = $langNames[$sourceLang];
    $targetName = $langNames[$targetLang];

    // Build system prompt - emphasize COMPLETE translation
    $systemPrompt = "You are a professional translator specializing in religious content. " .
        "Translate the ENTIRE content from {$sourceName} to {$targetName}. " .
        "CRITICAL RULES:\n" .
        "1. Translate ALL paragraphs, ALL headings, and ALL text - do not skip anything\n" .
        "2. Preserve the exact HTML structure and tags (<p>, <h1>, <h2>, <h3>, <span>, etc.)\n" .
        "3. Keep all class attributes intact (class=\"vref\", class=\"vtxt\", etc.)\n" .
        "4. Do NOT translate Bible verse references (keep book names and chapter:verse numbers as-is)\n" .
        "5. Output ONLY the translated HTML - no explanations, no comments\n" .
        "6. Make sure to translate the COMPLETE document from start to finish";

    $messages = [
        ['role' => 'system', 'content' => $systemPrompt],
        ['role' => 'user', 'content' => $content]
    ];

    error_log('Calling OpenAI API for ' . $targetLang . '...');
    $data = openai_chat($messages);
    error_log('OpenAI response received for ' . $targetLang);
    $translated = $data['choices'][0]['message']['content'] ?? '';

    if ($translated === '') {
        error_log('ERROR: Empty translation for ' . $targetLang);
        throw new Exception("Empty translation for {$targetLang}");
    }

    // Log translation length comparison
    $sourceLen = strlen($content);
    $transLen = strlen($translated);
    error_log('Translation for ' . $targetLang . ' length: ' . $transLen . ' chars (source: ' . $sourceLen . ' chars)');

    // Warning if translation is significantly shorter than source (might be truncated)
    if ($transLen < ($sourceLen * 0.5)) {
        error_log('WARNING: Translation for ' . $targetLang . ' seems truncated! Only ' . round(($transLen/$sourceLen)*100) . '% of source length');
    }

    // Now replace verse text with actual Bible verses
    $bible = loadBibleData($targetLang);
    if ($bible && !empty($verseMatches)) {
        foreach ($verseMatches as $match) {
            $reference = trim($match[1]);

            // Parse reference: "Book Chapter:VerseFrom-VerseTo" or "Book Chapter:Verse"
            if (preg_match('/^([A-Za-zÀ-ÿ\s]+)\s+(\d+):(\d+)(?:-(\d+))?$/u', $reference, $parts)) {
                $book = trim($parts[1]);
                $chapter = (int)$parts[2];
                $verseFrom = (int)$parts[3];
                $verseTo = isset($parts[4]) ? (int)$parts[4] : $verseFrom;

                // Try to find the book in the Bible data
                $bibleVerses = null;
                if (isset($bible[$book][$chapter])) {
                    $bibleVerses = $bible[$book][$chapter];
                } else {
                    // Try to find by partial match or common variations
                    foreach (array_keys($bible) as $bibleBook) {
                        if (stripos($bibleBook, $book) === 0 || stripos($book, $bibleBook) === 0) {
                            if (isset($bible[$bibleBook][$chapter])) {
                                $bibleVerses = $bible[$bibleBook][$chapter];
                                break;
                            }
                        }
                    }
                }

                if ($bibleVerses) {
                    $verseTexts = [];
                    $verseNum = 1;
                    foreach ($bibleVerses as $v) {
                        if (isset($v['v'])) {
                            $num = isset($v['n']) ? (int)$v['n'] : $verseNum;
                            if ($num >= $verseFrom && $num <= $verseTo) {
                                $verseTexts[] = '<sup>' . $num . '</sup> ' . $v['v'];
                            }
                            $verseNum++;
                        }
                    }

                    if (!empty($verseTexts)) {
                        $newVerseHtml = '<p class="vref">' . htmlspecialchars($reference) . '</p><p class="vtxt">' . implode(' ', $verseTexts) . '</p>';
                        $refPattern = '/<p class="(?:verse-ref|vref)">[^<]*' . preg_quote($parts[2] . ':' . $parts[3], '/') . '[^<]*<\/p>\s*<p class="(?:verse-text|vtxt)">.+?<\/p>/si';
                        $translated = preg_replace($refPattern, $newVerseHtml, $translated, 1);
                    }
                }
            }
        }
    }

    ob_end_clean();
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'target_lang' => $targetLang,
        'translation' => $translated
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log('=== TRANSLATION ERROR ===');
    error_log('Message: ' . $e->getMessage());
    error_log('File: ' . $e->getFile() . ':' . $e->getLine());
    error_log('Trace: ' . $e->getTraceAsString());
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Translation failed',
        'detail' => $e->getMessage(),
        'target_lang' => $targetLang
    ], JSON_UNESCAPED_UNICODE);
}

exit;
