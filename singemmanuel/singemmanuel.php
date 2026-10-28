<?php
declare(strict_types=1);
/**
 * Sing Emmanuel — "Light Through Glass", ruby edition.
 * The welcome page's atmosphere lit in ruby, the colour the hymn book
 * carries on the welcome tiles. One hymn at a time, its verses set like a
 * page of a hymnal; the number picker and the organ player open as sheets
 * (phones) / cards (desktop).
 *
 * Hymns come from songs/sing_af.json and songs/sing_en.json. The organ
 * player (js/se-player.js + api/midi.php) is for offices 0-8, and only when
 * the hymn's MIDI file exists.
 */

header('Cache-Control: no-cache, must-revalidate');
require_once __DIR__ . '/../security/auth_gate.php';
require_once __DIR__ . '/../includes/languages.php';

// Language detection
$lang = $_SESSION['language'] ?? 'af';
if (isset($_GET['lang']) && in_array($_GET['lang'], SUPPORTED_LANGS, true)) {
    $lang = $_GET['lang'];
    $_SESSION['language'] = $lang;
}
if (!in_array($lang, SUPPORTED_LANGS, true)) {
    $lang = 'af';
}

// Translation helper using central 5-language system
function t(string $key): string {
    global $lang;
    return __t($key, $lang);
}

function se_e(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Cache-bust by file time, so browsers keep the assets until they change */
function se_asset(string $path): string {
    $mtime = @filemtime(dirname(__DIR__) . $path);
    return $path . '?v=' . ($mtime ?: '1');
}

/**
 * The verses as blocks to draw: a numbered verse ("1 Heilig, heilig ..."),
 * a chorus (the text after a "Koor" / "Chorus" line) or a plain stanza.
 */
function se_blocks(array $verses): array {
    $blocks = [];
    $inChorus = false;
    foreach ($verses as $v) {
        if (!is_string($v)) continue;
        $v = trim($v);
        if ($v === '') continue;
        if (preg_match('/^(koor|chorus|refrein|refrain)[:.]?$/iu', $v)) {
            $inChorus = true;
            continue;
        }
        if ($inChorus) {
            $blocks[] = ['type' => 'chorus', 'text' => $v];
            $inChorus = false;
            continue;
        }
        if (preg_match('/^(\d{1,2})[.)]?\s*(?=\D)(.+)$/su', $v, $m) && (int)$m[1] >= 1 && (int)$m[1] <= 20) {
            $blocks[] = ['type' => 'verse', 'num' => (string)(int)$m[1], 'text' => trim($m[2])];
        } else {
            $blocks[] = ['type' => 'stanza', 'text' => $v];
        }
    }
    return $blocks;
}

/** The hymn's opening words, for under its number ("Heilig, heilig, heilig, Here God Almagtig!") */
function se_first_line(array $blocks): string {
    if (!$blocks) return '';
    $text = preg_replace('/\s+/u', ' ', $blocks[0]['text']);
    if (preg_match('/^(.{12,70}?[!?.;:])(\s|$)/u', $text, $m)) {
        return rtrim($m[1], '.;:,');
    }
    if (mb_strlen($text) <= 60) {
        return rtrim($text, '.;:,');
    }
    $cut = mb_substr($text, 0, 60);
    $comma = mb_strrpos($cut, ',');
    if ($comma !== false && $comma >= 20) {
        return mb_substr($cut, 0, $comma);
    }
    return rtrim($cut) . '…';
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
$allSongNumbers = array_map('strval', array_keys($allSongNumbers));
usort($allSongNumbers, fn($a, $b) => (int)$a <=> (int)$b);

// Select song
$songParam = null;
if (isset($_GET['song']) && is_string($_GET['song']) && preg_match('/^[0-9]+$/', $_GET['song'])) {
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

// Orrel-speler: slegs ampte 0-8 (Admin t/m Hulpkrag), en net as die MIDI bestaan.
$stmt = $pdo->prepare("SELECT amp_id FROM users WHERE id = ? LIMIT 1");
$stmt->execute([(int)$_SESSION['user_id']]);
$myAmpId = $stmt->fetchColumn();
$canPlay = $myAmpId !== false && $myAmpId !== null && (int)$myAmpId >= 0 && (int)$myAmpId <= 8;
$hasMidi = $selectedSong && is_file(__DIR__ . '/media/' . sprintf('%03d', (int)$selectedSong['number']) . '.mid');
$showPlayer = $canPlay && $hasMidi;
// 'n R-lêer (bv. 002R.mid) bevat 2 verse; anders is net 1 vers beskikbaar.
$hasTwoVerses = $showPlayer && is_file(__DIR__ . '/media/' . sprintf('%03d', (int)$selectedSong['number']) . 'R.mid');

// What to draw
$songNumber = $selectedSong ? (string)$selectedSong['number'] : '';
$songLang = $languageFallback ? $fallbackLang : ($lang === 'en' ? 'en' : 'af');
$blocks = ($selectedSong && !empty($selectedSong['verses']) && is_array($selectedSong['verses'])) ? se_blocks($selectedSong['verses']) : [];
$firstLine = se_first_line($blocks);
$chorusLabel = $songLang === 'en' ? 'Chorus' : 'Koor';

// Previous / next hymn, through every number the book has (either language)
$prevSong = null;
$nextSong = null;
$pos = $songNumber !== '' ? array_search($songNumber, $allSongNumbers, true) : false;
if ($pos !== false) {
    $prevSong = $allSongNumbers[$pos - 1] ?? null;
    $nextSong = $allSongNumbers[$pos + 1] ?? null;
}

$songUrl = fn(string $n): string => '/singemmanuel/singemmanuel.php?song=' . rawurlencode($n);
$langUrl = fn(string $l): string => '/singemmanuel/singemmanuel.php?lang=' . $l . ($songParam ? '&song=' . rawurlencode((string)$songParam) : '');

$icons = [
    'note' => '<path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/>',
    'organ' => '<path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/>',
];
?><!doctype html>
<html lang="<?= se_e($lang) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= se_e(t('sing_emmanuel_title')) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;1,500;1,600&family=Parisienne&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= se_asset('/singemmanuel/css/singemmanuel.css') ?>">
</head>
<body class="se-body">
  <?php require_once __DIR__ . '/../header_footer/header.php'; ?>

  <svg class="se-sprite" width="0" height="0" aria-hidden="true" focusable="false"><defs>
    <symbol id="se-i-note" viewBox="0 0 24 24" fill="currentColor"><?= $icons['note'] ?></symbol>
    <symbol id="se-i-organ" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?= $icons['organ'] ?></symbol>
  </defs></svg>

  <!-- Atmosphere: rays and a halo of ruby light (purely decorative) -->
  <div class="se-aura" aria-hidden="true"><div class="se-rays"></div><div class="se-halo"></div></div>

  <main class="se-main" id="seMain">
    <section class="se-hero" aria-labelledby="seTitle">
      <p class="se-eyebrow">
        <svg class="se-eyebrow-icon" viewBox="0 0 24 24" aria-hidden="true"><use href="#se-i-note"/></svg>
        <span><?= se_e(t('sing_emmanuel')) ?></span>
      </p>
      <h2 class="se-title" id="seTitle">
        <?php if ($selectedSong): ?>
          <span class="se-title-word"><?= se_e(t('hymn')) ?></span>
          <span class="se-title-num"><?= se_e($songNumber) ?></span>
        <?php else: ?>
          <span class="se-title-word"><?= se_e(t('sing_emmanuel')) ?></span>
        <?php endif; ?>
      </h2>
      <?php if ($firstLine !== ''): ?>
        <p class="se-first-line" lang="<?= se_e($songLang) ?>"><?= se_e($firstLine) ?></p>
      <?php else: ?>
        <p class="se-first-line"><?= se_e(t('praise_lord_song')) ?></p>
      <?php endif; ?>
    </section>

    <!-- Song language, the number picker and (for offices 0-8) the organ -->
    <div class="se-card se-toolbar">
      <nav class="se-seg" aria-label="<?= se_e(t('se_song_language')) ?>">
        <a href="<?= se_e($langUrl('af')) ?>" class="se-seg-btn<?= $lang !== 'en' ? ' is-on' : '' ?>"<?= $lang !== 'en' ? ' aria-current="true"' : '' ?> lang="af">Afrikaans</a>
        <a href="<?= se_e($langUrl('en')) ?>" class="se-seg-btn<?= $lang === 'en' ? ' is-on' : '' ?>"<?= $lang === 'en' ? ' aria-current="true"' : '' ?> lang="en">English</a>
      </nav>

      <div class="se-toolbar-actions">
        <button type="button" class="se-btn se-btn--primary" id="openSongPicker" aria-haspopup="dialog">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
          <span><?= se_e(t('search_song')) ?></span>
        </button>
        <?php if ($showPlayer): ?>
          <button type="button" class="se-btn se-btn--glass" id="openPlayer" aria-haspopup="dialog"
                  data-song="<?= se_e($songNumber) ?>" title="<?= se_e(t('play_song')) ?>">
            <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#se-i-organ"/></svg>
            <span><?= se_e(t('play_song')) ?></span>
          </button>
        <?php endif; ?>
      </div>
    </div>

    <!-- The hymn -->
    <article class="se-card se-song" id="songContent" data-size="2" aria-labelledby="seTitle"<?= $selectedSong ? ' lang="' . se_e($songLang) . '"' : '' ?>>
      <?php if ($languageFallback): ?>
        <p class="se-notice" lang="<?= se_e($lang) ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
          <span><?= se_e(t($lang === 'en' ? 'song_not_in_english' : 'song_not_in_afrikaans')) ?></span>
        </p>
      <?php endif; ?>

      <?php if ($selectedSong): ?>
        <div class="se-song-tools" id="seSizeTools" lang="<?= se_e($lang) ?>" role="group" aria-label="<?= se_e(t('se_text_size')) ?>">
          <button type="button" class="se-size-btn se-size-btn--sm" id="seSizeDown" aria-label="<?= se_e(t('decrease')) ?>" title="<?= se_e(t('decrease')) ?>">A</button>
          <button type="button" class="se-size-btn se-size-btn--lg" id="seSizeUp" aria-label="<?= se_e(t('increase')) ?>" title="<?= se_e(t('increase')) ?>">A</button>
        </div>

        <div class="se-verses">
          <?php foreach ($blocks as $b): ?>
            <?php if ($b['type'] === 'verse'): ?>
              <div class="se-verse">
                <span class="se-verse-num" aria-hidden="true"><?= se_e($b['num']) ?></span>
                <p class="se-verse-text"><span class="se-sr"><?= se_e($b['num']) ?>. </span><?= se_e($b['text']) ?></p>
              </div>
            <?php elseif ($b['type'] === 'chorus'): ?>
              <div class="se-chorus">
                <span class="se-chorus-label"><?= se_e($chorusLabel) ?></span>
                <p class="se-chorus-text"><?= se_e($b['text']) ?></p>
              </div>
            <?php else: ?>
              <div class="se-verse se-verse--plain">
                <p class="se-verse-text"><?= se_e($b['text']) ?></p>
              </div>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
        <span class="se-song-end" aria-hidden="true"></span>
      <?php else: ?>
        <div class="se-empty">
          <span class="se-empty-orb" aria-hidden="true"><svg viewBox="0 0 24 24"><use href="#se-i-note"/></svg></span>
          <p class="se-empty-text"><?= se_e(t('song_not_found')) ?></p>
        </div>
      <?php endif; ?>
    </article>
    <script>
      // Text size before the first paint, so the hymn does not jump
      try { var seSize = localStorage.getItem('se_text_size'); if (/^[0-4]$/.test(seSize || '')) document.getElementById('songContent').setAttribute('data-size', seSize); } catch (e) {}
    </script>

    <?php if ($prevSong !== null || $nextSong !== null): ?>
      <nav class="se-pager" aria-label="<?= se_e(t('hymn')) ?>">
        <?php if ($prevSong !== null): ?>
          <a class="se-pager-link se-pager-link--prev" href="<?= se_e($songUrl($prevSong)) ?>" rel="prev">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg>
            <span class="se-pager-text"><small><?= se_e(t('se_prev')) ?></small><b><?= se_e(t('hymn')) ?> <?= se_e($prevSong) ?></b></span>
          </a>
        <?php endif; ?>
        <?php if ($nextSong !== null): ?>
          <a class="se-pager-link se-pager-link--next" href="<?= se_e($songUrl($nextSong)) ?>" rel="next">
            <span class="se-pager-text"><small><?= se_e(t('se_next')) ?></small><b><?= se_e(t('hymn')) ?> <?= se_e($nextSong) ?></b></span>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg>
          </a>
        <?php endif; ?>
      </nav>
    <?php endif; ?>
  </main>

  <!-- Song picker: a sheet on phones, a card on desktop -->
  <div id="songPickerOverlay" class="se-layer" hidden>
    <div class="se-sheet se-picker" role="dialog" aria-modal="true" aria-labelledby="songPickerTitle" tabindex="-1">
      <span class="se-sheet-grip" aria-hidden="true"></span>
      <div class="se-sheet-head">
        <h2 class="se-sheet-title" id="songPickerTitle"><?= se_e(t('choose_song')) ?></h2>
        <button type="button" class="se-sheet-close" id="closeSongPicker" data-se-close aria-label="<?= se_e(t('close')) ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M18 6L6 18M6 6l12 12"/></svg>
        </button>
      </div>
      <div class="se-picker-search">
        <input type="text" id="songNumberInput" class="se-number-input"
               inputmode="numeric" pattern="[0-9]*" maxlength="4" enterkeyhint="go"
               placeholder="<?= se_e(t('enter_song_number')) ?>" autocomplete="off"
               aria-label="<?= se_e(t('enter_song_number')) ?>" aria-describedby="songNumberMsg">
        <button type="button" class="se-number-go" id="songNumberGo" aria-label="<?= se_e(t('search_song')) ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </button>
      </div>
      <p class="se-picker-msg" id="songNumberMsg" role="status" aria-live="polite" data-not-found="<?= se_e(t('song_not_found')) ?>"></p>
      <div class="se-sheet-body">
        <div class="se-picker-grid" id="songPickerGrid">
          <?php foreach ($allSongNumbers as $n): ?>
            <button type="button" class="se-song-btn<?= $n === $songNumber ? ' is-current' : '' ?>" data-song="<?= se_e($n) ?>"<?= $n === $songNumber ? ' aria-current="true"' : '' ?>><?= se_e($n) ?></button>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <?php if ($showPlayer): ?>
  <!-- Orrel-speler -->
  <div id="playerOverlay" class="se-layer" hidden>
    <div class="se-sheet se-player" role="dialog" aria-modal="true" aria-labelledby="playerTitle" tabindex="-1">
      <span class="se-sheet-grip" aria-hidden="true"></span>
      <div class="se-sheet-head">
        <h2 class="se-sheet-title" id="playerTitle">
          <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#se-i-organ"/></svg>
          <span><?= se_e(t('hymn')) ?> <?= se_e($songNumber) ?></span>
        </h2>
        <button type="button" class="se-sheet-close" id="closePlayer" data-se-close aria-label="<?= se_e(t('close')) ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M18 6L6 18M6 6l12 12"/></svg>
        </button>
      </div>
      <div class="se-verse-choice" id="playerChoice">
        <p class="se-verse-choice-label" id="playerChoiceLabel"><?= se_e(t('player_verses_prompt')) ?></p>
        <div class="se-verse-choice-btns" role="group" aria-labelledby="playerChoiceLabel">
          <button type="button" class="se-verse-btn" data-verses="1"><b>1</b><span><?= se_e(t('player_1_verse')) ?></span></button>
          <?php if ($hasTwoVerses): ?>
            <button type="button" class="se-verse-btn" data-verses="2"><b>2</b><span><?= se_e(t('player_2_verses')) ?></span></button>
          <?php endif; ?>
        </div>
      </div>
      <div class="se-player-body" id="playerBody" hidden>
        <p class="se-player-verses" id="playerVerses"></p>
        <button type="button" class="se-player-toggle" id="playerToggle" aria-pressed="false"
                aria-label="<?= se_e(t('player_play')) ?>"
                data-label-play="<?= se_e(t('player_play')) ?>" data-label-pause="<?= se_e(t('player_pause')) ?>">
          <svg class="se-icon-play" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14l11-7z" fill="currentColor"/></svg>
          <svg class="se-icon-pause" viewBox="0 0 24 24" aria-hidden="true"><path d="M7 5h4v14H7zM13 5h4v14h-4z" fill="currentColor"/></svg>
        </button>
        <div class="se-player-progress" aria-hidden="true"><div class="se-player-bar" id="playerBar"></div></div>
        <div class="se-player-time" id="playerTime">0:00</div>
        <p class="se-player-status" id="playerStatus" role="status"
           data-loading="<?= se_e(t('player_loading')) ?>" data-error="<?= se_e(t('player_error')) ?>"></p>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <script defer src="<?= se_asset('/singemmanuel/js/singemmanuel.js') ?>"></script>
  <?php if ($showPlayer): ?>
  <script defer src="<?= se_asset('/singemmanuel/js/se-player.js') ?>"></script>
  <?php endif; ?>

  <?php require_once __DIR__ . '/../header_footer/footer.php'; ?>

</body>
</html>
