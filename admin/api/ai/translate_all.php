<?php
declare(strict_types=1);

/**
 * Translate content to all supported languages
 * Uses AI for text translation but preserves Bible verses from actual Bible files
 */

// Global error handler to catch all PHP errors and return JSON
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function($e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server Error',
        'detail' => $e->getMessage(),
        'file' => basename($e->getFile()) . ':' . $e->getLine()
    ]);
    exit;
});

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Custom error log for this file
$errorLogFile = dirname(__DIR__, 3) . '/logs/translate_all.log';
if (!is_dir(dirname($errorLogFile))) {
    @mkdir(dirname($errorLogFile), 0755, true);
}
ini_set('error_log', $errorLogFile);
error_log('=== TRANSLATE_ALL START: ' . date('Y-m-d H:i:s') . ' ===');

// Increase PHP timeout for long translations (4 languages)
set_time_limit(600); // 10 minutes
ini_set('max_execution_time', '600');

// Load dependencies - check files exist first (require_once fatal errors can't be caught)
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
        error_log('MISSING FILE: ' . $fullPath);
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Missing file: ' . $file]);
        exit;
    }
}

// Also check ai_config
$aiConfigPath = dirname(__DIR__, 2) . '/config/ai_config.php';
if (!file_exists($aiConfigPath)) {
    error_log('MISSING FILE: ' . $aiConfigPath);
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Missing file: ai_config.php']);
    exit;
}

// Now load them
try {
    error_log('Loading dependencies...');
    require_once $baseDir . '/security/config.php';
    require_once $baseDir . '/security/session.php';
    require_once $baseDir . '/security/auth.php';
    require_once $baseDir . '/includes/languages.php';
    require_once $aiConfigPath;
    error_log('All dependencies loaded successfully');
} catch (Throwable $e) {
    error_log('FAILED to load dependencies: ' . $e->getMessage());
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to load dependencies', 'detail' => $e->getMessage()]);
    exit;
}

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

if ($content === '') {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No content provided']);
    exit;
}

// Validate source language
if (!in_array($sourceLang, SUPPORTED_LANGS, true)) {
    $sourceLang = 'af';
}

// Language names for prompts
$langNames = [
    'af' => 'Afrikaans',
    'en' => 'English',
    'zu' => 'isiZulu',
    'xh' => 'isiXhosa',
    'pt' => 'Portuguese'
];

// Target languages (exclude source)
$targetLangs = array_filter(SUPPORTED_LANGS, fn($l) => $l !== $sourceLang);

// Extract verse references to preserve them
// Pattern supports both old (verse-ref/verse-text) and new (vref/vtxt) class names
$versePattern = '/<p class="(?:verse-ref|vref)">([^<]+)<\/p>\s*<p class="(?:verse-text|vtxt)">(.+?)<\/p>/si';
$verseMatches = [];
preg_match_all($versePattern, $content, $verseMatches, PREG_SET_ORDER);

// Build translations array
$translations = [];
$translations[$sourceLang] = $content; // Source stays the same

try {
    error_log('Starting translation loop. Source: ' . $sourceLang . ', Targets: ' . implode(', ', $targetLangs));
    error_log('Content length: ' . strlen($content) . ' chars');

    foreach ($targetLangs as $targetLang) {
        error_log('--- Translating to: ' . $targetLang . ' ---');
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
                    // Book names might differ between languages, try exact match first
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
                        // Handle both array structures: with 'n' key or just sequential 'v' entries
                        $verseNum = 1;
                        foreach ($bibleVerses as $v) {
                            if (isset($v['v'])) {
                                $num = isset($v['n']) ? (int)$v['n'] : $verseNum;
                                if ($num >= $verseFrom && $num <= $verseTo) {
                                    // Use exact verse text from Bible file - no htmlspecialchars to preserve formatting
                                    $verseTexts[] = '<sup>' . $num . '</sup> ' . $v['v'];
                                }
                                $verseNum++;
                            }
                        }

                        if (!empty($verseTexts)) {
                            // Build the replacement HTML using vref/vtxt classes (new standard)
                            $newVerseHtml = '<p class="vref">' . htmlspecialchars($reference) . '</p><p class="vtxt">' . implode(' ', $verseTexts) . '</p>';

                            // Find and replace the verse block in translated content
                            // Support both old (verse-ref/verse-text) and new (vref/vtxt) class names
                            $refPattern = '/<p class="(?:verse-ref|vref)">[^<]*' . preg_quote($parts[2] . ':' . $parts[3], '/') . '[^<]*<\/p>\s*<p class="(?:verse-text|vtxt)">.+?<\/p>/si';
                            $translated = preg_replace($refPattern, $newVerseHtml, $translated, 1);
                        }
                    }
                }
            }
        }

        $translations[$targetLang] = $translated;
    }

    ob_end_clean();
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'translations' => $translations,
        'source_lang' => $sourceLang,
        'translated_count' => count($targetLangs)
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
        'detail' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

exit;
