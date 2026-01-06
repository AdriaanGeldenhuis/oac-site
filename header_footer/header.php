<?php
// =====================================================================
// /header_footer/header.php — Global Header
// =====================================================================

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// Include languages config if available
$langConfigPath = __DIR__ . '/../includes/languages.php';
if (file_exists($langConfigPath)) {
  require_once $langConfigPath;
} else {
  // Fallback if languages.php not loaded
  define('SUPPORTED_LANGS', ['af', 'en', 'zu', 'xh', 'pt']);
  define('LANG_NAMES', [
    'af' => 'Afrikaans',
    'en' => 'English',
    'zu' => 'isiZulu',
    'xh' => 'isiXhosa',
    'pt' => 'Português'
  ]);
}

// Language handling - support all 5 languages
if (isset($_GET['lang']) && in_array($_GET['lang'], SUPPORTED_LANGS, true)) {
  $_SESSION['language'] = $_GET['lang'];
}
if (empty($_SESSION['language']) || !in_array($_SESSION['language'], SUPPORTED_LANGS, true)) {
  $_SESSION['language'] = 'af';
}

$hdrLang = $_SESSION['language'];

// Translation helper
function t_hdr($af, $en) {
  global $hdrLang;
  return $hdrLang === 'en' ? $en : $af;
}

// Get current page title
$reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$pageTitle = 'Old Apostolic Church';

// Check if we're on Bible page
$isBiblePage = strpos($reqPath, '/bible/bible.php') !== false || strpos($reqPath, '/bybel/') !== false;

if (strpos($reqPath, '/welcome.php') !== false || strpos($reqPath, '/welkom/') !== false) {
  $pageTitle = t_hdr('Welkom', 'Welcome');
} elseif (strpos($reqPath, '/gospel_media/gospel.php') !== false) {
  $pageTitle = t_hdr('Evangelie Media', 'Gospel Media');
} elseif (strpos($reqPath, '/prayers/prayers.php') !== false) {
  $pageTitle = t_hdr('Gebede', 'Prayers');
} elseif (strpos($reqPath, '/aislimbybel/aislimbybel.php') !== false) {
  $pageTitle = t_hdr('AI Slimbybel', 'AI Smart Bible');
} elseif ($isBiblePage) {
  $pageTitle = t_hdr('Bybel', 'Bible');
} elseif (strpos($reqPath, '/calendar/calendar.php') !== false) {
  $pageTitle = t_hdr('Kalender', 'Calendar');
} elseif (strpos($reqPath, '/diary/diary.php') !== false) {
  $pageTitle = t_hdr('Dagboek', 'Diary');
} elseif (strpos($reqPath, '/singemmanuel/singemmanuel.php') !== false) {
  $pageTitle = 'Sing Emmanuel';
}

// Navigation links
$navLinks = [
  ['/welcome.php', 'Welkom', 'Welcome'],
  ['/gospel_media/gospel.php', 'Evangelie Media', 'Gospel Media'],
  ['/prayers/prayers.php', 'Gebede', 'Prayers'],
  ['/aislimbybel/aislimbybel.php', 'AI Slimbybel', 'AI Smart Bible'],
  ['/bible/bible.php', 'Bybel', 'Bible'],
  ['/calendar/calendar.php', 'Kalender', 'Calendar'],
  ['/diary/diary.php', 'Dagboek', 'Diary'],
  ['/singemmanuel/singemmanuel.php', 'Sing Emmanuel', 'Sing Emmanuel'],
  ['/notifications/notifications.php', 'Kennisgewings', 'Notifications'],
  ['/admin/index.php', 'Admin', 'Admin'],
  ['/logout.php', 'Teken uit', 'Log out']
];

function isActive($url, $currentPath) {
  return strpos($currentPath, $url) !== false;
}
?>
<link rel="stylesheet" href="/header_footer/css/header.css?v=<?= time() ?>">

<header class="ghf-header" id="ghfHeader">
  <div class="ghf-container">
    
    <!-- Hamburger Button -->
    <button class="ghf-hamburger" id="ghfHamburger" type="button" aria-label="<?= t_hdr('Open menu', 'Open menu') ?>">
      <span class="ghf-bar"></span>
      <span class="ghf-bar"></span>
      <span class="ghf-bar"></span>
      <span class="ghf-shine"></span>
    </button>

    <!-- Page Title -->
    <h1 class="ghf-title"><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1>

    <!-- Bible View Toggle (only on Bible page) -->
    <?php if ($isBiblePage): ?>
    <button class="ghf-view-toggle" id="ghfViewToggle" type="button" title="<?= t_hdr('Wissel Aansig', 'Toggle View') ?>">
      <svg class="ghf-view-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <rect x="3" y="3" width="8" height="18" stroke="currentColor" stroke-width="1.5" rx="1"/>
        <rect x="13" y="3" width="8" height="18" stroke="currentColor" stroke-width="1.5" rx="1"/>
      </svg>
      <span class="ghf-view-shine"></span>
    </button>
    <?php endif; ?>

    <!-- Theme Toggle (Dark/Light) -->
    <button class="ghf-theme-toggle" id="ghfThemeToggle" type="button" title="<?= t_hdr('Wissel Tema', 'Toggle Theme') ?>">
      <svg class="ghf-theme-icon ghf-theme-sun" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="12" cy="12" r="5" stroke="currentColor" stroke-width="1.5"/>
        <line x1="12" y1="1" x2="12" y2="3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
        <line x1="12" y1="21" x2="12" y2="23" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
        <line x1="4.22" y1="4.22" x2="5.64" y2="5.64" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
        <line x1="18.36" y1="18.36" x2="19.78" y2="19.78" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
        <line x1="1" y1="12" x2="3" y2="12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
        <line x1="21" y1="12" x2="23" y2="12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
        <line x1="4.22" y1="19.78" x2="5.64" y2="18.36" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
        <line x1="18.36" y1="5.64" x2="19.78" y2="4.22" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
      </svg>
      <svg class="ghf-theme-icon ghf-theme-moon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      <span class="ghf-theme-shine"></span>
    </button>

    <!-- Language Dropdown -->
    <div class="ghf-lang-wrapper">
      <button class="ghf-lang-btn" id="ghfLangBtn" type="button" aria-expanded="false">
        <svg class="ghf-lang-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.5"/>
          <path d="M2 12h20M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z" stroke="currentColor" stroke-width="1.5"/>
        </svg>
        <span class="ghf-lang-text"><?= strtoupper($hdrLang) ?></span>
        <span class="ghf-lang-shine"></span>
      </button>
      <ul class="ghf-lang-menu" id="ghfLangMenu" hidden>
        <?php foreach (SUPPORTED_LANGS as $code): ?>
        <li><a href="#" data-lang="<?= $code ?>" class="<?= $hdrLang === $code ? 'active' : '' ?>"><?= htmlspecialchars(LANG_NAMES[$code]) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </div>

  </div>
</header>

<!-- Navigation Overlay -->
<div class="ghf-overlay" id="ghfOverlay" hidden></div>

<!-- Navigation Drawer -->
<nav class="ghf-drawer" id="ghfDrawer" aria-hidden="true">
  <div class="ghf-drawer-header">
    <h2 class="ghf-drawer-title"><?= t_hdr('Navigasie', 'Navigation') ?></h2>
    <button class="ghf-drawer-close" id="ghfDrawerClose" type="button" aria-label="<?= t_hdr('Sluit', 'Close') ?>">
      <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      </svg>
    </button>
  </div>
  <ul class="ghf-nav-list">
    <?php foreach ($navLinks as $link): ?>
      <?php $active = isActive($link[0], $reqPath) ? ' active' : ''; ?>
      <li>
        <a href="<?= htmlspecialchars($link[0], ENT_QUOTES, 'UTF-8') ?>" class="ghf-nav-link<?= $active ?>">
          <span class="ghf-nav-shine"></span>
          <?= htmlspecialchars(t_hdr($link[1], $link[2]), ENT_QUOTES, 'UTF-8') ?>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
</nav>

<script src="/header_footer/js/header.js?v=<?= time() ?>"></script>