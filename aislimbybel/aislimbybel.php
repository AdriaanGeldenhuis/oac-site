<?php
// =====================================================================
// /aislimbybel/aislimbybel.php — AI Slimbybel
// =====================================================================

require_once __DIR__ . '/../security/auth_gate.php';
require_once __DIR__ . '/../includes/languages.php';

// -------------------------- Taal --------------------------------------
$pageLang = $_SESSION['language'] ?? 'af';
if (isset($_GET['lang']) && in_array($_GET['lang'], SUPPORTED_LANGS, true)) {
  $_SESSION['language'] = $_GET['lang'];
  $pageLang = $_GET['lang'];
  header('Location: /aislimbybel/aislimbybel.php');
  exit;
}
// Translation helper using central 5-language system
function t(string $key): string { global $pageLang; return __t($key, $pageLang); }

// --------------------- Laai ou konfig/riglyne -------------------------
$OLD_CONFIG = __DIR__ . '/config.php';
$INSTR_FILE = __DIR__ . '/Slimbybel_Instruksies.txt';
if (is_file($OLD_CONFIG)) {
  @require_once $OLD_CONFIG;
}
$OPENAI_API_KEY = defined('OPENAI_API_KEY') ? constant('OPENAI_API_KEY') : getenv('OPENAI_API_KEY');

$SYSTEM_PROMPT = '';
if (defined('SLIMBYBEL_SYSTEM_PROMPT')) {
  $SYSTEM_PROMPT = constant('SLIMBYBEL_SYSTEM_PROMPT');
} elseif (is_file($INSTR_FILE)) {
  $SYSTEM_PROMPT = file_get_contents($INSTR_FILE) ?: '';
} else {
  $SYSTEM_PROMPT = 'Interpret the Scriptures spiritually. Use Afrikaans 1933/1953 or KJV only.';
}

// =====================================================================
// BIBLE VERSE LOOKUP SYSTEM - Loads verses directly from server files
// =====================================================================
$BIBLE_DIR = __DIR__ . '/../bible/bibles/';
$BIBLE_FILES = [
  'af' => $BIBLE_DIR . 'af_1933_53.json',
  'en' => $BIBLE_DIR . 'en_kjv1611.json',
];

// Book name mapping (Afrikaans to English and vice versa)
$BOOK_NAMES = [
  // Afrikaans => English
  'genesis' => 'Genesis', 'gen' => 'Genesis',
  'eksodus' => 'Exodus', 'eks' => 'Exodus', 'exodus' => 'Exodus', 'ex' => 'Exodus',
  'levitikus' => 'Leviticus', 'lev' => 'Leviticus', 'leviticus' => 'Leviticus',
  'numeri' => 'Numbers', 'num' => 'Numbers', 'numbers' => 'Numbers',
  'deuteronomium' => 'Deuteronomy', 'deut' => 'Deuteronomy', 'deuteronomy' => 'Deuteronomy',
  'josua' => 'Joshua', 'jos' => 'Joshua', 'joshua' => 'Joshua',
  'rigters' => 'Judges', 'rig' => 'Judges', 'judges' => 'Judges',
  'rut' => 'Ruth', 'ruth' => 'Ruth',
  '1 samuel' => '1 Samuel', '1 sam' => '1 Samuel', '1samuel' => '1 Samuel',
  '2 samuel' => '2 Samuel', '2 sam' => '2 Samuel', '2samuel' => '2 Samuel',
  '1 konings' => '1 Kings', '1 kon' => '1 Kings', '1 kings' => '1 Kings', '1konings' => '1 Kings',
  '2 konings' => '2 Kings', '2 kon' => '2 Kings', '2 kings' => '2 Kings', '2konings' => '2 Kings',
  '1 kronieke' => '1 Chronicles', '1 kron' => '1 Chronicles', '1 chronicles' => '1 Chronicles',
  '2 kronieke' => '2 Chronicles', '2 kron' => '2 Chronicles', '2 chronicles' => '2 Chronicles',
  'esra' => 'Ezra', 'ezra' => 'Ezra',
  'nehemia' => 'Nehemiah', 'neh' => 'Nehemiah', 'nehemiah' => 'Nehemiah',
  'ester' => 'Esther', 'esther' => 'Esther',
  'job' => 'Job',
  'psalms' => 'Psalms', 'ps' => 'Psalms', 'psalm' => 'Psalms',
  'spreuke' => 'Proverbs', 'spr' => 'Proverbs', 'proverbs' => 'Proverbs', 'prov' => 'Proverbs',
  'prediker' => 'Ecclesiastes', 'pred' => 'Ecclesiastes', 'ecclesiastes' => 'Ecclesiastes', 'eccl' => 'Ecclesiastes',
  'hooglied' => 'Song of Solomon', 'hoogl' => 'Song of Solomon', 'song of solomon' => 'Song of Solomon', 'song' => 'Song of Solomon',
  'jesaja' => 'Isaiah', 'jes' => 'Isaiah', 'isaiah' => 'Isaiah', 'isa' => 'Isaiah',
  'jeremia' => 'Jeremiah', 'jer' => 'Jeremiah', 'jeremiah' => 'Jeremiah',
  'klaagliedere' => 'Lamentations', 'klaag' => 'Lamentations', 'lamentations' => 'Lamentations', 'lam' => 'Lamentations',
  'esegiël' => 'Ezekiel', 'eseg' => 'Ezekiel', 'ezekiel' => 'Ezekiel', 'ezek' => 'Ezekiel',
  'daniël' => 'Daniel', 'dan' => 'Daniel', 'daniel' => 'Daniel',
  'hosea' => 'Hosea', 'hos' => 'Hosea',
  'joël' => 'Joel', 'joel' => 'Joel',
  'amos' => 'Amos',
  'obadja' => 'Obadiah', 'obad' => 'Obadiah', 'obadiah' => 'Obadiah',
  'jona' => 'Jonah', 'jonah' => 'Jonah',
  'miga' => 'Micah', 'micah' => 'Micah', 'mic' => 'Micah',
  'nahum' => 'Nahum', 'nah' => 'Nahum',
  'habakuk' => 'Habakkuk', 'hab' => 'Habakkuk', 'habakkuk' => 'Habakkuk',
  'sefanja' => 'Zephaniah', 'sef' => 'Zephaniah', 'zephaniah' => 'Zephaniah', 'zeph' => 'Zephaniah',
  'haggai' => 'Haggai', 'hag' => 'Haggai',
  'sagaria' => 'Zechariah', 'sag' => 'Zechariah', 'zechariah' => 'Zechariah', 'zech' => 'Zechariah',
  'maleagi' => 'Malachi', 'mal' => 'Malachi', 'malachi' => 'Malachi',
  // New Testament
  'matteus' => 'Matthew', 'mat' => 'Matthew', 'matthew' => 'Matthew', 'matt' => 'Matthew',
  'markus' => 'Mark', 'mark' => 'Mark', 'mar' => 'Mark',
  'lukas' => 'Luke', 'luk' => 'Luke', 'luke' => 'Luke',
  'johannes' => 'John', 'joh' => 'John', 'john' => 'John',
  'handelinge' => 'Acts', 'hand' => 'Acts', 'acts' => 'Acts',
  'romeine' => 'Romans', 'rom' => 'Romans', 'romans' => 'Romans',
  '1 korintiërs' => '1 Corinthians', '1 kor' => '1 Corinthians', '1 corinthians' => '1 Corinthians', '1kor' => '1 Corinthians',
  '2 korintiërs' => '2 Corinthians', '2 kor' => '2 Corinthians', '2 corinthians' => '2 Corinthians', '2kor' => '2 Corinthians',
  'galasiërs' => 'Galatians', 'gal' => 'Galatians', 'galatians' => 'Galatians',
  'efesiërs' => 'Ephesians', 'ef' => 'Ephesians', 'ephesians' => 'Ephesians', 'eph' => 'Ephesians',
  'filippense' => 'Philippians', 'fil' => 'Philippians', 'philippians' => 'Philippians', 'phil' => 'Philippians', 'filp' => 'Philippians',
  'kolossense' => 'Colossians', 'kol' => 'Colossians', 'colossians' => 'Colossians', 'col' => 'Colossians',
  '1 tessalonisense' => '1 Thessalonians', '1 tes' => '1 Thessalonians', '1 thessalonians' => '1 Thessalonians', '1tes' => '1 Thessalonians', '1 thes' => '1 Thessalonians',
  '2 tessalonisense' => '2 Thessalonians', '2 tes' => '2 Thessalonians', '2 thessalonians' => '2 Thessalonians', '2tes' => '2 Thessalonians', '2 thes' => '2 Thessalonians',
  '1 timoteus' => '1 Timothy', '1 tim' => '1 Timothy', '1 timothy' => '1 Timothy', '1tim' => '1 Timothy',
  '2 timoteus' => '2 Timothy', '2 tim' => '2 Timothy', '2 timothy' => '2 Timothy', '2tim' => '2 Timothy',
  'titus' => 'Titus', 'tit' => 'Titus',
  'filemon' => 'Philemon', 'filem' => 'Philemon', 'philemon' => 'Philemon', 'phm' => 'Philemon',
  'hebreërs' => 'Hebrews', 'heb' => 'Hebrews', 'hebrews' => 'Hebrews', 'hebr' => 'Hebrews',
  'jakobus' => 'James', 'jak' => 'James', 'james' => 'James', 'jas' => 'James',
  '1 petrus' => '1 Peter', '1 pet' => '1 Peter', '1 peter' => '1 Peter', '1pet' => '1 Peter',
  '2 petrus' => '2 Peter', '2 pet' => '2 Peter', '2 peter' => '2 Peter', '2pet' => '2 Peter',
  '1 johannes' => '1 John', '1 joh' => '1 John', '1 john' => '1 John', '1joh' => '1 John',
  '2 johannes' => '2 John', '2 joh' => '2 John', '2 john' => '2 John', '2joh' => '2 John',
  '3 johannes' => '3 John', '3 joh' => '3 John', '3 john' => '3 John', '3joh' => '3 John',
  'judas' => 'Jude', 'jude' => 'Jude',
  'openbaring' => 'Revelation', 'op' => 'Revelation', 'openb' => 'Revelation', 'revelation' => 'Revelation', 'rev' => 'Revelation',
];

// Cache for loaded Bible data
$BIBLE_CACHE = [];

/**
 * Load Bible data for a language
 */
function loadBible(string $lang): ?array {
  global $BIBLE_FILES, $BIBLE_CACHE;

  if (isset($BIBLE_CACHE[$lang])) {
    return $BIBLE_CACHE[$lang];
  }

  $file = $BIBLE_FILES[$lang] ?? null;
  if (!$file || !is_readable($file)) {
    return null;
  }

  $data = @json_decode(file_get_contents($file), true);
  if ($data) {
    $BIBLE_CACHE[$lang] = $data;
  }
  return $data;
}

/**
 * Normalize book name to standard format
 */
function normalizeBookName(string $name): ?string {
  global $BOOK_NAMES;
  $lower = strtolower(trim($name));
  return $BOOK_NAMES[$lower] ?? null;
}

/**
 * Get a specific verse from the Bible
 * Returns the verse text or null if not found
 */
function getVerse(string $lang, string $book, int $chapter, int $verse): ?string {
  $bible = loadBible($lang);
  if (!$bible) return null;

  $normalBook = normalizeBookName($book);
  if (!$normalBook) return null;

  // Try exact match first
  if (!isset($bible[$normalBook])) {
    // Try case-insensitive search
    foreach ($bible as $bookName => $chapters) {
      if (strtolower($bookName) === strtolower($normalBook)) {
        $normalBook = $bookName;
        break;
      }
    }
  }

  if (!isset($bible[$normalBook][(string)$chapter])) return null;

  $chapterData = $bible[$normalBook][(string)$chapter];

  // Verses are 1-indexed, but array is 0-indexed
  // Also need to account for headings (items with 'h' key)
  $verseIndex = 0;
  $currentVerse = 0;

  foreach ($chapterData as $item) {
    if (isset($item['v'])) {
      $currentVerse++;
      if ($currentVerse === $verse) {
        return $item['v'];
      }
    }
  }

  return null;
}

/**
 * Get a range of verses
 */
function getVerseRange(string $lang, string $book, int $chapter, int $startVerse, int $endVerse): array {
  $verses = [];
  for ($v = $startVerse; $v <= $endVerse; $v++) {
    $text = getVerse($lang, $book, $chapter, $v);
    if ($text) {
      $verses[$v] = $text;
    }
  }
  return $verses;
}

/**
 * Parse Bible references from text
 * Returns array of [book, chapter, verse_start, verse_end]
 */
function parseBibleReferences(string $text): array {
  $refs = [];

  // Pattern to match Bible references like "Joh 3:16", "Johannes 3:16-18", "1 Kor 12:27", etc.
  $pattern = '/\b((?:\d\s*)?[A-Za-zëïöü]+(?:\s+[A-Za-zëïöü]+)?)\s*(\d{1,3})\s*[:v\.]\s*(\d{1,3})(?:\s*[-–]\s*(\d{1,3}))?\b/ui';

  if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
    foreach ($matches as $match) {
      $book = trim($match[1]);
      $chapter = (int)$match[2];
      $verseStart = (int)$match[3];
      $verseEnd = isset($match[4]) ? (int)$match[4] : $verseStart;

      // Validate book name
      if (normalizeBookName($book)) {
        $refs[] = [
          'book' => $book,
          'chapter' => $chapter,
          'verse_start' => $verseStart,
          'verse_end' => $verseEnd,
        ];
      }
    }
  }

  return $refs;
}

/**
 * Fetch all referenced verses and format them for the prompt
 */
function fetchReferencedVerses(string $text, string $lang): string {
  $refs = parseBibleReferences($text);
  if (empty($refs)) return '';

  $output = [];
  $usedLang = ($lang === 'af') ? 'af' : 'en'; // Default to English for other languages
  $langLabel = ($usedLang === 'af') ? '1933/1953 Afrikaans' : 'KJV 1611';

  foreach ($refs as $ref) {
    $verses = getVerseRange($usedLang, $ref['book'], $ref['chapter'], $ref['verse_start'], $ref['verse_end']);
    if (!empty($verses)) {
      $normalBook = normalizeBookName($ref['book']);
      $refStr = $normalBook . ' ' . $ref['chapter'] . ':' . $ref['verse_start'];
      if ($ref['verse_end'] > $ref['verse_start']) {
        $refStr .= '-' . $ref['verse_end'];
      }

      $versesText = [];
      foreach ($verses as $num => $text) {
        $versesText[] = $num . '. ' . $text;
      }

      $output[] = "[$refStr ($langLabel)]\n" . implode("\n", $versesText);
    }
  }

  return implode("\n\n", $output);
}

// ------------------------- SSE ROUTE ----------------------------------
if (isset($_GET['stream']) && $_GET['stream'] === '1') {
  // Plain text, at most 500 characters (the form allows no more)
  $q = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
  $q = mb_substr($q, 0, 500, 'UTF-8');
  $sbUserId = (int)($_SESSION['user_id'] ?? 0);
  // Nothing below writes the session: release it, so the member's other
  // pages (and the notification poll) are not held up while the answer streams
  session_write_close();

  header('Content-Type: text/event-stream');
  header('Cache-Control: no-cache');
  header('Connection: keep-alive');
  header('X-Accel-Buffering: no');

  @ini_set('output_buffering', 'off');
  @ini_set('zlib.output_compression', '0');
  @ini_set('implicit_flush', '1');
  while (ob_get_level() > 0) { @ob_end_flush(); }
  @ob_implicit_flush(1);

  $sse = function(string $event, string $data) {
    if ($event !== 'message') { echo "event: {$event}\n"; }
    foreach (preg_split("/\r?\n/", $data) as $ln) { echo 'data: ' . $ln . "\n"; }
    echo "\n";
    @flush();
  };

  if ($q === '') { $sse('error', t('empty_question')); $sse('done', 'end'); exit; }
  if (!$OPENAI_API_KEY) { $sse('error', t('missing_api_key')); $sse('done', 'end'); exit; }

  // Every question costs OpenAI credit: at most 20 per member per 10 minutes.
  // rate_limit_check() keeps a count for an hour, so the 10-minute window is
  // in the key (a fresh count each window). Fails open, like the login limiter.
  require_once __DIR__ . '/../security/rate_limit.php';
  if (!rate_limit_check('slimbybel:' . $sbUserId . ':' . intdiv(time(), 600), 20, 10)) {
    $sse('error', t('sb_err_rate')); $sse('done', 'end'); exit;
  }

  // =====================================================================
  // FETCH BIBLE VERSES FROM SERVER - STRICT word-for-word requirement
  // =====================================================================
  $referencedVerses = fetchReferencedVerses($q, $pageLang);
  $systemInstructions = fetchReferencedVerses($SYSTEM_PROMPT, $pageLang);
  $allVerses = trim($referencedVerses . "\n\n" . $systemInstructions);

  // Build strict verse instruction
  $verseInstruction = '';
  if ($allVerses) {
    $verseInstruction = "
=== AMPTELIKE BYBELVERSE VAN DIE SERVER (STRENG WOORD-VIR-WOORD) ===
Die volgende verse is direk vanaf die amptelike Bybellêers op die server gelaai.
Jy MOET hierdie presiese teks gebruik wanneer jy hierdie verse aanhaal.
MOENIE enige ander vertaling of bewoording gebruik nie.
MOENIE woorde byvoeg, weglaat of verander nie.

$allVerses

=== EINDE VAN AMPTELIKE VERSE ===
";
  }

  // Language-specific instructions - STRICT enforcement
  $langInstructions = [
    'af' => 'STRENG INSTRUKSIE:
1. Jy MOET alle antwoorde in SUIWER AFRIKAANS skryf - geen Engels of ander tale nie.
2. Wanneer jy Bybelverse aanhaal, gebruik SLEGS die 1933/1953 Afrikaanse vertaling WOORD-VIR-WOORD soos hierbo verskaf.
3. As \'n vers nie hierbo verskaf is nie, moet jy dit presies uit die 1933/1953 vertaling aanhaal - GEEN ander vertalings nie.
4. MOENIE verse parafraseer of in jou eie woorde stel nie - gebruik die presiese Bybelteks.
5. Elke woord in jou antwoord moet Afrikaans wees.',

    'en' => 'STRICT INSTRUCTION:
1. You MUST write all answers in PURE ENGLISH only - no other languages.
2. When quoting Bible verses, use ONLY the King James Version (KJV) 1611 WORD-FOR-WORD as provided above.
3. If a verse is not provided above, quote it exactly from the KJV 1611 - NO other translations.
4. DO NOT paraphrase or put verses in your own words - use the exact Bible text.
5. Every single word in your answer must be English.',

    'zu' => 'UMYALELO OQINILE: KUMELE ubhale zonke izimpendulo ngesi-ZULU KUPHELA. Sebenzisa i-King James Version (KJV) Bible njengoba inikeziwe ngenhla. UNGASEBENZISI amagama ngesiNgisi.',

    'xh' => 'UMYALELO ONGQONGQO: KUFUNEKA ubhale zonke iimpendulo ngesi-XHOSA KUPHELA. Sebenzisa i-King James Version (KJV) Bible njengoko inikezelwe ngentla. UNGAZE usebenzise amagama ngesiNgesi.',

    'pt' => 'INSTRUÇÃO ESTRITA: Você DEVE escrever todas as respostas em PORTUGUÊS PURO apenas. Use a versão King James (KJV) da Bíblia conforme fornecido acima. NUNCA use palavras em inglês.',

    'st' => 'TAELO E TIILENG: O TLAMEHA ho ngola likarabo tsohle ka SESOTHO SE HLOEKILENG feela. Sebelisa King James Version (KJV) ea Bibele joalo ka ha e fanoe ka holimo. O SE KE OA sebelisa mantsoe a Senyesemane.'
  ];

  $langInstruction = $langInstructions[$pageLang] ?? $langInstructions['en'];

  // Build messages with verse context
  $messages = [
    ['role' => 'system', 'content' => $SYSTEM_PROMPT],
  ];

  // Add verse context if available
  if ($verseInstruction) {
    $messages[] = ['role' => 'system', 'content' => $verseInstruction];
  }

  $messages[] = ['role' => 'system', 'content' => $langInstruction];
  $messages[] = ['role' => 'user', 'content' => $q];

  $payload = json_encode([
    'model'      => 'gpt-5.4-mini',
    'messages'   => $messages,
    'temperature'=> 0.2,
    'stream'     => true,
  ], JSON_UNESCAPED_SLASHES);

  $ch = curl_init('https://api.openai.com/v1/chat/completions');
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $OPENAI_API_KEY,
    'Content-Type: application/json',
  ]);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
  curl_setopt($ch, CURLOPT_TIMEOUT, 0);
  $sseBuffer = '';
  curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $chunk) use ($sse, &$sseBuffer) {
    $sseBuffer .= $chunk;
    // Only process complete lines (ending with \n)
    while (($pos = strpos($sseBuffer, "\n")) !== false) {
      $line = rtrim(substr($sseBuffer, 0, $pos), "\r");
      $sseBuffer = substr($sseBuffer, $pos + 1);
      if (strpos($line, 'data: ') === 0) {
        $json = trim(substr($line, 6));
        if ($json === '[DONE]') { $sse('done', 'end'); continue; }
        $obj = json_decode($json, true);
        if (isset($obj['choices'][0]['delta']['content'])) {
          $token = (string)$obj['choices'][0]['delta']['content'];
          if ($token !== '') { $sse('message', $token); }
        }
      }
    }
    return strlen($chunk);
  });

  $ok = curl_exec($ch);
  if ($ok === false) { $sse('error', 'Stream error: ' . curl_error($ch)); $sse('done', 'end'); }
  curl_close($ch);
  exit;
}

// -------------------------- HTML UI -----------------------------------
// "Light Through Glass", emerald edition: the welcome page's atmosphere lit
// in emerald, the colour AI Slimbybel carries on the welcome tiles.
function esc($s) { return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

/** Cache-bust by file time, so browsers keep the assets until they change */
function sb_asset(string $path): string {
  $mtime = @filemtime(dirname(__DIR__) . $path);
  return $path . '?v=' . ($mtime ?: '1');
}

// Example questions, each in its own jewel
$examples = [
  ['q' => 'water',   'accent' => 'sapphire', 'icon' => '<path d="M12 2.7l5.66 5.66a8 8 0 11-11.31 0z"/>'],
  ['q' => 'sower',   'accent' => 'emerald',  'icon' => '<path d="M6.05 8.05c-2.73 2.73-2.73 7.15-.02 9.88 1.47-3.4 4.09-6.24 7.36-7.93-2.77 2.34-4.71 5.61-5.39 9.32 2.6 1.23 5.8.78 7.95-1.37C19.43 14.47 20 4 20 4S9.53 4.57 6.05 8.05z"/>'],
  ['q' => 'spirit',  'accent' => 'amethyst', 'icon' => '<path d="M11.2 3h1.6v4.2h-1.6z"/><path d="M12.6 5.4c1-1.9 3-2.9 5.2-2.6-.7 2-2.6 3.3-5.2 2.6z"/><circle cx="7.4" cy="10" r="2.5"/><circle cx="12" cy="10" r="2.5"/><circle cx="16.6" cy="10" r="2.5"/><circle cx="9.7" cy="14.2" r="2.5"/><circle cx="14.3" cy="14.2" r="2.5"/><circle cx="12" cy="18.4" r="2.5"/>'],
  ['q' => 'kingdom', 'accent' => 'amber',    'icon' => '<path d="M3 7.5l4.6 3.9L12 4.5l4.4 6.9L21 7.5 19.2 18H4.8L3 7.5zM4.8 19.5h14.4V21H4.8z"/>'],
];

$sbConfig = [
  'max' => 500,
  't' => [
    'title'   => t('ai_slimbybel'),
    'copied'  => t('copied'),
    'copyFail'=> t('gd_copy_failed'),
    'error'   => t('sb_error'),
    'cutOff'  => t('sb_cut_off'),
  ],
];
?><!doctype html>
<html lang="<?= esc($pageLang) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= esc(t('ai_slimbybel')) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;1,500;1,600&family=Parisienne&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= sb_asset('/aislimbybel/css/aislimbybel.css') ?>">
  <script>window.SLIMBYBEL = <?= json_encode($sbConfig, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}' ?>;</script>
</head>
<body class="sb-body">
  <?php require_once __DIR__ . '/../header_footer/header.php'; ?>

  <svg class="sb-sprite" width="0" height="0" aria-hidden="true" focusable="false"><defs>
    <symbol id="sb-i-ask" viewBox="0 0 24 24" fill="currentColor"><path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/></symbol>
    <symbol id="sb-i-book" viewBox="0 0 24 24" fill="currentColor"><path d="M18 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM6 4h5v8l-2.5-1.5L6 12V4z"/></symbol>
    <?php foreach ($examples as $ex): ?>
    <symbol id="sb-i-<?= $ex['q'] ?>" viewBox="0 0 24 24" fill="currentColor"><?= $ex['icon'] ?></symbol>
    <?php endforeach; ?>
  </defs></svg>

  <!-- Atmosphere: rays and a halo of emerald light (purely decorative) -->
  <div class="sb-aura" aria-hidden="true"><div class="sb-rays"></div><div class="sb-halo"></div></div>

  <main class="sb-main" id="sbMain">
    <section class="sb-hero" aria-labelledby="sbTitle">
      <p class="sb-eyebrow">
        <svg class="sb-eyebrow-icon" viewBox="0 0 24 24" aria-hidden="true"><use href="#sb-i-ask"/></svg>
        <span><?= esc(t('ask_scripture')) ?></span>
      </p>
      <h2 class="sb-title" id="sbTitle"><span><?= esc(t('ai_slimbybel')) ?></span></h2>
      <p class="sb-tagline"><?= esc(t('spiritual_wisdom_ai')) ?></p>
      <p class="sb-sub"><?= esc(t('spiritual_interpretation_help')) ?></p>
    </section>

    <!-- The question -->
    <form id="sbForm" class="sb-card sb-ask" action="#" method="get" role="search">
      <label class="sb-ask-label" for="q_input">
        <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#sb-i-book"/></svg>
        <span><?= esc(t('ask_scripture')) ?></span>
      </label>
      <textarea id="q_input" name="q" class="sb-input" rows="2" maxlength="500"
                autocomplete="off" enterkeyhint="send"
                placeholder="<?= esc(t('ask_placeholder')) ?>"></textarea>
      <div class="sb-ask-foot">
        <p class="sb-ask-note"><?= esc(t('sb_ai_note')) ?></p>
        <button class="sb-btn sb-btn--primary" id="askBtn" type="submit">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg>
          <span><?= esc(t('explain')) ?></span>
        </button>
      </div>
    </form>

    <!-- The answer (shown from the first question on) -->
    <section class="sb-answer-section" id="sbAnswerSection" aria-labelledby="sbAnswerLabel" hidden>
      <h3 class="sb-label" id="sbAnswerLabel"><?= esc(t('answer')) ?></h3>
      <article class="sb-card sb-answer" id="answerBox">
        <p class="sb-answer-q" id="sbQuestion"></p>
        <div class="sb-answer-content" id="answerContent" aria-live="polite" aria-busy="false"></div>
        <div class="sb-typing" id="loadingIndicator" hidden>
          <i></i><i></i><i></i>
          <span class="sb-sr"><?= esc(t('loading')) ?></span>
        </div>
        <p class="sb-error" id="sbError" role="alert" hidden></p>
        <div class="sb-answer-actions" id="sbActions" hidden>
          <button class="sb-btn sb-btn--glass" id="sbStop" type="button" hidden>
            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="6" y="6" width="12" height="12" rx="2" fill="currentColor"/></svg>
            <span><?= esc(t('sb_stop')) ?></span>
          </button>
          <button class="sb-btn sb-btn--glass" id="sbCopy" type="button" hidden>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 4h10a2 2 0 012 2v12M16 8H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2V10a2 2 0 00-2-2z"/></svg>
            <span><?= esc(t('copy')) ?></span>
          </button>
          <button class="sb-btn sb-btn--glass" id="sbShare" type="button" hidden>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 12v7a2 2 0 002 2h12a2 2 0 002-2v-7M16 6l-4-4-4 4M12 2v13"/></svg>
            <span><?= esc(t('share')) ?></span>
          </button>
          <button class="sb-btn sb-btn--glass" id="sbAgain" type="button" hidden>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9M16.5 3.5a2.12 2.12 0 013 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>
            <span><?= esc(t('sb_new_question')) ?></span>
          </button>
        </div>
      </article>
    </section>

    <!-- Example questions -->
    <section class="sb-examples" aria-labelledby="sbExamplesLabel">
      <h3 class="sb-label" id="sbExamplesLabel"><?= esc(t('example_questions')) ?></h3>
      <div class="sb-ex-grid">
        <?php foreach ($examples as $i => $ex): ?>
          <button class="sb-ex sb-ex--<?= $ex['accent'] ?>" type="button" style="--i:<?= $i ?>"
                  data-question="<?= esc(t($ex['q'] . '_question_full')) ?>">
            <span class="sb-ex-orb"><svg viewBox="0 0 24 24" aria-hidden="true"><use href="#sb-i-<?= $ex['q'] ?>"/></svg></span>
            <span class="sb-ex-text"><?= esc(t($ex['q'] . '_question_short')) ?></span>
            <svg class="sb-ex-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg>
          </button>
        <?php endforeach; ?>
      </div>
    </section>
  </main>

  <div class="sb-toast" id="sbToast" role="status" aria-live="polite"></div>

  <script defer src="<?= sb_asset('/aislimbybel/js/aislimbybel.js') ?>"></script>

  <?php require_once __DIR__ . '/../header_footer/footer.php'; ?>

</body>
</html>
