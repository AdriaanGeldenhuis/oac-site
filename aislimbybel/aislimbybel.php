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
  $q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

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
5. Elke woord in jou antwoord moet Afrikaans wees.
6. MOENIE OOIT woorde afsny, verkort of saamsmelt nie. Elke woord moet VOLLEDIG uitgeskryf word. Bv. skryf "Korintiërs" NIE "Korië" nie, skryf "afsonderlik lede" NIE "aflikde" nie, skryf "Gees" NIE "Ge" nie.
7. MOENIE twee woorde aanmekaar skryf sonder spasie nie (bv. "afsonderlikEfesiërs" is VERKEERD, moet wees "afsonderlik. Efesiërs").
8. Skryf Bybelboekname ALTYD volledig uit: "Korintiërs" nie "Kor" nie, "Efesiërs" nie "Ef" nie, "Tessalonisense" nie "Tess" nie, "Romeine" nie "Rom" nie.',

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
    'model'      => 'gpt-4o-mini',
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
  curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $chunk) use ($sse) {
    $lines = preg_split("/\r?\n/", $chunk);
    foreach ($lines as $line) {
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
function esc($s) { return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?><!doctype html>
<html lang="<?= $pageLang ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= esc(t('ai_slimbybel')) ?></title>
  
  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <style>
    @font-face {
      font-family: 'Parisienne';
      src: url('/assets/fonts/Parisienne-Regular.ttf') format('truetype');
      font-weight: normal;
      font-style: normal;
      font-display: swap;
    }
  </style>
  
  <link rel="stylesheet" href="/aislimbybel/css/aislimbybel.css?v=<?= time() ?>">
</head>
<body class="aislimbybel-body">
  <?php require_once __DIR__ . '/../header_footer/header.php'; ?>

  <main class="sb-main">
    <!-- Search Section -->
    <section class="sb-section sb-search-section">
      <h2 class="sb-page-title">AI Slimbybel</h2>
      <div class="sb-section-header">
        <div class="sb-icon-wrapper">
          <svg class="sb-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="2"/>
            <path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </div>
        <h2 class="sb-section-title"><?= esc(t('ask_scripture')) ?></h2>
      </div>

      <form id="sbForm" class="sb-form" action="#" method="get">
        <div class="sb-input-group">
          <input 
            type="text" 
            id="q_input" 
            name="q" 
            class="sb-input" 
            autocomplete="off" 
            placeholder="<?= esc(t('ask_placeholder')) ?>"
          >
          <button class="sb-btn sb-btn-primary" id="askBtn" type="submit">
            <span class="sb-btn-shine"></span>
            <svg class="sb-btn-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <span class="sb-btn-text"><?= esc(t('explain')) ?></span>
          </button>
        </div>
      </form>

      <div class="sb-help">
        <svg class="sb-help-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.5"/>
          <path d="M12 16v.01M12 8v5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
        </svg>
        <p class="sb-help-text"><?= esc(t('spiritual_interpretation_help')) ?></p>
      </div>
    </section>

    <!-- Answer Section -->
    <section class="sb-section sb-answer-section">
      <div class="sb-section-header">
        <div class="sb-icon-wrapper">
          <svg class="sb-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M12 2L2 7v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V7l-8-5z" stroke="currentColor" stroke-width="1.5" fill="none"/>
            <path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>
        <h2 class="sb-section-title"><?= esc(t('answer')) ?></h2>
        <div class="sb-loading" id="loadingIndicator" hidden>
          <div class="sb-spinner"></div>
        </div>
      </div>

      <div class="sb-answer-panel">
        <div class="sb-answer-scroll" id="answerBox">
          <div class="sb-placeholder" id="placeholder">
            <svg class="sb-placeholder-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z" fill="currentColor" opacity="0.3"/>
            </svg>
            <p><?= esc(t('answer_placeholder')) ?></p>
          </div>
          <div class="sb-answer-content" id="answerContent" hidden></div>
        </div>
      </div>
    </section>

    <!-- Examples Section -->
    <section class="sb-section sb-examples-section">
      <h3 class="sb-examples-title"><?= esc(t('example_questions')) ?></h3>
      <div class="sb-examples-grid">
        <button class="sb-example-card" data-question="<?= esc(t('water_question_full')) ?>">
          <div class="sb-example-icon">💧</div>
          <p class="sb-example-text"><?= esc(t('water_question_short')) ?></p>
          <div class="sb-card-shine"></div>
        </button>
        <button class="sb-example-card" data-question="<?= esc(t('sower_question_full')) ?>">
          <div class="sb-example-icon">🌱</div>
          <p class="sb-example-text"><?= esc(t('sower_question_short')) ?></p>
          <div class="sb-card-shine"></div>
        </button>
        <button class="sb-example-card" data-question="<?= esc(t('spirit_question_full')) ?>">
          <div class="sb-example-icon">🍇</div>
          <p class="sb-example-text"><?= esc(t('spirit_question_short')) ?></p>
          <div class="sb-card-shine"></div>
        </button>
        <button class="sb-example-card" data-question="<?= esc(t('kingdom_question_full')) ?>">
          <div class="sb-example-icon">👑</div>
          <p class="sb-example-text"><?= esc(t('kingdom_question_short')) ?></p>
          <div class="sb-card-shine"></div>
        </button>
      </div>
    </section>
  </main>

  <script src="/aislimbybel/js/aislimbybel.js?v=<?= time() ?>"></script>

  <?php require_once __DIR__ . '/../header_footer/footer.php'; ?>

</body>
</html>