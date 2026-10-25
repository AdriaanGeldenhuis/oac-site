<?php
// =====================================================================
// /header_footer/header.php — Global Header
// =====================================================================

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// Include languages config
require_once __DIR__ . '/../includes/languages.php';

// Language handling - support all 5 languages
if (isset($_GET['lang']) && in_array($_GET['lang'], SUPPORTED_LANGS, true)) {
  $_SESSION['language'] = $_GET['lang'];
}
if (empty($_SESSION['language']) || !in_array($_SESSION['language'], SUPPORTED_LANGS, true)) {
  $_SESSION['language'] = 'af';
}

$hdrLang = $_SESSION['language'];

// Translation helper using the central translation system
function t_hdr(string $key): string {
  global $hdrLang;
  return __t($key, $hdrLang);
}

// Get current page title
$reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$pageTitle = 'Old Apostolic Church';

// Check if we're on Bible page
$isBiblePage = strpos($reqPath, '/bible/bible.php') !== false || strpos($reqPath, '/bybel/') !== false;

if (strpos($reqPath, '/welcome.php') !== false || strpos($reqPath, '/welkom/') !== false) {
  $pageTitle = t_hdr('welcome');
} elseif (strpos($reqPath, '/gospel_media/gospel.php') !== false || strpos($reqPath, '/gospel_media/rooms.php') !== false) {
  $pageTitle = t_hdr('gospel_media');
} elseif (strpos($reqPath, '/prayers/prayers.php') !== false) {
  $pageTitle = t_hdr('prayers');
} elseif (strpos($reqPath, '/aislimbybel/aislimbybel.php') !== false) {
  $pageTitle = t_hdr('ai_smart_bible');
} elseif ($isBiblePage) {
  $pageTitle = t_hdr('bible');
} elseif (strpos($reqPath, '/opsomming/opsomming.php') !== false) {
  $pageTitle = t_hdr('bible_summary');
} elseif (strpos($reqPath, '/calendar/calendar.php') !== false || strpos($reqPath, '/calendar/view.php') !== false) {
  $pageTitle = t_hdr('calendar');
} elseif (strpos($reqPath, '/diary/diary.php') !== false) {
  $pageTitle = t_hdr('diary');
} elseif (strpos($reqPath, '/singemmanuel/singemmanuel.php') !== false) {
  $pageTitle = t_hdr('sing_emmanuel');
} elseif (strpos($reqPath, '/gedagtes/gedagtes.php') !== false) {
  $pageTitle = t_hdr('thought_history');
} elseif (strpos($reqPath, '/policy') !== false) {
  $pageTitle = t_hdr('privacy_policy');
} elseif (strpos($reqPath, '/admin/') !== false) {
  $pageTitle = t_hdr('admin');
}

// Navigation links: [href, translation key, jewel colour, icon path]
// Colours/icons match the quick-navigation tiles on the welcome page.
$navLinks = [
  ['/welcome.php', 'welcome', 'rose', '<path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/>'],
  ['/gospel_media/gospel.php', 'gospel_media', 'ruby', '<path d="M21 3H3c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h5v2h8v-2h5c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 14H3V5h18v12zM10 15l5.5-4.5L10 6v9z"/>'],
  ['/prayers/prayers.php', 'prayers', 'amethyst', '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>'],
  ['/aislimbybel/aislimbybel.php', 'ai_smart_bible', 'emerald', '<path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/>'],
  ['/bible/bible.php', 'bible', 'amber', '<path d="M18 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM6 4h5v8l-2.5-1.5L6 12V4z"/>'],
  ['/opsomming/opsomming.php', 'bible_summary', 'rose', '<path d="M21 5c-1.11-.35-2.33-.5-3.5-.5-1.95 0-4.05.4-5.5 1.5-1.45-1.1-3.55-1.5-5.5-1.5S3.45 4.9 2 6v14.65c0 .25.25.5.5.5.1 0 .15-.05.25-.05C4.1 20.45 6.05 20 7.5 20c1.95 0 4.05.4 5.5 1.5 1.35-.85 3.8-1.5 5.5-1.5 1.65 0 3.35.3 4.75 1.05.1.05.15.05.25.05.25 0 .5-.25.5-.5V6c-.6-.45-1.25-.75-2-1zm0 13.5c-1.1-.35-2.3-.5-3.5-.5-1.7 0-4.15.65-5.5 1.5V8c1.35-.85 3.8-1.5 5.5-1.5 1.2 0 2.4.15 3.5.5v11.5z"/>'],
  ['/calendar/calendar.php', 'calendar', 'sapphire', '<path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM9 10H7v2h2v-2zm4 0h-2v2h2v-2zm4 0h-2v2h2v-2z"/>'],
  ['/diary/diary.php', 'diary', 'amber', '<path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>'],
  ['/singemmanuel/singemmanuel.php', 'sing_emmanuel', 'ruby', '<path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/>'],
  ['/notifications/notifications.php', 'notifications', 'sapphire', '<path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.89 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/>'],
  ['/gedagtes/gedagtes.php', 'thought_history', 'amethyst', '<path d="M9 21c0 .55.45 1 1 1h4c.55 0 1-.45 1-1v-1H9v1zm3-19C8.14 2 5 5.14 5 9c0 2.38 1.19 4.47 3 5.74V17c0 .55.45 1 1 1h6c.55 0 1-.45 1-1v-2.26c1.81-1.27 3-3.36 3-5.74 0-3.86-3.14-7-7-7z"/>'],
  ['/admin/index.php', 'admin', 'emerald', '<path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/>'],
  ['/policy/', 'privacy_policy', 'sapphire', '<path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zM9 8V6c0-1.66 1.34-3 3-3s3 1.34 3 3v2H9z"/>'],
  ['/logout.php', 'logout', 'ruby', '<path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/>']
];

function isActive($url, $currentPath) {
  return strpos($currentPath, $url) !== false;
}
?>
<link rel="stylesheet" href="/header_footer/css/header.css?v=<?= time() ?>">

<header class="ghf-header" id="ghfHeader">
  <div class="ghf-container">

    <!-- Hamburger Button -->
    <button class="ghf-hamburger" id="ghfHamburger" type="button" aria-label="<?= t_hdr('open_menu') ?>">
      <span class="ghf-bar"></span>
      <span class="ghf-bar"></span>
      <span class="ghf-bar"></span>
      <span class="ghf-shine"></span>
    </button>

    <!-- Page Title -->
    <h1 class="ghf-title<?= $isBiblePage ? ' ghf-title--ref' : '' ?>"><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1>

    <!-- Right-side actions -->
    <div class="ghf-actions">

      <!-- Bible View Toggle (only on Bible page) -->
      <?php if ($isBiblePage): ?>
      <button class="ghf-view-toggle" id="ghfViewToggle" type="button" title="<?= t_hdr('toggle_view') ?>">
        <svg class="ghf-view-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <rect x="3" y="3" width="8" height="18" stroke="currentColor" stroke-width="1.5" rx="1"/>
          <rect x="13" y="3" width="8" height="18" stroke="currentColor" stroke-width="1.5" rx="1"/>
        </svg>
        <span class="ghf-view-shine"></span>
      </button>
      <?php endif; ?>

      <!-- Theme Toggle (Dark/Light) -->
      <button class="ghf-theme-toggle" id="ghfThemeToggle" type="button" title="<?= t_hdr('toggle_theme') ?>">
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
        <button class="ghf-lang-btn" id="ghfLangBtn" type="button" aria-expanded="false" aria-label="<?= t_hdr('language') ?>">
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

  </div>
</header>

<!-- Navigation Overlay -->
<div class="ghf-overlay" id="ghfOverlay" hidden></div>

<!-- Navigation Drawer -->
<nav class="ghf-drawer" id="ghfDrawer" aria-hidden="true">
  <!-- Decorative wooden cross in a halo of light (the same image as the welcome page) -->
  <div class="ghf-drawer-cross" aria-hidden="true">
    <img src="/assets/img/wood-cross.svg" alt="" width="320" height="500" decoding="async">
  </div>

  <div class="ghf-drawer-header">
    <h2 class="ghf-drawer-title"><?= t_hdr('navigation') ?></h2>
    <button class="ghf-drawer-close" id="ghfDrawerClose" type="button" aria-label="<?= t_hdr('close') ?>">
      <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      </svg>
    </button>
  </div>
  <ul class="ghf-nav-list">
    <?php foreach ($navLinks as $i => $link): ?>
      <?php $isCurrent = isActive($link[0], $reqPath); ?>
      <li style="--i:<?= $i ?>">
        <a href="<?= htmlspecialchars($link[0], ENT_QUOTES, 'UTF-8') ?>" class="ghf-nav-link ghf-nav-link--<?= $link[2] ?><?= $isCurrent ? ' active' : '' ?>"<?= $isCurrent ? ' aria-current="page"' : '' ?>>
          <span class="ghf-nav-orb"><svg viewBox="0 0 24 24" aria-hidden="true"><?= $link[3] ?></svg></span>
          <span class="ghf-nav-text"><?= htmlspecialchars(t_hdr($link[1]), ENT_QUOTES, 'UTF-8') ?></span>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
</nav>

<script src="/header_footer/js/header.js?v=<?= time() ?>"></script>
<script src="/assets/js/fcm-bridge.js?v=<?= time() ?>"></script>
