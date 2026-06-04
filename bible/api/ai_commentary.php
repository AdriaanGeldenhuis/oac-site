<?php
/**
 * Bible AI Commentary API
 * =======================
 * Provides contextual explanations of Bible passages using AI.
 * Only uses actual Bible text - no fabrication allowed.
 */

// Error handling - catch all errors and return as JSON
error_reporting(E_ALL);
ini_set('display_errors', '0');

set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function($e) {
    header('Content-Type: application/json');
    http_response_code(500);
    error_log("Bible AI exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    echo json_encode([
        'success' => false,
        'error' => 'An internal error occurred'
    ]);
    exit;
});

require_once __DIR__ . '/../../security/auth_gate.php';

$configPath = __DIR__ . '/../config.php';
if (!file_exists($configPath)) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'AI commentary not configured. Copy config.example.php to config.php.']);
    exit;
}
require_once $configPath;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$userId = $_SESSION['user_id'] ?? 0;
if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$verseRef = $input['verse_ref'] ?? '';      // e.g., "Lukas 17:21" or "Luke 17:21"
$verseText = $input['verse_text'] ?? '';
$bookEN = $input['book_en'] ?? '';           // English book name for lookup
$chapter = (int)($input['chapter'] ?? 0);
$verse = (int)($input['verse'] ?? 0);
$lang = $input['lang'] ?? 'af';

if (!$verseRef || !$verseText || !$chapter || !$verse) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

/**
 * Load Bible JSON file
 */
function loadBible(string $lang): ?array {
    $versions = BIBLE_VERSIONS;
    $file = $versions[$lang] ?? $versions['af'];
    $path = BIBLE_DIR . $file;

    if (!file_exists($path)) {
        return null;
    }

    $content = file_get_contents($path);
    return json_decode($content, true);
}

/**
 * Get surrounding verses for context
 */
function getSurroundingVerses(array $bible, string $bookEN, int $chapter, int $verse, string $lang): array {
    // Map English book names to Afrikaans - MUST match af_1933_53.json exactly
    $afBookMap = [
        'Genesis' => 'Genesis', 'Exodus' => 'Eksodus', 'Leviticus' => 'Levitikus',
        'Numbers' => 'Numeri', 'Deuteronomy' => 'Deuteronomium', 'Joshua' => 'Josua',
        'Judges' => 'Rigters', 'Ruth' => 'Rut', '1 Samuel' => '1 Samuel',
        '2 Samuel' => '2 Samuel', '1 Kings' => '1 Konings', '2 Kings' => '2 Konings',
        '1 Chronicles' => '1 Kronieke', '2 Chronicles' => '2 Kronieke', 'Ezra' => 'Esra',
        'Nehemiah' => 'Nehemía', 'Esther' => 'Ester', 'Job' => 'Job',
        'Psalms' => 'Psalms', 'Proverbs' => 'Spreuke', 'Ecclesiastes' => 'Prediker',
        'Song of Solomon' => 'Hooglied', 'Isaiah' => 'Jesaja', 'Jeremiah' => 'Jeremia',
        'Lamentations' => 'Klaagliedere', 'Ezekiel' => 'Eségiël', 'Daniel' => 'Daniël',
        'Hosea' => 'Hoséa', 'Joel' => 'Joël', 'Amos' => 'Amos',
        'Obadiah' => 'Obádja', 'Jonah' => 'Jona', 'Micah' => 'Miga',
        'Nahum' => 'Nahum', 'Habakkuk' => 'Hábakuk', 'Zephaniah' => 'Sefánja',
        'Haggai' => 'Haggai', 'Zechariah' => 'Sagaría', 'Malachi' => 'Maleági',
        'Matthew' => 'Matthéüs', 'Mark' => 'Markus', 'Luke' => 'Lukas',
        'John' => 'Johannes', 'Acts' => 'Handelinge', 'Romans' => 'Romeine',
        '1 Corinthians' => '1 Korinthiërs', '2 Corinthians' => '2 Korinthiërs',
        'Galatians' => 'Galásiërs', 'Ephesians' => 'Efésiërs', 'Philippians' => 'Filippense',
        'Colossians' => 'Kolossense', '1 Thessalonians' => '1 Thessalonicense',
        '2 Thessalonians' => '2 Thessalonicense', '1 Timothy' => '1 Timótheüs',
        '2 Timothy' => '2 Timótheüs', 'Titus' => 'Titus', 'Philemon' => 'Filémon',
        'Hebrews' => 'Hebreërs', 'James' => 'Jakobus', '1 Peter' => '1 Petrus',
        '2 Peter' => '2 Petrus', '1 John' => '1 Johannes', '2 John' => '2 Johannes',
        '3 John' => '3 Johannes', 'Jude' => 'Judas', 'Revelation' => 'Openbaring'
    ];

    $bookName = ($lang === 'af') ? ($afBookMap[$bookEN] ?? $bookEN) : $bookEN;

    // Find the book in the Bible data
    $bookData = null;
    if (isset($bible['books'])) {
        foreach ($bible['books'] as $book) {
            $name = $book['name'] ?? $book['book'] ?? '';
            if ($name === $bookName) {
                $bookData = $book;
                break;
            }
        }
    } elseif (isset($bible[$bookName])) {
        $bookData = $bible[$bookName];
    }

    if (!$bookData) {
        return [];
    }

    // Get the chapter
    $chapterData = null;
    if (isset($bookData['chapters'][$chapter - 1])) {
        $chapterData = $bookData['chapters'][$chapter - 1];
    } elseif (isset($bookData['chapter'][$chapter - 1])) {
        $chapterData = $bookData['chapter'][$chapter - 1];
    } elseif (isset($bookData[(string)$chapter])) {
        $chapterData = $bookData[(string)$chapter];
    }

    if (!$chapterData || !is_array($chapterData)) {
        return [];
    }

    // Extract verses with context
    $verses = [];
    $startVerse = max(1, $verse - AI_CONTEXT_VERSES_BEFORE);
    $endVerse = min(count($chapterData), $verse + AI_CONTEXT_VERSES_AFTER);

    $verseNum = 0;
    foreach ($chapterData as $item) {
        // Skip headings
        if (is_array($item) && isset($item['h'])) {
            continue;
        }

        $verseNum++;

        if ($verseNum >= $startVerse && $verseNum <= $endVerse) {
            $text = '';
            if (is_string($item)) {
                $text = $item;
            } elseif (is_array($item)) {
                $text = $item['v'] ?? $item['text'] ?? $item['verse'] ?? $item['t'] ?? '';
            }

            $marker = ($verseNum === $verse) ? ' **[SELECTED]**' : '';
            $verses[] = "v{$verseNum}: {$text}{$marker}";
        }
    }

    return $verses;
}

/**
 * Build the AI prompt using rules file
 */
function buildPrompt(string $verseRef, string $verseText, array $context, string $lang): array {
    // Load rules
    $rules = '';
    if (file_exists(AI_RULES_FILE)) {
        $rules = file_get_contents(AI_RULES_FILE);
    }

    // System prompt - concise and powerful Bible context
    $systemPrompt = $lang === 'af'
        ? "Jy gee KORT, KRAGTIGE konteks rondom 'n Bybelvers.
           Mense lees 1 vers en weet nie wat voor of na gebeur nie. Jy vertel hulle - bondig.

           REËLS:
           - NET wat in die Bybel staan - moenie byvoeg nie
           - NOOIT betekenis, interpretasie, lesse, of teologie nie
           - KORT EN KRAGTIG - elke seksie 2-4 sinne, nie paragrawe nie
           - Haal direk aan uit die verse

           4 seksies: DIE TONEEL, WAT VOOR GEBEUR, HIERDIE VERS, WAT DAARNA GEBEUR.

           {$rules}"
        : "You give SHORT, POWERFUL context around a Bible verse.
           People read 1 verse and don't know what happened before or after. You tell them - concisely.

           RULES:
           - ONLY what the Bible text says - don't add anything
           - NEVER meanings, interpretations, lessons, or theology
           - SHORT AND PUNCHY - each section 2-4 sentences, not paragraphs
           - Quote directly from the verses

           4 sections: THE SCENE, WHAT HAPPENED BEFORE, THIS VERSE, WHAT HAPPENS AFTER.

           {$rules}";

    // Build context string
    $contextText = implode("\n", $context);

    // User prompt - concise context request
    $userPrompt = $lang === 'af'
        ? "VERS: {$verseRef}
\"{$verseText}\"

OMLIGGENDE VERSE:
{$contextText}

Gee kort, kragtige konteks. Elke seksie 2-4 sinne. Haal aan uit die Bybel.

**DIE TONEEL:** Waar en wie?
**WAT VOOR GEBEUR:** Die storie van die ~10 verse voor.
**HIERDIE VERS:** Wat gebeur presies hier? Haal aan.
**WAT DAARNA GEBEUR:** Die storie van die ~10 verse na."

        : "VERSE: {$verseRef}
\"{$verseText}\"

SURROUNDING VERSES:
{$contextText}

Give short, powerful context. Each section 2-4 sentences. Quote from the Bible.

**THE SCENE:** Where and who?
**WHAT HAPPENED BEFORE:** The story of the ~10 verses before.
**THIS VERSE:** What exactly happens here? Quote it.
**WHAT HAPPENS AFTER:** The story of the ~10 verses after.";

    return [$systemPrompt, $userPrompt];
}

try {
    // Check rate limits
    if (defined('AI_RATE_LIMIT_HOUR') && $pdo) {
        $stmt = $pdo->prepare('
            SELECT COUNT(*) as cnt FROM bible_ai_commentary
            WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ');
        $stmt->execute([$userId]);
        $hourCount = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
        if ($hourCount >= AI_RATE_LIMIT_HOUR) {
            http_response_code(429);
            echo json_encode(['success' => false, 'error' => $lang === 'af' ? 'Te veel versoeke. Probeer later weer.' : 'Too many requests. Please try again later.']);
            exit;
        }
    }
    if (defined('AI_RATE_LIMIT_DAY') && $pdo) {
        $stmt = $pdo->prepare('
            SELECT COUNT(*) as cnt FROM bible_ai_commentary
            WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)
        ');
        $stmt->execute([$userId]);
        $dayCount = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
        if ($dayCount >= AI_RATE_LIMIT_DAY) {
            http_response_code(429);
            echo json_encode(['success' => false, 'error' => $lang === 'af' ? 'Daaglikse limiet bereik. Probeer more weer.' : 'Daily limit reached. Please try again tomorrow.']);
            exit;
        }
    }

    // Load the Bible in the user's language
    $bible = loadBible($lang);

    if (!$bible) {
        throw new Exception('Could not load Bible data');
    }

    // Get surrounding verses for context
    $context = getSurroundingVerses($bible, $bookEN, $chapter, $verse, $lang);

    if (empty($context)) {
        // Fallback: just use the provided verse
        $context = ["v{$verse}: {$verseText} **[SELECTED]**"];
    }

    // Build the prompt
    [$systemPrompt, $userPrompt] = buildPrompt($verseRef, $verseText, $context, $lang);

    // Call OpenAI API
    $ch = curl_init(OPENAI_API_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . OPENAI_API_KEY
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'model' => OPENAI_MODEL,
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt]
        ],
        'max_tokens' => OPENAI_MAX_TOKENS,
        'temperature' => OPENAI_TEMPERATURE
    ]));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        throw new Exception('Connection error: ' . $curlError);
    }

    if ($httpCode !== 200) {
        $errorData = json_decode($response, true);
        $errorMsg = $errorData['error']['message'] ?? "HTTP {$httpCode}";
        throw new Exception('API error: ' . $errorMsg);
    }

    $result = json_decode($response, true);
    $answer = $result['choices'][0]['message']['content'] ?? '';

    if (!$answer) {
        throw new Exception('Empty response from AI');
    }

    // Save to database for history
    try {
        global $pdo;
        if ($pdo) {
            $stmt = $pdo->prepare('
                INSERT INTO bible_ai_commentary (user_id, verse_ref, question, answer)
                VALUES (?, ?, ?, ?)
            ');
            $stmt->execute([$userId, $verseRef, 'context_explanation', $answer]);
        }
    } catch (Exception $dbError) {
        // Don't fail if database save fails
        error_log('Failed to save AI commentary: ' . $dbError->getMessage());
    }

    echo json_encode([
        'success' => true,
        'answer' => $answer,
        'context_verses' => count($context)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
