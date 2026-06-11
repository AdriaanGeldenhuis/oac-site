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

// Load songs from both languages
$songsEn = [];
$songsAf = [];
$songsPathEn = __DIR__ . '/songs/sing_en.json';
$songsPathAf = __DIR__ . '/songs/sing_af.json';

if (file_exists($songsPathEn)) {
    $decoded = json_decode(file_get_contents($songsPathEn), true);
    if (is_array($decoded)) {
        $songsEn = $decoded;
    }
}
if (file_exists($songsPathAf)) {
    $decoded = json_decode(file_get_contents($songsPathAf), true);
    if (is_array($decoded)) {
        $songsAf = $decoded;
    }
}

// Use current language songs for display (song picker)
$songs = ($lang === 'en') ? $songsEn : $songsAf;
$altSongs = ($lang === 'en') ? $songsAf : $songsEn;

// Build union of song numbers from both languages so every hymn appears
// in the picker, even if it only exists in one language.
$allSongNumbers = [];
foreach ($songsEn as $s) { $allSongNumbers[(string)$s['number']] = true; }
foreach ($songsAf as $s) { $allSongNumbers[(string)$s['number']] = true; }
$allSongNumbers = array_keys($allSongNumbers);
usort($allSongNumbers, fn($a, $b) => (int)$a <=> (int)$b);

// Select song
$songParam = null;
if (isset($_GET['song']) && preg_match('/^[0-9]+$/', $_GET['song'])) {
    $songParam = $_GET['song'];
}

$selectedSong = null;
$languageFallback = false; // Flag for showing notice
$fallbackLang = '';

if ($songParam) {
    // First try to find song in current language
    foreach ($songs as $s) {
        if ((string)$s['number'] === (string)$songParam) {
            $selectedSong = $s;
            break;
        }
    }

    // If not found, try the other language
    if (!$selectedSong) {
        foreach ($altSongs as $s) {
            if ((string)$s['number'] === (string)$songParam) {
                $selectedSong = $s;
                $languageFallback = true;
                $fallbackLang = ($lang === 'en') ? 'af' : 'en';
                break;
            }
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

  <main class="se-main">
    <!-- Language & Search Section -->
    <section class="se-section se-controls-section">
      <h2 class="se-page-title">Sing Emmanuel</h2>

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
      <div class="se-song-content" id="songContent">
        <?php if ($languageFallback): ?>
          <div class="se-language-notice">
            <svg class="se-notice-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
              <path d="M12 8v4M12 16h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
            <span><?= t($lang === 'en' ? 'song_not_in_english' : 'song_not_in_afrikaans') ?></span>
          </div>
        <?php endif; ?>
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
      <div class="se-overlay-search">
        <input type="text" id="songNumberInput" class="se-number-input"
               inputmode="numeric" pattern="[0-9]*" maxlength="4"
               placeholder="<?= t('enter_song_number') ?>" autocomplete="off"
               aria-label="<?= t('enter_song_number') ?>">
        <button class="se-number-go" id="songNumberGo" aria-label="<?= t('search_song') ?>">
          <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M5 12h14M13 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </button>
      </div>
      <div class="se-overlay-grid" id="songPickerGrid">
        <?php foreach ($allSongNumbers as $songNumber): ?>
          <?php $songNumberStr = (string)$songNumber; ?>
          <button class="se-song-btn" data-song="<?= htmlspecialchars($songNumberStr) ?>">
            <span class="se-btn-shine"></span>
            <span><?= htmlspecialchars($songNumberStr) ?></span>
          </button>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <script src="/singemmanuel/js/singemmanuel.js?v=<?= $VER ?>"></script>

  <?php require_once __DIR__ . '/../header_footer/footer.php'; ?>

</body>
</html>