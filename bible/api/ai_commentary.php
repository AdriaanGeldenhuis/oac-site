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
        'Nehemiah' => 'Nehemia', 'Esther' => 'Ester', 'Job' => 'Job',
        'Psalms' => 'Psalms', 'Proverbs' => 'Spreuke', 'Ecclesiastes' => 'Prediker',
        'Song of Solomon' => 'Hooglied', 'Isaiah' => 'Jesaja', 'Jeremiah' => 'Jeremia',
        'Lamentations' => 'Klaagliedere', 'Ezekiel' => 'Esegiel', 'Daniel' => 'Daniël',
        'Hosea' => 'Hosea', 'Joel' => 'Joel', 'Amos' => 'Amos',
        'Obadiah' => 'Obadja', 'Jonah' => 'Jona', 'Micah' => 'Miga',
        'Nahum' => 'Nahum', 'Habakkuk' => 'Habakuk', 'Zephaniah' => 'Sefanja',
        'Haggai' => 'Haggai', 'Zechariah' => 'Sagaria', 'Malachi' => 'Maleagi',
        'Matthew' => 'Matteus', 'Mark' => 'Markus', 'Luke' => 'Lukas',
        'John' => 'Johannes', 'Acts' => 'Handelinge', 'Romans' => 'Romeine',
        '1 Corinthians' => '1 Korinthiërs', '2 Corinthians' => '2 Korinthiers',
        'Galatians' => 'Galasiers', 'Ephesians' => 'Efesiers', 'Philippians' => 'Filippense',
        'Colossians' => 'Kolossense', '1 Thessalonians' => '1 Thessalonisense',
        '2 Thessalonians' => '2 Thessalonisense', '1 Timothy' => '1 Timotheus',
        '2 Timothy' => '2 Timotheus', 'Titus' => 'Titus', 'Philemon' => 'Filemon',
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

    // System prompt - storyteller that gives the FULL picture around a verse
    $systemPrompt = $lang === 'af'
        ? "Jy is 'n Bybelse verteller. Jy vertel die STORIE rondom 'n vers sodat mense die volle prentjie kry.

           Baie mense lees of hoor net 1 vers, maar hulle weet nie wat voor of na gebeur het nie.
           JOU WERK is om die ~10 verse VOOR en ~10 verse NA te gebruik om 'n vloeiende,
           verstaanbare verhaal te vertel. As die vers naby die begin of einde van 'n hoofstuk is,
           gebruik soveel verse as wat beskikbaar is.

           STRENG VERBODE:
           - GEEN betekenis of interpretasie
           - GEEN 'dit beteken...' of 'die les is...'
           - GEEN geestelike toepassings of lewenslesse
           - GEEN teologiese verduidelikings

           JY MOET:
           - Die toneel stel (waar, wanneer, wie)
           - Die verhaal VOOR hierdie vers vertel as 'n vloeiende vertelling
           - Presies beskryf wat in HIERDIE vers gebeur, met direkte aanhalings
           - Die verhaal NA hierdie vers vertel
           - Slegs gebruik wat in die Bybelteks staan - moenie byvoeg nie

           Maak dit lewendig en interessant - soos wanneer jy 'n vriend vertel wat in 'n storie gebeur het.
           Gebruik die presiese formaat in die reëls.

           {$rules}"
        : "You are a Bible storyteller. You tell the STORY around a verse so people get the full picture.

           Many people read or hear just 1 verse, but they don't know what happened before or after.
           YOUR JOB is to use the ~10 verses BEFORE and ~10 verses AFTER to tell a flowing,
           understandable story. If the verse is near the start or end of a chapter,
           use as many verses as are available.

           STRICTLY FORBIDDEN:
           - NO meanings or interpretations
           - NO 'this means...' or 'the lesson is...'
           - NO spiritual applications or life lessons
           - NO theological explanations

           YOU MUST:
           - Set the scene (where, when, who)
           - Tell the story BEFORE this verse as a flowing narrative
           - Describe exactly what happens in THIS verse, with direct quotes
           - Tell the story AFTER this verse
           - Only use what is written in the Bible text - do not add anything

           Make it vivid and interesting - like telling a friend what happened in a story.
           Use the exact format in the rules.

           {$rules}";

    // Build context string
    $contextText = implode("\n", $context);

    // User prompt - ask for full story context
    $userPrompt = $lang === 'af'
        ? "GESELEKTEERDE VERS: {$verseRef}
\"{$verseText}\"

KONTEKS - DIE VERSE RONDOM HIERDIE VERS (10 voor en 10 na, of soveel as beskikbaar):
{$contextText}

Vertel die VOLLE VERHAAL rondom hierdie vers. Gebruik die verse hierbo om konteks te gee.
Baie mense ken net hierdie een vers - help hulle om die groter storie te verstaan.

**WAAR EN WANNEER:** Stel die toneel - waar en wanneer gebeur dit?
**WIE IS BETROKKE:** Wie is die karakters? Wie praat? Wie luister?
**DIE VERHAAL TOT HIER:** Vertel as 'n vloeiende verhaal wat in die ~10 verse VOOR gebeur het. Haal aan uit die verse.
**HIERDIE OOMBLIK:** Wat gebeur PRESIES in hierdie vers? Haal die woorde direk aan.
**DIE VERHAAL GAAN VOORT:** Vertel wat in die ~10 verse NA hierdie vers gebeur. Hoe gaan die storie voort?"

        : "SELECTED VERSE: {$verseRef}
\"{$verseText}\"

CONTEXT - THE VERSES AROUND THIS VERSE (10 before and 10 after, or as many as available):
{$contextText}

Tell the FULL STORY around this verse. Use the verses above to provide context.
Many people only know this one verse - help them understand the bigger story.

**WHERE AND WHEN:** Set the scene - where and when does this happen?
**WHO IS INVOLVED:** Who are the characters? Who speaks? Who listens?
**THE STORY SO FAR:** Tell as a flowing narrative what happened in the ~10 verses BEFORE. Quote from the verses.
**THIS MOMENT:** What EXACTLY happens in this verse? Quote the words directly.
**THE STORY CONTINUES:** Tell what happens in the ~10 verses AFTER this verse. How does the story continue?";

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
