<?php
declare(strict_types=1);

/**
 * Translate content to all supported languages
 * Uses AI for text translation but preserves Bible verses from actual Bible files
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Increase PHP timeout for long translations (4 languages)
set_time_limit(600); // 10 minutes
ini_set('max_execution_time', '600');

// Load dependencies
try {
    require_once dirname(__DIR__, 3) . '/security/config.php';
    require_once dirname(__DIR__, 3) . '/security/session.php';
    require_once dirname(__DIR__, 3) . '/security/auth.php';
    require_once dirname(__DIR__, 3) . '/includes/languages.php';
    require_once dirname(__DIR__, 2) . '/config/ai_config.php';
} catch (Throwable $e) {
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
// Pattern: <p class="vref">Book Chapter:Verse</p><p class="vtxt">...</p>
$versePattern = '/<p class="vref">([^<]+)<\/p>\s*<p class="vtxt">(.+?)<\/p>/si';
$verseMatches = [];
preg_match_all($versePattern, $content, $verseMatches, PREG_SET_ORDER);

// Build translations array
$translations = [];
$translations[$sourceLang] = $content; // Source stays the same

try {
    foreach ($targetLangs as $targetLang) {
        $sourceName = $langNames[$sourceLang];
        $targetName = $langNames[$targetLang];

        // Build system prompt
        $systemPrompt = "You are a professional translator. Translate from {$sourceName} to {$targetName}. " .
            "Preserve the exact HTML structure and tags. " .
            "Keep all class attributes intact. " .
            "Do NOT translate Bible verse references (book names, chapter:verse numbers). " .
            "Respond with ONLY the translated HTML, no explanations.";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $content]
        ];

        $data = openai_chat($messages);
        $translated = $data['choices'][0]['message']['content'] ?? '';

        if ($translated === '') {
            throw new Exception("Empty translation for {$targetLang}");
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
                        foreach ($bibleVerses as $v) {
                            if (isset($v['n'], $v['v'])) {
                                $num = (int)$v['n'];
                                if ($num >= $verseFrom && $num <= $verseTo) {
                                    $verseTexts[] = '<sup>' . $num . '</sup> ' . htmlspecialchars($v['v']);
                                }
                            }
                        }

                        if (!empty($verseTexts)) {
                            // Build the replacement HTML
                            $newVerseHtml = '<p class="vref">' . htmlspecialchars($reference) . '</p><p class="vtxt">' . implode(' ', $verseTexts) . '</p>';

                            // Find and replace the verse block in translated content
                            // The reference should still be there, just need to replace the vtxt content
                            $refPattern = '/<p class="vref">[^<]*' . preg_quote($parts[2] . ':' . $parts[3], '/') . '[^<]*<\/p>\s*<p class="vtxt">.+?<\/p>/si';
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
    error_log('Translation error: ' . $e->getMessage());
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Translation failed',
        'detail' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

exit;
