<?php
declare(strict_types=1);
require_once __DIR__ . '/../security/auth_gate.php';
require_once __DIR__ . '/../includes/languages.php';

// Language detection
$lang = $_SESSION['language'] ?? 'af';
if (isset($_GET['lang']) && in_array($_GET['lang'], SUPPORTED_LANGS, true)) {
    $lang = $_GET['lang'];
    $_SESSION['language'] = $lang;
}

// Translation helper using central 5-language system
function t(string $key): string {
    global $lang;
    return __t($key, $lang);
}

// Load songs
$songs = [];
$songsPath = __DIR__ . '/songs/' . ($lang === 'en' ? 'sing_en.json' : 'sing_af.json');
if (file_exists($songsPath)) {
    $songsJson = file_get_contents($songsPath);
    $decoded = json_decode($songsJson, true);
    if (is_array($decoded)) {
        $songs = $decoded;
    }
}

// Select song
$songParam = null;
if (isset($_GET['song']) && preg_match('/^[0-9]+$/', $_GET['song'])) {
    $songParam = $_GET['song'];
}

$selectedSong = null;
if ($songParam) {
    foreach ($songs as $s) {
        if ((string)$s['number'] === (string)$songParam) {
            $selectedSong = $s;
            break;
        }
    }
}
if (!$selectedSong && !empty($songs)) {
    $selectedSong = $songs[0];
    $songParam = (string)$selectedSong['number'];
}

$VER = time();
?><!doctype html>
<html lang="<?= $lang ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= t('sing_emmanuel_title') ?></title>
  
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
  
  <link rel="stylesheet" href="/singemmanuel/css/singemmanuel.css?v=<?= $VER ?>">
</head>
<body class="singemmanuel-body">
  <?php require_once __DIR__ . '/../header_footer/header.php'; ?>

  <!-- Hero Section -->
  <div class="se-hero">
    <div class="se-hero-glow"></div>
    <div class="se-hero-content">
      <h1 class="se-hero-title">Sing Emmanuel</h1>
      <p class="se-hero-subtitle"><?= t('praise_lord_song') ?></p>
    </div>
    <div class="se-sparkles">
      <span class="se-sparkle" style="--delay: 0s; --x: 10%; --y: 20%;"></span>
      <span class="se-sparkle" style="--delay: 0.5s; --x: 85%; --y: 30%;"></span>
      <span class="se-sparkle" style="--delay: 1s; --x: 50%; --y: 60%;"></span>
      <span class="se-sparkle" style="--delay: 1.5s; --x: 20%; --y: 80%;"></span>
      <span class="se-sparkle" style="--delay: 2s; --x: 90%; --y: 70%;"></span>
    </div>
  </div>

  <main class="se-main">
    <!-- Language & Search Section -->
    <section class="se-section se-controls-section">
      <div class="se-section-header">
        <div class="se-icon-wrapper">
          <svg class="se-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z" stroke="currentColor" stroke-width="1.5" fill="none"/>
          </svg>
        </div>
        <h2 class="se-section-title"><?= t('choose_song') ?></h2>
      </div>

      <div class="se-controls-grid">
        <div class="se-language-toggle">
          <a href="?lang=af<?= $songParam ? '&song='.$songParam : '' ?>" 
             class="se-lang-btn <?= $lang === 'af' ? 'active' : '' ?>">
            <span class="se-btn-shine"></span>
            <span class="se-btn-text">Afrikaans</span>
          </a>
          <span class="se-lang-divider">|</span>
          <a href="?lang=en<?= $songParam ? '&song='.$songParam : '' ?>" 
             class="se-lang-btn <?= $lang === 'en' ? 'active' : '' ?>">
            <span class="se-btn-shine"></span>
            <span class="se-btn-text">English</span>
          </a>
        </div>

        <button class="se-btn se-btn-primary" id="openSongPicker">
          <span class="se-btn-shine"></span>
          <svg class="se-btn-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="2"/>
            <path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
          <span class="se-btn-text"><?= t('search_song') ?></span>
        </button>
      </div>
    </section>

    <!-- Song Display Section -->
    <section class="se-section se-song-section">
      <div class="se-section-header">
        <div class="se-icon-wrapper">
          <svg class="se-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M19 1H5c-1.1 0-2 .9-2 2v18l7-3 7 3V3c0-1.1-.9-2-2-2z" stroke="currentColor" stroke-width="1.5" fill="none"/>
            <path d="M12 6v6M9 9h6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
          </svg>
        </div>
        <h2 class="se-section-title">
          <?= t('hymn') ?> 
          <?= htmlspecialchars($selectedSong ? (string)$selectedSong['number'] : '1') ?>
        </h2>
      </div>

      <div class="se-song-content" id="songContent">
        <?php if ($selectedSong): ?>
          <?php if (!empty($selectedSong['verses']) && is_array($selectedSong['verses'])): ?>
            <?php foreach ($selectedSong['verses'] as $verse): ?>
              <p class="se-verse"><?= nl2br(htmlspecialchars($verse)) ?></p>
            <?php endforeach; ?>
          <?php endif; ?>
        <?php else: ?>
          <div class="se-placeholder">
            <svg class="se-placeholder-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z" fill="currentColor" opacity="0.3"/>
            </svg>
            <p><?= t('song_not_found') ?></p>
          </div>
        <?php endif; ?>
      </div>
    </section>
  </main>

  <!-- Song Picker Overlay -->
  <div id="songPickerOverlay" class="se-overlay" hidden>
    <div class="se-overlay-backdrop"></div>
    <div class="se-overlay-content">
      <div class="se-overlay-header">
        <h3><?= t('choose_song') ?></h3>
        <button class="se-overlay-close" id="closeSongPicker" aria-label="<?= t('close') ?>">
          <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </button>
      </div>
      <div class="se-overlay-grid" id="songPickerGrid">
        <?php foreach ($songs as $song): ?>
          <button class="se-song-btn" data-song="<?= htmlspecialchars((string)$song['number']) ?>">
            <span class="se-btn-shine"></span>
            <span><?= htmlspecialchars((string)$song['number']) ?></span>
          </button>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <script src="/singemmanuel/js/singemmanuel.js?v=<?= $VER ?>"></script>

  <?php require_once __DIR__ . '/../header_footer/footer.php'; ?>

</body>
</html>