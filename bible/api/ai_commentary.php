<?php
/**
 * Bible AI Commentary API
 * =======================
 * Provides contextual explanations of Bible passages using AI.
 * Only uses actual Bible text - no fabrication allowed.
 */
require_once __DIR__ . '/../../security/auth_gate.php';
require_once __DIR__ . '/../config.php';

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
    // Map English book names to Afrikaans if needed
    $afBookMap = [
        'Genesis' => 'Genesis', 'Exodus' => 'Exodus', 'Leviticus' => 'Levitikus',
        'Numbers' => 'Numeri', 'Deuteronomy' => 'Deuteronomium', 'Joshua' => 'Josua',
        'Judges' => 'Rigters', 'Ruth' => 'Rut', '1 Samuel' => '1 Samuel',
        '2 Samuel' => '2 Samuel', '1 Kings' => '1 Konings', '2 Kings' => '2 Konings',
        '1 Chronicles' => '1 Kronieke', '2 Chronicles' => '2 Kronieke', 'Ezra' => 'Esra',
        'Nehemiah' => 'Nehemia', 'Esther' => 'Ester', 'Job' => 'Job',
        'Psalms' => 'Psalms', 'Proverbs' => 'Spreuke', 'Ecclesiastes' => 'Prediker',
        'Song of Solomon' => 'Hooglied', 'Isaiah' => 'Jesaja', 'Jeremiah' => 'Jeremia',
        'Lamentations' => 'Klaagliedere', 'Ezekiel' => 'Esegiel', 'Daniel' => 'Daniel',
        'Hosea' => 'Hosea', 'Joel' => 'Joel', 'Amos' => 'Amos',
        'Obadiah' => 'Obadja', 'Jonah' => 'Jona', 'Micah' => 'Miga',
        'Nahum' => 'Nahum', 'Habakkuk' => 'Habakuk', 'Zephaniah' => 'Sefanja',
        'Haggai' => 'Haggai', 'Zechariah' => 'Sagaria', 'Malachi' => 'Maleagi',
        'Matthew' => 'Matteus', 'Mark' => 'Markus', 'Luke' => 'Lukas',
        'John' => 'Johannes', 'Acts' => 'Handelinge', 'Romans' => 'Romeine',
        '1 Corinthians' => '1 Korinthiers', '2 Corinthians' => '2 Korinthiers',
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

    // System prompt
    $systemPrompt = $lang === 'af'
        ? "Jy is 'n Bybelse assistent wat die konteks en gebeure rondom Bybelverse verduidelik.
           Jy mag SLEGS feite uit die Bybelteks self gebruik - NOOIT inligting fabriseer nie.
           Antwoord in Afrikaans. Hou dit kort en bondig (3-4 paragrawe maksimum).

           REËLS:
           {$rules}"
        : "You are a Bible assistant that explains the context and events around Bible verses.
           You may ONLY use facts from the Bible text itself - NEVER fabricate information.
           Answer in English. Keep it brief and concise (3-4 paragraphs maximum).

           RULES:
           {$rules}";

    // Build context string
    $contextText = implode("\n", $context);

    // User prompt
    $userPrompt = $lang === 'af'
        ? "GESELEKTEERDE VERS: {$verseRef}
\"{$verseText}\"

OMLIGGENDE VERSE VIR KONTEKS:
{$contextText}

Verduidelik asseblief kortliks wat hier gebeur:
1. Waar en wanneer vind dit plaas?
2. Wie is betrokke? Wie praat?
3. Wat het voor hierdie vers gebeur?
4. Wat beteken hierdie vers in eenvoudige terme?

Onthou: Gebruik SLEGS wat in die Bybelteks staan. Moenie inligting byvoeg nie."

        : "SELECTED VERSE: {$verseRef}
\"{$verseText}\"

SURROUNDING VERSES FOR CONTEXT:
{$contextText}

Please briefly explain what is happening here:
1. Where and when does this take place?
2. Who is involved? Who is speaking?
3. What happened before this verse?
4. What does this verse mean in simple terms?

Remember: Use ONLY what is written in the Bible text. Do not add information.";

    return [$systemPrompt, $userPrompt];
}

try {
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
