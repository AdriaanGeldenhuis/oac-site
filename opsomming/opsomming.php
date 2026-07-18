<?php
// =====================================================================
// /opsomming/opsomming.php — Bybel Opsomming (Bible Summary)
// Boek → 6 Dele → Studies met geestelike verklaring
// =====================================================================
require_once __DIR__ . '/../security/auth_gate.php';
require_once __DIR__ . '/../includes/languages.php';

$pageLang = $_SESSION['language'] ?? 'af';
if (isset($_GET['lang']) && in_array($_GET['lang'], SUPPORTED_LANGS, true)) {
  $_SESSION['language'] = $_GET['lang'];
  $pageLang = $_GET['lang'];
  header('Location: /opsomming/opsomming.php');
  exit;
}

// Translation helper using central system
function t(string $key): string {
    global $pageLang;
    return __t($key, $pageLang);
}
function esc($s) { return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

// ---------------------------------------------------------------------
// Content resolution: try the user's language, fall back to Afrikaans.
// Summaries are authored in Afrikaans first and translated over time.
// ---------------------------------------------------------------------
$book = 'genesis';
$dataLang = $pageLang;
$dataFile = __DIR__ . '/data/' . $dataLang . '/' . $book . '.json';
if (!is_readable($dataFile)) {
  $dataLang = 'af';
  $dataFile = __DIR__ . '/data/af/' . $book . '.json';
}
$dataUrl = '/opsomming/data/' . $dataLang . '/' . $book . '.json?v=' . (is_readable($dataFile) ? filemtime($dataFile) : time());

// ---------------------------------------------------------------------
// Bible books (Afrikaans 1933/53 names + English names).
// Only books listed in $availableBooks have summary content so far.
// ---------------------------------------------------------------------
$booksOT = [
  ['genesis','Génesis','Genesis'], ['exodus','Éxodus','Exodus'], ['levitikus','Levítikus','Leviticus'],
  ['numeri','Númeri','Numbers'], ['deuteronomium','Deuteronómium','Deuteronomy'], ['josua','Josua','Joshua'],
  ['rigters','Rigters','Judges'], ['rut','Rut','Ruth'], ['1samuel','1 Samuel','1 Samuel'],
  ['2samuel','2 Samuel','2 Samuel'], ['1konings','1 Konings','1 Kings'], ['2konings','2 Konings','2 Kings'],
  ['1kronieke','1 Kronieke','1 Chronicles'], ['2kronieke','2 Kronieke','2 Chronicles'], ['esra','Esra','Ezra'],
  ['nehemia','Nehemía','Nehemiah'], ['ester','Ester','Esther'], ['job','Job','Job'],
  ['psalms','Psalms','Psalms'], ['spreuke','Spreuke','Proverbs'], ['prediker','Prediker','Ecclesiastes'],
  ['hooglied','Hooglied','Song of Solomon'], ['jesaja','Jesaja','Isaiah'], ['jeremia','Jeremia','Jeremiah'],
  ['klaagliedere','Klaagliedere','Lamentations'], ['esegiel','Eségiël','Ezekiel'], ['daniel','Daniël','Daniel'],
  ['hosea','Hoséa','Hosea'], ['joel','Joël','Joel'], ['amos','Amos','Amos'],
  ['obadja','Obadja','Obadiah'], ['jona','Jona','Jonah'], ['miga','Miga','Micah'],
  ['nahum','Nahum','Nahum'], ['habakuk','Hábakuk','Habakkuk'], ['sefanja','Sefánja','Zephaniah'],
  ['haggai','Haggai','Haggai'], ['sagaria','Sagaría','Zechariah'], ['maleagi','Maleági','Malachi'],
];
$booksNT = [
  ['mattheus','Matthéüs','Matthew'], ['markus','Markus','Mark'], ['lukas','Lukas','Luke'],
  ['johannes','Johannes','John'], ['handelinge','Handelinge','Acts'], ['romeine','Romeine','Romans'],
  ['1korinthiers','1 Korinthiërs','1 Corinthians'], ['2korinthiers','2 Korinthiërs','2 Corinthians'],
  ['galasiers','Galásiërs','Galatians'], ['efesiers','Efésiërs','Ephesians'], ['filippense','Filippense','Philippians'],
  ['kolossense','Kolossense','Colossians'], ['1thessalonicense','1 Thessalonicense','1 Thessalonians'],
  ['2thessalonicense','2 Thessalonicense','2 Thessalonians'], ['1timotheus','1 Timótheüs','1 Timothy'],
  ['2timotheus','2 Timótheüs','2 Timothy'], ['titus','Titus','Titus'], ['filemon','Filémon','Philemon'],
  ['hebreers','Hebreërs','Hebrews'], ['jakobus','Jakobus','James'], ['1petrus','1 Petrus','1 Peter'],
  ['2petrus','2 Petrus','2 Peter'], ['1johannes','1 Johannes','1 John'], ['2johannes','2 Johannes','2 John'],
  ['3johannes','3 Johannes','3 John'], ['judas','Judas','Jude'], ['openbaring','Openbaring','Revelation'],
];
$availableBooks = ['genesis'];

// Book display name index: Afrikaans names for af, English otherwise
$nameIdx = ($pageLang === 'af') ? 1 : 2;
$mapBooks = function(array $list) use ($nameIdx) {
  return array_map(fn($b) => ['id' => $b[0], 'name' => $b[$nameIdx]], $list);
};

// UI strings handed to the client-side renderer
$jsKeys = [
  'bible_summary', 'bible_summary_tagline', 'choose_book', 'old_testament', 'new_testament',
  'back', 'loading', 'search', 'sum_available', 'sum_coming_soon', 'sum_part', 'sum_parts',
  'sum_studies_label', 'sum_study_label', 'sum_the_story', 'sum_spiritual_meaning',
  'sum_scripture', 'sum_application', 'sum_about_doc', 'sum_symbol_key',
  'sum_af_only', 'sum_continue_reading', 'sum_start_reading', 'sum_mark_read',
  'sum_marked_read', 'sum_next', 'sum_prev', 'sum_read_thread',
  'sum_search_placeholder', 'sum_no_results', 'sum_books_available',
];
$jsT = [];
foreach ($jsKeys as $k) { $jsT[$k] = t($k); }

$VER_CSS = filemtime(__DIR__ . '/css/opsomming.css');
$VER_JS  = filemtime(__DIR__ . '/js/opsomming.js');
?><!doctype html>
<html lang="<?= esc($pageLang) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= esc(t('bible_summary')) ?></title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Parisienne&family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="/opsomming/css/opsomming.css?v=<?= $VER_CSS ?>">
</head>
<body class="ops-body">
  <?php require_once __DIR__ . '/../header_footer/header.php'; ?>

  <main class="ops-main">
    <div class="ops-app" id="opsApp">
      <div class="ops-loading">
        <div class="ops-loading-book"><span></span></div>
        <p><?= esc(t('loading')) ?></p>
      </div>
    </div>
  </main>

  <script>
    window.OPSOMMING = {
      lang: <?= json_encode($pageLang) ?>,
      dataLang: <?= json_encode($dataLang) ?>,
      dataUrl: <?= json_encode($dataUrl) ?>,
      book: <?= json_encode($book) ?>,
      available: <?= json_encode($availableBooks) ?>,
      books: {
        ot: <?= json_encode($mapBooks($booksOT), JSON_UNESCAPED_UNICODE) ?>,
        nt: <?= json_encode($mapBooks($booksNT), JSON_UNESCAPED_UNICODE) ?>
      },
      t: <?= json_encode($jsT, JSON_UNESCAPED_UNICODE) ?>
    };
  </script>
  <script src="/opsomming/js/opsomming.js?v=<?= $VER_JS ?>"></script>
</body>
</html>
