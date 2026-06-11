<?php
require_once __DIR__ . '/../security/auth_gate.php';
require_once __DIR__ . '/../includes/languages.php';

$pageLang = $_SESSION['language'] ?? 'af';
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
?><!doctype html>
<html lang="<?= $pageLang ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= esc(t('bible_reader')) ?></title>
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Parisienne&display=swap" rel="stylesheet">
  
  <link rel="stylesheet" href="/bible/css/bible.css?v=<?= filemtime(__DIR__ . '/css/bible.css') ?>">
</head>
<body class="bible-body">
  <?php require_once __DIR__ . '/../header_footer/header.php'; ?>

  <!-- Quick Navigation Modal (Fullscreen) -->
  <div class="bible-modal bible-modal-hidden" id="quickNavModal">
    <div class="bible-modal-overlay" id="quickNavOverlay"></div>
    <div class="bible-modal-content">
      <div class="bible-modal-header">
        <h2 class="bible-modal-title"><?= esc(t('quick_navigation')) ?></h2>
        <button class="bible-modal-close" id="quickNavClose">×</button>
      </div>
      <div class="bible-modal-body">

        <!-- Step 1: Testament Selection -->
        <div class="bible-nav-step" id="navStepTestament">
          <h3 class="bible-nav-step-title"><?= esc(t('choose_testament')) ?></h3>
          <div class="bible-nav-grid">
            <button class="bible-nav-card" data-testament="old">
              <div class="bible-nav-card-icon">📖</div>
              <div class="bible-nav-card-title"><?= esc(t('old_testament')) ?></div>
              <div class="bible-nav-card-subtitle"><?= esc(t('genesis_malachi')) ?></div>
            </button>
            <button class="bible-nav-card" data-testament="new">
              <div class="bible-nav-card-icon">✨</div>
              <div class="bible-nav-card-title"><?= esc(t('new_testament')) ?></div>
              <div class="bible-nav-card-subtitle"><?= esc(t('matthew_revelation')) ?></div>
            </button>
          </div>
        </div>

        <!-- Step 2: Book Selection -->
        <div class="bible-nav-step bible-nav-hidden" id="navStepBook">
          <button class="bible-nav-back" id="navBackToTestament">← <?= esc(t('back')) ?></button>
          <h3 class="bible-nav-step-title" id="navBookTitle"><?= esc(t('choose_book')) ?></h3>
          <div class="bible-nav-grid" id="navBookGrid"></div>
        </div>

        <!-- Step 3: Chapter Selection -->
        <div class="bible-nav-step bible-nav-hidden" id="navStepChapter">
          <button class="bible-nav-back" id="navBackToBook">← <?= esc(t('back')) ?></button>
          <h3 class="bible-nav-step-title" id="navChapterTitle"><?= esc(t('choose_chapter')) ?></h3>
          <div class="bible-nav-grid bible-nav-grid-small" id="navChapterGrid"></div>
        </div>

      </div>
    </div>
  </div>

  <main class="bible-main">

    <!-- Search Panel -->
    <section class="bible-panel bible-panel-hidden" id="searchPanel">
      <div class="bible-panel-header">
        <h3 class="bible-panel-title"><?= esc(t('search_bible')) ?></h3>
        <button class="bible-panel-close" id="searchClose">×</button>
      </div>
      <div class="bible-panel-body">
        <div class="bible-search-container">
          <input type="text" id="searchInput" class="bible-input" placeholder="<?= esc(t('type_search_term')) ?>">
          <button class="bible-btn bible-btn-primary" id="searchBtn">
            <svg class="bible-btn-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="2"/>
              <path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
            <span><?= esc(t('search')) ?></span>
          </button>
        </div>
        <div id="searchResults" class="bible-search-results"></div>
      </div>
    </section>

    <!-- Notes Panel -->
    <section class="bible-panel bible-panel-hidden" id="notesPanel">
      <div class="bible-panel-header">
        <h3 class="bible-panel-title"><?= esc(t('notes')) ?></h3>
        <button class="bible-panel-close" id="notesClose">×</button>
      </div>
      <div class="bible-panel-body">
        <div id="notesList" class="bible-notes-list">
          <p class="bible-empty-state"><?= esc(t('no_notes_yet')) ?></p>
        </div>
        <div id="noteEditor" class="bible-note-editor bible-note-hidden">
          <div class="bible-note-ref" id="noteReference"></div>
          <textarea id="noteText" class="bible-textarea" placeholder="<?= esc(t('write_note_here')) ?>"></textarea>
          <div class="bible-note-actions">
            <button class="bible-btn bible-btn-primary" id="saveNoteBtn"><?= esc(t('save')) ?></button>
            <button class="bible-btn" id="cancelNoteBtn"><?= esc(t('cancel')) ?></button>
          </div>
        </div>
      </div>
    </section>

    <!-- Bookmarks Panel -->
    <section class="bible-panel bible-panel-hidden" id="bookmarksPanel">
      <div class="bible-panel-header">
        <h3 class="bible-panel-title"><?= esc(t('bookmarks')) ?></h3>
        <button class="bible-panel-close" id="bookmarksClose">×</button>
      </div>
      <div class="bible-panel-body">
        <div id="bookmarksList" class="bible-bookmarks-list">
          <p class="bible-empty-state"><?= esc(t('no_bookmarks_yet')) ?></p>
        </div>
      </div>
    </section>

    <!-- AI Commentary Panel -->
    <section class="bible-panel bible-panel-hidden" id="aiPanel">
      <div class="bible-panel-header">
        <h3 class="bible-panel-title"><?= esc(t('ai_commentary')) ?></h3>
        <button class="bible-panel-close" id="aiClose">×</button>
      </div>
      <div class="bible-panel-body">
        <div id="aiOutput" class="bible-ai-output">
          <p class="bible-empty-state"><?= esc(t('select_verse_ai')) ?></p>
        </div>
      </div>
    </section>

    <!-- Cross References Panel -->
    <section class="bible-panel bible-panel-hidden" id="crossRefPanel">
      <div class="bible-panel-header">
        <h3 class="bible-panel-title"><?= esc(t('cross_references')) ?></h3>
        <button class="bible-panel-close" id="crossRefClose">×</button>
      </div>
      <div class="bible-panel-body">
        <div id="crossRefList" class="bible-cross-ref-list">
          <p class="bible-empty-state"><?= esc(t('select_verse_cross_ref')) ?></p>
        </div>
      </div>
    </section>

    <!-- Main Reading View (Dual Columns) -->
    <section class="bible-reading-section">
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

    <!-- Context Menu -->
    <div id="verseContextMenu" class="bible-context-menu bible-context-hidden">
      <div class="bible-context-section-title"><?= esc(t('choose_color')) ?></div>
      <div class="bible-highlight-colors">
        <button class="bible-color-btn bible-color-1" data-color="1" title="<?= esc(t('pink')) ?>"></button>
        <button class="bible-color-btn bible-color-2" data-color="2" title="<?= esc(t('orange')) ?>"></button>
        <button class="bible-color-btn bible-color-3" data-color="3" title="<?= esc(t('yellow')) ?>"></button>
        <button class="bible-color-btn bible-color-4" data-color="4" title="<?= esc(t('green')) ?>"></button>
        <button class="bible-color-btn bible-color-5" data-color="5" title="<?= esc(t('blue')) ?>"></button>
        <button class="bible-color-btn bible-color-6" data-color="6" title="<?= esc(t('purple')) ?>"></button>
        <button class="bible-color-btn bible-color-btn-clear" data-color="0" title="<?= esc(t('remove')) ?>">×</button>
      </div>

      <div class="bible-context-divider"></div>

      <button class="bible-context-item" id="ctxBookmark">
        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z" stroke="currentColor" stroke-width="2"/>
        </svg>
        <?= esc(t('bookmark')) ?>
      </button>

      <button class="bible-context-item" id="ctxAddNote">
        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M12 20h9M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z" stroke="currentColor" stroke-width="2"/>
        </svg>
        <?= esc(t('add_note')) ?>
      </button>

      <button class="bible-context-item" id="ctxAI">
        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/>
          <path d="M12 1v6m0 6v6M1 12h6m6 0h6" stroke="currentColor" stroke-width="2"/>
        </svg>
        <?= esc(t('ask_ai')) ?>
      </button>

      <button class="bible-context-item" id="ctxCrossRef">
        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71" stroke="currentColor" stroke-width="2"/>
          <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71" stroke="currentColor" stroke-width="2"/>
        </svg>
        <?= esc(t('cross_refs')) ?>
      </button>
      
      <button class="bible-context-item" id="ctxCopy">
        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <rect x="9" y="9" width="13" height="13" rx="2" stroke="currentColor" stroke-width="2"/>
          <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" stroke="currentColor" stroke-width="2"/>
        </svg>
        <?= esc(t('copy')) ?>
      </button>

      <button class="bible-context-item" id="ctxShare">
        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <circle cx="18" cy="5" r="3" stroke="currentColor" stroke-width="2"/>
          <circle cx="6" cy="12" r="3" stroke="currentColor" stroke-width="2"/>
          <circle cx="18" cy="19" r="3" stroke="currentColor" stroke-width="2"/>
          <path d="M8.59 13.51l6.83 3.98m-.01-10.98l-6.82 3.98" stroke="currentColor" stroke-width="2"/>
        </svg>
        <?= esc(t('share')) ?>
      </button>
    </div>

  </main>

  <!-- Fixed Footer Toolbar -->
  <section class="bible-toolbar">
    <button class="bible-tool-btn bible-tool-btn-primary" id="quickNavToggle" title="<?= esc(t('navigate')) ?>" aria-label="<?= esc(t('navigate')) ?>">
      <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
        <polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76" stroke="currentColor" stroke-width="2" stroke-linejoin="round" fill="none"/>
      </svg>
    </button>

    <button class="bible-tool-btn" id="searchToggle" title="<?= esc(t('search')) ?>" aria-label="<?= esc(t('search')) ?>">
      <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="2"/>
        <path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      </svg>
    </button>

    <button class="bible-tool-btn" id="notesToggle" title="<?= esc(t('notes')) ?>" aria-label="<?= esc(t('notes')) ?>">
      <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M12 20h9M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z" stroke="currentColor" stroke-width="2"/>
      </svg>
    </button>

    <button class="bible-tool-btn" id="bookmarksToggle" title="<?= esc(t('bookmarks')) ?>" aria-label="<?= esc(t('bookmarks')) ?>">
      <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z" stroke="currentColor" stroke-width="2"/>
      </svg>
    </button>

    <button class="bible-tool-btn bible-tool-btn-text" id="fontSizeDecrease" title="<?= esc(t('decrease')) ?>" aria-label="<?= esc(t('decrease')) ?>">
      <span>A-</span>
    </button>

    <button class="bible-tool-btn bible-tool-btn-text" id="fontSizeIncrease" title="<?= esc(t('increase')) ?>" aria-label="<?= esc(t('increase')) ?>">
      <span>A+</span>
    </button>
  </section>

  <script>
    window.BIBLE = {
      lang: '<?= esc($pageLang) ?>',
      paths: {
        af: '/bible/bibles/af_1933_53.json?v=<?= filemtime(__DIR__ . '/bibles/af_1933_53.json') ?>',
        en: '/bible/bibles/en_kjv1611.json?v=<?= filemtime(__DIR__ . '/bibles/en_kjv1611.json') ?>'
      },
      userId: <?= (int)($_SESSION['user_id'] ?? 0) ?>
    };
  </script>
  <script type="module" src="/bible/js/bible.js?v=<?= filemtime(__DIR__ . '/js/bible.js') ?>"></script>

</body>
</html>