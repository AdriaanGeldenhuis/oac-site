<?php
// Cache-busted: v4 — "Light Through Glass" redesign
declare(strict_types=1);
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
require_once __DIR__ . '/security/auth_gate.php';
require_once __DIR__ . '/includes/languages.php';
require_once __DIR__ . '/includes/teaching.php';
require_once __DIR__ . '/gedagtes/lib/gedagtes.php';
require_once __DIR__ . '/security/config.php';

// Handle language change via GET parameter - redirect to apply cleanly
if (isset($_GET['lang']) && in_array($_GET['lang'], SUPPORTED_LANGS, true)) {
    $_SESSION['language'] = $_GET['lang'];
    header('Location: /welcome.php');
    exit;
}

// Language detection - get from session, or load from DB
$lang = $_SESSION['language'] ?? null;

// If no session language, fetch from DB
if (!$lang || !in_array($lang, SUPPORTED_LANGS, true)) {
    try {
        $langStmt = $pdo->prepare('SELECT language FROM users WHERE id = ? LIMIT 1');
        $langStmt->execute([$_SESSION['user_id']]);
        $langRow = $langStmt->fetch(PDO::FETCH_ASSOC);
        $lang = validate_language($langRow['language'] ?? 'en');
        $_SESSION['language'] = $lang;
    } catch (Throwable $e) {
        $lang = 'en';
        $_SESSION['language'] = $lang;
    }
}

// Translation helper using central system
function t(string $key): string {
    global $lang;
    return __t($key, $lang);
}

// HTML-escape helper for template output
function wc_e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$firstName = '';
$surname = '';
$ampId = 10;          // 10 = Broer/Suster, the same default the calendar API uses
$gender = 'man';
$town = '';
$province = '';
$townId = null;
$provinceId = null;

try {
    $stmt = $pdo->prepare('
        SELECT
            u.name AS first_name,
            u.surname AS surname,
            u.amp_id AS amp_id,
            u.gender AS gender,
            t.id AS town_id,
            t.name AS town,
            p.id AS province_id,
            p.name AS province
        FROM users u
        LEFT JOIN towns t ON t.id = u.town_id
        LEFT JOIN provinces p ON p.id = t.province_id
        WHERE u.id = ?
        LIMIT 1
    ');
    $stmt->execute([$_SESSION['user_id']]);

    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $firstName = trim((string)($row['first_name'] ?? ''));
        $surname = trim((string)($row['surname'] ?? ''));
        $ampId = (int)($row['amp_id'] ?? 0) ?: 10;
        $gender = (string)($row['gender'] ?? '') ?: 'man';
        $town = $row['town'] ?? '';
        $province = $row['province'] ?? '';
        $townId = $row['town_id'] ?? null;
        $provinceId = $row['province_id'] ?? null;
    }
} catch (Throwable $e) {
    error_log("Welcome page - DB error: " . $e->getMessage());
}

// Teaching of the month: the town's own teaching in the reader's language,
// falling back to English, then Afrikaans (see includes/teaching.php)
$teachingHtml = teaching_reader_html(teaching_dir((string)$province, (string)$town), $lang);

// Today's thought once its display_time has come, in the reader's language.
// Same rules as the thoughts page (South African time, not the database clock).
$dailyThought = null;
try {
    $dailyThought = gd_today($pdo, $lang)['thought'];
} catch (Throwable $e) {
    error_log("Daily thought fetch error: " . $e->getMessage());
}

$VER = time();

// "Now" in South Africa. Drives the greeting, the date line and the sky tint,
// and is handed to the JS so "Today"/"Tomorrow" agree with the server.
$now      = new DateTimeImmutable('now', new DateTimeZone('Africa/Johannesburg'));
$hour     = (int)$now->format('G');
$tod      = $hour < 12 ? 'morning' : ($hour < 18 ? 'afternoon' : 'evening');
$greeting = t('greeting_' . $tod);
$dateLine = t(strtolower($now->format('l'))) . ', ' . $now->format('j') . ' ' . t(strtolower($now->format('F')));

// Everything the JS needs: server clock + the strings it renders
$wcData = [
    'now'      => $now->format('Y-m-d H:i'),
    'today'    => $now->format('Y-m-d'),
    'tomorrow' => $now->modify('+1 day')->format('Y-m-d'),
    'i18n'     => [
        'days'     => array_map(fn($k) => t($k), ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday']),
        'months'   => array_map(fn($k) => t($k), ['january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december']),
        'today'    => t('today'),
        'tomorrow' => t('tomorrow'),
        'noEvents' => t('no_events'),
        'copied'   => t('copied'),
        'readMore' => t('read_more'),
        'readLess' => t('read_less'),
    ],
];

// Quick navigation. 'size': feature = hero tile, wide = double width, '' = single.
// 'accent' picks the stained-glass colour of the tile.
$navItems = [
    ['id' => 'bible',    'href' => '/bible/bible.php',                'key' => 'bible',          'accent' => 'amber',    'size' => 'feature', 'icon' => '<path d="M18 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM6 4h5v8l-2.5-1.5L6 12V4z"/>'],
    ['id' => 'prayers',  'href' => '/prayers/prayers.php',            'key' => 'prayers',        'accent' => 'amethyst', 'size' => '',        'icon' => '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>'],
    ['id' => 'sing',     'href' => '/singemmanuel/singemmanuel.php',  'key' => 'sing_emmanuel',  'accent' => 'gold',     'size' => '',        'icon' => '<path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/>'],
    ['id' => 'calendar', 'href' => '/calendar/calendar.php',          'key' => 'calendar',       'accent' => 'sapphire', 'size' => 'wide',    'icon' => '<path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM9 10H7v2h2v-2zm4 0h-2v2h2v-2zm4 0h-2v2h2v-2z"/>'],
    ['id' => 'ai',       'href' => '/aislimbybel/aislimbybel.php',    'key' => 'ai_smart_bible', 'accent' => 'silver',   'size' => 'wide',    'icon' => '<path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/>'],
    ['id' => 'summary',  'href' => '/opsomming/opsomming.php',        'key' => 'bible_summary',  'accent' => 'rose',     'size' => '',        'icon' => '<path d="M21 5c-1.11-.35-2.33-.5-3.5-.5-1.95 0-4.05.4-5.5 1.5-1.45-1.1-3.55-1.5-5.5-1.5S3.45 4.9 2 6v14.65c0 .25.25.5.5.5.1 0 .15-.05.25-.05C4.1 20.45 6.05 20 7.5 20c1.95 0 4.05.4 5.5 1.5 1.35-.85 3.8-1.5 5.5-1.5 1.65 0 3.35.3 4.75 1.05.1.05.15.05.25.05.25 0 .5-.25.5-.5V6c-.6-.45-1.25-.75-2-1zm0 13.5c-1.1-.35-2.3-.5-3.5-.5-1.7 0-4.15.65-5.5 1.5V8c1.35-.85 3.8-1.5 5.5-1.5 1.2 0 2.4.15 3.5.5v11.5z"/>'],
    ['id' => 'diary',    'href' => '/diary/diary.php',                'key' => 'diary',          'accent' => 'amber',    'size' => '',        'icon' => '<path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>'],
    ['id' => 'gospel',   'href' => '/gospel_media/gospel.php',        'key' => 'gospel_media',   'accent' => 'ruby',     'size' => 'wide',    'icon' => '<path d="M21 3H3c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h5v2h8v-2h5c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 14H3V5h18v12zM10 15l5.5-4.5L10 6v9z"/>'],
    ['id' => 'notif',    'href' => '/notifications/notifications.php','key' => 'notifications', 'accent' => 'sapphire', 'size' => '',        'icon' => '<path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.89 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/>'],
    ['id' => 'thoughts', 'href' => '/gedagtes/gedagtes.php',          'key' => 'thought_history','accent' => 'amethyst', 'size' => '',        'icon' => '<path d="M9 21c0 .55.45 1 1 1h4c.55 0 1-.45 1-1v-1H9v1zm3-19C8.14 2 5 5.14 5 9c0 2.38 1.19 4.47 3 5.74V17c0 .55.45 1 1 1h6c.55 0 1-.45 1-1v-2.26c1.81-1.27 3-3.36 3-5.74 0-3.86-3.14-7-7-7z"/>'],
];

// Time-of-day glyph for the greeting eyebrow
$todIcons = [
    'morning'   => '<path d="M5 17a7 7 0 0114 0"/><path d="M2 17h20M12 4v3M4.6 9.6l2 2M19.4 9.6l-2 2"/>',
    'afternoon' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9L7 7M17 17l2.1 2.1M4.9 19.1L7 17M17 7l2.1-2.1"/>',
    'evening'   => '<path d="M20 14.5A8 8 0 019.5 4 8 8 0 1020 14.5z"/>',
];

// Addressed by office and surname, e.g. "Oudste Geldenhuis" / "Broer Geldenhuis" /
// "Priester Geldenhuis". Same title lookup the profile page uses. If the surname
// is missing we fall back to the first name so we never greet "Oudste" on its own.
$callName    = $surname !== '' ? $surname : $firstName;
$officeTitle = get_translated_office($ampId, $gender, $lang);
$displayName = $callName !== '' ? trim($officeTitle . ' ' . $callName) : '';
$hasName     = $displayName !== '';
// Long names scale down rather than taking over the screen
$nameLen  = function_exists('mb_strlen') ? mb_strlen($displayName, 'UTF-8') : strlen($displayName);
$nameSize = $nameLen > 18 ? ' wc-hello--sm' : ($nameLen > 10 ? ' wc-hello--md' : '');
?><!doctype html>
<html lang="<?= wc_e($lang) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= wc_e(t('page_title_welcome')) ?></title>

  <!-- Reveal-on-scroll only hides content once we know JS is running -->
  <script>
    // wc-js lets the CSS hide sections until welcome.js reveals them. Should
    // welcome.js never run (failed download, a WebView that cannot run it),
    // drop the class after 4 s so every section, the teaching too, shows.
    (function (root) {
      root.classList.add('wc-js');
      setTimeout(function () { if (!window.wcReady) { root.classList.remove('wc-js'); } }, 4000);
    })(document.documentElement);
  </script>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;1,500;1,600&family=Parisienne&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="/welcome/css/welcome.css?v=<?= $VER ?>">
</head>
<body class="welcome-body wc-home">
  <?php require_once __DIR__ . '/header_footer/header.php'; ?>

  <!-- Icon sprite (referenced by the quick-navigation tiles) -->
  <svg class="wc-sprite" width="0" height="0" aria-hidden="true" focusable="false">
    <defs>
      <?php foreach ($navItems as $item): ?>
        <symbol id="wc-i-<?= $item['id'] ?>" viewBox="0 0 24 24" fill="currentColor"><?= $item['icon'] ?></symbol>
      <?php endforeach; ?>
    </defs>
  </svg>

  <!-- Atmosphere: a wooden cross in a halo of light, rays and rising embers (purely decorative) -->
  <div class="wc-aura" id="wcAura" data-tod="<?= $tod ?>" aria-hidden="true">
    <div class="wc-rays"></div>
    <div class="wc-halo"></div>
    <div class="wc-cross">
      <img src="/assets/img/wood-cross.svg" alt="" width="320" height="500" decoding="async">
    </div>
    <div class="wc-embers" id="wcEmbers"></div>
  </div>

  <main class="wc-main" id="wcMain">

    <!-- Greeting -->
    <section class="wc-hero" aria-labelledby="wcHello">
      <p class="wc-eyebrow">
        <svg class="wc-tod" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $todIcons[$tod] ?></svg>
        <span><?= wc_e(t('welcome_title')) ?></span>
      </p>
      <h2 class="wc-hello<?= $hasName ? $nameSize : ' wc-hello--solo' ?>" id="wcHello">
        <span class="wc-hello-greet"><?= wc_e($greeting) ?><?= $hasName ? ',' : '' ?></span>
        <?php if ($hasName): ?>
          <span class="wc-hello-name"><?= wc_e($displayName) ?></span>
        <?php endif; ?>
      </h2>
      <div class="wc-meta">
        <span class="wc-chip">
          <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><?= $navItems[3]['icon'] ?></svg>
          <?= wc_e($dateLine) ?>
        </span>
        <?php if ($town || $province): ?>
          <span class="wc-chip">
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
            <?= wc_e($town) ?><?= ($town && $province) ? ', ' : '' ?><?= wc_e($province) ?>
          </span>
        <?php endif; ?>
      </div>
    </section>

    <!-- Today: thought of the day + what's coming up -->
    <div class="wc-today<?= $dailyThought ? '' : ' wc-today--solo' ?>">

      <?php if ($dailyThought): ?>
      <section class="wc-thought-section wc-reveal" aria-labelledby="wcThoughtLabel">
        <h3 class="wc-label" id="wcThoughtLabel"><?= wc_e(t('thought_of_day')) ?></h3>
        <figure class="wc-card wc-thought wc-foldable" id="wcThought">
          <button class="wc-thought-share" id="wcShare" type="button" aria-label="<?= wc_e(t('share')) ?>" title="<?= wc_e(t('share')) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 12v7a1 1 0 001 1h14a1 1 0 001-1v-7M16 6l-4-4-4 4M12 2v13"/></svg>
          </button>

          <div class="wc-fold-body" id="wcThoughtBody">
            <div class="wc-thought-main">
              <blockquote class="wc-thought-text" id="wcThoughtText" lang="<?= wc_e($dailyThought['lang']) ?>"><?= wc_e($dailyThought['text']) ?></blockquote>
              <?php if (!empty($dailyThought['author'])): ?>
                <figcaption class="wc-thought-author" id="wcThoughtAuthor"><?= wc_e($dailyThought['author']) ?></figcaption>
              <?php endif; ?>
            </div>
            <div class="wc-fold-fade" aria-hidden="true"></div>
          </div>

          <button type="button" class="wc-fold-toggle" id="wcThoughtToggle" aria-expanded="false" aria-controls="wcThoughtBody" hidden>
            <span class="wc-fold-label"><?= wc_e(t('read_more')) ?></span>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
          </button>
        </figure>
      </section>
      <?php endif; ?>

      <section class="wc-upnext-section wc-reveal" style="--i:1" aria-labelledby="wcUpNextLabel">
        <h3 class="wc-label" id="wcUpNextLabel"><?= wc_e(t('up_next')) ?></h3>
        <div class="wc-card wc-upnext" data-upnext aria-busy="true">
          <div class="wc-skel" aria-hidden="true"><i></i><i></i></div>
        </div>
      </section>

    </div>

    <!-- Quick Navigation -->
    <section class="wc-apps" aria-labelledby="wcAppsLabel">
      <h3 class="wc-label wc-reveal" id="wcAppsLabel"><?= wc_e(t('quick_navigation')) ?></h3>
      <div class="wc-bento">
        <?php foreach ($navItems as $i => $item): ?>
          <div class="wc-cell<?= $item['size'] ? ' wc-cell--' . $item['size'] : '' ?> wc-reveal" style="--i:<?= $i ?>">
            <a href="<?= wc_e($item['href']) ?>" class="wc-tile wc-tile--<?= $item['accent'] ?>">
              <svg class="wc-tile-mark" viewBox="0 0 24 24" aria-hidden="true"><use href="#wc-i-<?= $item['id'] ?>"/></svg>
              <span class="wc-tile-orb">
                <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#wc-i-<?= $item['id'] ?>"/></svg>
                <?php if ($item['id'] === 'notif'): ?>
                  <span class="wc-badge" data-notif-badge hidden></span>
                <?php endif; ?>
              </span>
              <span class="wc-tile-label"><?= wc_e(t($item['key'])) ?></span>
              <svg class="wc-tile-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg>
            </a>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- Teaching of the month -->
    <section class="wc-teaching-section wc-reveal" aria-labelledby="wcTeachLabel">
      <h3 class="wc-label" id="wcTeachLabel"><?= wc_e(t('teaching_of_month')) ?></h3>
      <p class="wc-sub"><?= wc_e(t('grow_faith')) ?></p>
      <article class="wc-card wc-teaching-card wc-foldable" id="wcTeach">
        <div class="wc-read-progress" aria-hidden="true"><i id="wcReadBar"></i></div>
        <div class="wc-card-decoration"></div>

        <div class="wc-teach-tools" id="wcTeachTools" hidden>
          <button type="button" class="wc-size-btn wc-size-btn--sm" id="wcSizeDown" aria-label="<?= wc_e(t('decrease')) ?>" title="<?= wc_e(t('decrease')) ?>">A</button>
          <button type="button" class="wc-size-btn wc-size-btn--lg" id="wcSizeUp" aria-label="<?= wc_e(t('increase')) ?>" title="<?= wc_e(t('increase')) ?>">A</button>
        </div>

        <div class="wc-fold-body" id="wcTeachBody">
          <div class="wc-teaching-content">
            <?php
              // Sanitized on the way out too, so older files are just as safe
              if ($teachingHtml !== '') {
                echo $teachingHtml;
              } else {
                echo '<div class="wc-no-content">
                        <svg viewBox="0 0 24 24" fill="currentColor">
                          <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                        </svg>
                        <p>' . wc_e(t('no_content')) . '</p>
                      </div>';
              }
            ?>
          </div>
          <div class="wc-fold-fade" aria-hidden="true"></div>
        </div>

        <button type="button" class="wc-fold-toggle" id="wcTeachToggle" aria-expanded="false" aria-controls="wcTeachBody" hidden>
          <span class="wc-fold-label" id="wcTeachToggleText"><?= wc_e(t('read_more')) ?></span>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
        </button>
      </article>
    </section>

    <!-- Closing verse, by candlelight -->
    <section class="wc-bene wc-reveal">
      <div class="wc-candle" aria-hidden="true"><i class="wc-flame"></i><i class="wc-wick"></i><i class="wc-wax"></i></div>
      <blockquote class="wc-quote">
        <p class="wc-quote-text"><?= wc_e(t('quote_matthew_18_20')) ?></p>
        <cite class="wc-quote-source"><?= wc_e(t('quote_matthew_18_20_ref')) ?></cite>
      </blockquote>
    </section>

  </main>

  <div class="wc-flare" id="wcFlare" aria-hidden="true"></div>
  <div class="wc-toast" id="wcToast" role="status" aria-live="polite"></div>

  <script type="application/json" id="wcData"><?= json_encode($wcData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}' ?></script>
  <script src="/welcome/js/welcome.js?v=<?= $VER ?>" defer></script>
  <?php require_once __DIR__ . '/header_footer/footer.php'; ?>

</body>
</html>
