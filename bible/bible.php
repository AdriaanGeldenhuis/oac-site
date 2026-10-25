<?php
require_once __DIR__ . '/../security/auth_gate.php';
require_once __DIR__ . '/../includes/languages.php';

$pageLang = $_SESSION['language'] ?? 'af';
if (!in_array($pageLang, SUPPORTED_LANGS, true)) {
  $pageLang = 'af';
}
if (isset($_GET['lang']) && in_array($_GET['lang'], SUPPORTED_LANGS, true)) {
  $_SESSION['language'] = $_GET['lang'];
  $pageLang = $_GET['lang'];
  header('Location: /bible/bible.php');
  exit;
}

// Translation helper using central 5-language system
function t(string $key): string {
    global $pageLang;
    return __t($key, $pageLang);
}
function esc($s) { return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

// Cache-bust by file time
function bible_asset(string $rel): string {
  return '/bible/' . $rel . '?v=' . (@filemtime(__DIR__ . '/' . $rel) ?: '1');
}

// Shared stroke icons (24x24, currentColor)
$ico = [
  'search'   => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>',
  'note'     => '<path d="M12 20h9M16.5 3.5a2.12 2.12 0 013 3L7 19l-4 1 1-4 12.5-12.5z"/>',
  'bookmark' => '<path d="M19 21l-7-5-7 5V5a2 2 0 012-2h10a2 2 0 012 2z"/>',
  'book'     => '<path d="M4 19.5A2.5 2.5 0 016.5 17H20V3H6.5A2.5 2.5 0 004 5.5v14z"/><path d="M4 19.5A2.5 2.5 0 006.5 22H20v-5"/>',
  'close'    => '<path d="M6 6l12 12M18 6L6 18"/>',
  'chevron'  => '<path d="M6 9l6 6 6-6"/>',
  'back'     => '<path d="M15 18l-6-6 6-6"/>',
  'spark'    => '<path d="M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8z"/><path d="M19 17l.7 2.3L22 20l-2.3.7L19 23l-.7-2.3L16 20l2.3-.7z"/>',
  'link'     => '<path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71"/>',
  'copy'     => '<rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/>',
  'share'    => '<path d="M4 12v7a1 1 0 001 1h14a1 1 0 001-1v-7M16 6l-4-4-4 4M12 2v13"/>',
];
function icon(string $name, string $cls = ''): string {
  global $ico;
  return '<svg' . ($cls ? ' class="' . $cls . '"' : '') . ' viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $ico[$name] . '</svg>';
}

$bibleData = [
  'lang' => $pageLang,
  'paths' => [
    'af' => bible_asset('bibles/af_1933_53.json'),
    'en' => bible_asset('bibles/en_kjv1611.json'),
  ],
  'userId' => (int)($_SESSION['user_id'] ?? 0),
  'csrfToken' => csrf_token(),
  'i18n' => [
    'bible' => t('bible'),
    'copied' => t('copied'),
    'remove' => t('remove'),
    'cancel' => t('cancel'),
    'bookmark' => t('bookmark'),
  ],
];
?><!doctype html>
<html lang="<?= esc($pageLang) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= esc(t('bible_reader')) ?></title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;1,500&family=Parisienne&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="<?= bible_asset('css/bible.css') ?>">
</head>
<body class="bible-body">
  <?php require_once __DIR__ . '/../header_footer/header.php'; ?>

  <!-- Atmosphere: candlelight falling on the page (purely decorative) -->
  <div class="bible-aura" aria-hidden="true"><div class="bible-rays"></div><div class="bible-halo"></div></div>

  <!-- How far into the current chapter you are -->
  <div class="bible-progress" aria-hidden="true"><i id="bibleProgress"></i></div>

  <!-- Quick Navigation -->
  <div class="bible-modal bible-modal-hidden" id="quickNavModal" role="dialog" aria-modal="true" aria-labelledby="quickNavTitle">
    <div class="bible-modal-overlay" id="quickNavOverlay"></div>
    <div class="bible-modal-content" tabindex="-1">
      <div class="bible-modal-header">
        <h2 class="bible-modal-title" id="quickNavTitle"><?= esc(t('quick_navigation')) ?></h2>
        <button class="bible-modal-close" id="quickNavClose" type="button" aria-label="<?= esc(t('close')) ?>"><?= icon('close') ?></button>
      </div>
      <div class="bible-modal-body">

        <!-- Step 1: Testament Selection -->
        <div class="bible-nav-step" id="navStepTestament">
          <h3 class="bible-nav-step-title"><?= esc(t('choose_testament')) ?></h3>
          <div class="bible-nav-grid bible-nav-grid-testament">
            <button class="bible-nav-card bible-nav-card--old" type="button" data-testament="old">
              <span class="bible-nav-card-icon"><?= icon('book') ?></span>
              <span class="bible-nav-card-title"><?= esc(t('old_testament')) ?></span>
              <span class="bible-nav-card-subtitle"><?= esc(t('genesis_malachi')) ?></span>
            </button>
            <button class="bible-nav-card bible-nav-card--new" type="button" data-testament="new">
              <span class="bible-nav-card-icon"><?= icon('spark') ?></span>
              <span class="bible-nav-card-title"><?= esc(t('new_testament')) ?></span>
              <span class="bible-nav-card-subtitle"><?= esc(t('matthew_revelation')) ?></span>
            </button>
          </div>
        </div>

        <!-- Step 2: Book Selection -->
        <div class="bible-nav-step bible-nav-hidden" id="navStepBook">
          <button class="bible-nav-back" id="navBackToTestament" type="button"><?= icon('back') ?><span><?= esc(t('back')) ?></span></button>
          <h3 class="bible-nav-step-title" id="navBookTitle"><?= esc(t('choose_book')) ?></h3>
          <label class="bible-nav-filter">
            <?= icon('search') ?>
            <input type="search" id="navBookFilter" placeholder="<?= esc(t('search')) ?>" aria-label="<?= esc(t('search')) ?>" autocomplete="off">
          </label>
          <div class="bible-nav-grid" id="navBookGrid"></div>
        </div>

        <!-- Step 3: Chapter Selection -->
        <div class="bible-nav-step bible-nav-hidden" id="navStepChapter">
          <button class="bible-nav-back" id="navBackToBook" type="button"><?= icon('back') ?><span><?= esc(t('back')) ?></span></button>
          <h3 class="bible-nav-step-title" id="navChapterTitle"><?= esc(t('choose_chapter')) ?></h3>
          <div class="bible-nav-grid bible-nav-grid-small" id="navChapterGrid"></div>
        </div>

      </div>
    </div>
  </div>

  <main class="bible-main">

    <!-- Main Reading View (Dual Columns) -->
    <section class="bible-reading-section" id="bibleReading">
      <div class="bible-dual-container">
        <div class="bible-column bible-column-left" id="leftColumn">
          <div class="bible-column-content" id="leftContent">
            <div class="bible-loading"><?= esc(t('loading')) ?></div>
          </div>
        </div>
        <div class="bible-column bible-column-right" id="rightColumn">
          <div class="bible-column-content" id="rightContent">
            <div class="bible-loading"><?= esc(t('loading')) ?></div>
          </div>
        </div>
      </div>
    </section>

  </main>

  <!-- Panels and sheets live outside <main> so they can sit above the toolbar -->

    <!-- Search Panel -->
    <section class="bible-panel bible-panel-hidden" id="searchPanel" aria-labelledby="searchPanelTitle">
      <div class="bible-panel-header">
        <h3 class="bible-panel-title" id="searchPanelTitle"><?= esc(t('search_bible')) ?></h3>
        <button class="bible-panel-close" id="searchClose" type="button" aria-label="<?= esc(t('close')) ?>"><?= icon('close') ?></button>
      </div>
      <div class="bible-panel-body">
        <div class="bible-search-container">
          <input type="search" id="searchInput" class="bible-input" placeholder="<?= esc(t('type_search_term')) ?>" aria-label="<?= esc(t('search_bible')) ?>" autocomplete="off" enterkeyhint="search">
          <button class="bible-btn bible-btn-primary" id="searchBtn" type="button" aria-label="<?= esc(t('search')) ?>">
            <?= icon('search', 'bible-btn-icon') ?>
          </button>
        </div>
        <div id="searchResults" class="bible-search-results" aria-live="polite"></div>
      </div>
    </section>

    <!-- Notes Panel -->
    <section class="bible-panel bible-panel-hidden" id="notesPanel" aria-labelledby="notesPanelTitle">
      <div class="bible-panel-header">
        <h3 class="bible-panel-title" id="notesPanelTitle"><?= esc(t('notes')) ?></h3>
        <button class="bible-panel-close" id="notesClose" type="button" aria-label="<?= esc(t('close')) ?>"><?= icon('close') ?></button>
      </div>
      <div class="bible-panel-body">
        <div id="notesList" class="bible-notes-list">
          <p class="bible-empty-state"><?= esc(t('no_notes_yet')) ?></p>
        </div>
        <div id="noteEditor" class="bible-note-editor bible-note-hidden">
          <div class="bible-note-ref" id="noteReference"></div>
          <textarea id="noteText" class="bible-textarea" placeholder="<?= esc(t('write_note_here')) ?>" maxlength="10000"></textarea>
          <div class="bible-note-actions">
            <button class="bible-btn bible-btn-primary" id="saveNoteBtn" type="button"><?= esc(t('save')) ?></button>
            <button class="bible-btn" id="cancelNoteBtn" type="button"><?= esc(t('cancel')) ?></button>
          </div>
        </div>
      </div>
    </section>

    <!-- Bookmarks Panel -->
    <section class="bible-panel bible-panel-hidden" id="bookmarksPanel" aria-labelledby="bookmarksPanelTitle">
      <div class="bible-panel-header">
        <h3 class="bible-panel-title" id="bookmarksPanelTitle"><?= esc(t('bookmarks')) ?></h3>
        <button class="bible-panel-close" id="bookmarksClose" type="button" aria-label="<?= esc(t('close')) ?>"><?= icon('close') ?></button>
      </div>
      <div class="bible-panel-body">
        <div id="bookmarksList" class="bible-bookmarks-list">
          <p class="bible-empty-state"><?= esc(t('no_bookmarks_yet')) ?></p>
        </div>
      </div>
    </section>

    <!-- AI Commentary Panel -->
    <section class="bible-panel bible-panel-hidden" id="aiPanel" aria-labelledby="aiPanelTitle">
      <div class="bible-panel-header">
        <h3 class="bible-panel-title" id="aiPanelTitle"><?= esc(t('ai_commentary')) ?></h3>
        <button class="bible-panel-close" id="aiClose" type="button" aria-label="<?= esc(t('close')) ?>"><?= icon('close') ?></button>
      </div>
      <div class="bible-panel-body">
        <div id="aiOutput" class="bible-ai-output" aria-live="polite">
          <p class="bible-empty-state"><?= esc(t('select_verse_ai')) ?></p>
        </div>
      </div>
    </section>

    <!-- Cross References Panel -->
    <section class="bible-panel bible-panel-hidden" id="crossRefPanel" aria-labelledby="crossRefPanelTitle">
      <div class="bible-panel-header">
        <h3 class="bible-panel-title" id="crossRefPanelTitle"><?= esc(t('cross_references')) ?></h3>
        <button class="bible-panel-close" id="crossRefClose" type="button" aria-label="<?= esc(t('close')) ?>"><?= icon('close') ?></button>
      </div>
      <div class="bible-panel-body">
        <div id="crossRefList" class="bible-cross-ref-list">
          <p class="bible-empty-state"><?= esc(t('select_verse_cross_ref')) ?></p>
        </div>
      </div>
    </section>

    <!-- Verse actions: a sheet on phones, a card on wider screens -->
    <div class="bible-sheet-scrim bible-context-hidden" id="verseScrim"></div>
    <div id="verseContextMenu" class="bible-context-menu bible-context-hidden" role="dialog" aria-modal="true" aria-labelledby="ctxRef" tabindex="-1">
      <div class="bible-context-head">
        <div class="bible-context-head-text">
          <div class="bible-context-ref" id="ctxRef"></div>
          <p class="bible-context-preview" id="ctxPreview"></p>
        </div>
        <button class="bible-panel-close" id="ctxClose" type="button" aria-label="<?= esc(t('close')) ?>"><?= icon('close') ?></button>
      </div>

      <div class="bible-context-section-title"><?= esc(t('choose_color')) ?></div>
      <div class="bible-highlight-colors">
        <button class="bible-color-btn bible-color-1" type="button" data-color="1" title="<?= esc(t('pink')) ?>" aria-label="<?= esc(t('pink')) ?>"></button>
        <button class="bible-color-btn bible-color-2" type="button" data-color="2" title="<?= esc(t('orange')) ?>" aria-label="<?= esc(t('orange')) ?>"></button>
        <button class="bible-color-btn bible-color-3" type="button" data-color="3" title="<?= esc(t('yellow')) ?>" aria-label="<?= esc(t('yellow')) ?>"></button>
        <button class="bible-color-btn bible-color-4" type="button" data-color="4" title="<?= esc(t('green')) ?>" aria-label="<?= esc(t('green')) ?>"></button>
        <button class="bible-color-btn bible-color-5" type="button" data-color="5" title="<?= esc(t('blue')) ?>" aria-label="<?= esc(t('blue')) ?>"></button>
        <button class="bible-color-btn bible-color-6" type="button" data-color="6" title="<?= esc(t('purple')) ?>" aria-label="<?= esc(t('purple')) ?>"></button>
        <button class="bible-color-btn bible-color-btn-clear" type="button" data-color="0" title="<?= esc(t('remove')) ?>" aria-label="<?= esc(t('remove')) ?>"><?= icon('close') ?></button>
      </div>

      <div class="bible-context-grid">
        <button class="bible-context-item" id="ctxBookmark" type="button"><?= icon('bookmark') ?><span><?= esc(t('bookmark')) ?></span></button>
        <button class="bible-context-item" id="ctxAddNote" type="button"><?= icon('note') ?><span><?= esc(t('add_note')) ?></span></button>
        <button class="bible-context-item" id="ctxAI" type="button"><?= icon('spark') ?><span><?= esc(t('ask_ai')) ?></span></button>
        <button class="bible-context-item" id="ctxCrossRef" type="button"><?= icon('link') ?><span><?= esc(t('cross_refs')) ?></span></button>
        <button class="bible-context-item" id="ctxCopy" type="button"><?= icon('copy') ?><span><?= esc(t('copy')) ?></span></button>
        <button class="bible-context-item" id="ctxShare" type="button"><?= icon('share') ?><span><?= esc(t('share')) ?></span></button>
      </div>
    </div>

    <!-- Reading comfort: text size and typeface -->
    <div class="bible-display bible-context-hidden" id="displayPanel" role="dialog" aria-labelledby="displayTitle">
      <div class="bible-display-title" id="displayTitle"><?= esc(t('font')) ?></div>
      <div class="bible-display-row">
        <button class="bible-display-btn" id="fontSizeDecrease" type="button" title="<?= esc(t('decrease')) ?>" aria-label="<?= esc(t('decrease')) ?>"><span class="bible-display-a-sm">A</span></button>
        <div class="bible-display-steps" id="fontSizeSteps" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></div>
        <button class="bible-display-btn" id="fontSizeIncrease" type="button" title="<?= esc(t('increase')) ?>" aria-label="<?= esc(t('increase')) ?>"><span class="bible-display-a-lg">A</span></button>
      </div>
      <div class="bible-display-row bible-display-faces" role="group" aria-label="<?= esc(t('font')) ?>">
        <button class="bible-face-btn bible-face-btn--serif" type="button" data-face="serif" aria-pressed="false">Aa<small>Serif</small></button>
        <button class="bible-face-btn bible-face-btn--sans" type="button" data-face="sans" aria-pressed="false">Aa<small>Sans</small></button>
      </div>
    </div>


  <!-- Toolbar: search, notes, where you are (tap to go elsewhere), bookmarks, display -->
  <nav class="bible-toolbar" aria-label="<?= esc(t('bible')) ?>">
    <button class="bible-tool-btn" id="searchToggle" type="button" title="<?= esc(t('search')) ?>" aria-label="<?= esc(t('search')) ?>"><?= icon('search') ?></button>
    <button class="bible-tool-btn" id="notesToggle" type="button" title="<?= esc(t('notes')) ?>" aria-label="<?= esc(t('notes')) ?>"><?= icon('note') ?></button>

    <button class="bible-tool-btn bible-tool-btn-primary" id="quickNavToggle" type="button" title="<?= esc(t('navigate')) ?>" aria-label="<?= esc(t('navigate')) ?>" aria-haspopup="dialog">
      <span class="bible-ref-orb"><?= icon('book') ?></span>
      <span class="bible-ref-label" id="bibleRefLabel"><?= esc(t('bible')) ?></span>
      <?= icon('chevron', 'bible-ref-chev') ?>
    </button>

    <button class="bible-tool-btn" id="bookmarksToggle" type="button" title="<?= esc(t('bookmarks')) ?>" aria-label="<?= esc(t('bookmarks')) ?>"><?= icon('bookmark') ?></button>
    <button class="bible-tool-btn bible-tool-btn-text" id="displayToggle" type="button" title="<?= esc(t('font')) ?>" aria-label="<?= esc(t('font')) ?>" aria-expanded="false" aria-controls="displayPanel"><span>Aa</span></button>
  </nav>

  <div class="bible-toast" id="bibleToast" role="status" aria-live="polite"></div>

  <script>
    window.BIBLE = <?= json_encode($bibleData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  </script>
  <script type="module" src="<?= bible_asset('js/bible.js') ?>"></script>

</body>
</html>
