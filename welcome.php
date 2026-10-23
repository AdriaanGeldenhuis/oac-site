<?php
// =====================================================================
// /welcome.php — Home (Bible-app style dashboard)
// =====================================================================
declare(strict_types=1);
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
require_once __DIR__ . '/security/auth_gate.php';
require_once __DIR__ . '/includes/languages.php';
require_once __DIR__ . '/includes/verse_of_day.php';
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

function e(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$town = '';
$province = '';
$townId = null;
$provinceId = null;

try {
    $stmt = $pdo->prepare('
        SELECT
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
        $town = $row['town'] ?? '';
        $province = $row['province'] ?? '';
        $townId = $row['town_id'] ?? null;
        $provinceId = $row['province_id'] ?? null;
    }
} catch (Throwable $e) {
    error_log("Welcome page - DB error: " . $e->getMessage());
}

// Slug function
$slug = function($s) {
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', '_', $s);
    return trim($s, '_');
};

$provSlug = $slug($province);
$townSlug = $slug($town);
$cityDir = ($provSlug && $townSlug) ? (__DIR__ . '/welcome/south_africa/' . $provSlug . '/' . $townSlug) : '';

// Choose content file based on language with proper fallback
// Afrikaans: lering_content.html
// Other languages: teaching_content.<lang>.html
$contentFile = null;

if ($lang === 'af') {
    // Afrikaans fallback chain
    $candidates = [
        $cityDir ? ($cityDir . '/lering_content.html') : '',
        __DIR__ . '/welcome/lering_content.html'
    ];
} else {
    // Other languages fallback chain
    $candidates = [
        $cityDir ? ($cityDir . '/teaching_content.' . $lang . '.html') : '',
        $cityDir ? ($cityDir . '/teaching_content.html') : '',
        __DIR__ . '/welcome/teaching_content.' . $lang . '.html',
        __DIR__ . '/welcome/teaching_content.html'
    ];
}

// Find first readable file
foreach ($candidates as $cand) {
    if ($cand && is_readable($cand)) {
        $contentFile = $cand;
        break;
    }
}

// Teaching HTML is written by admins in the teaching editor (trusted)
$teachingHtml = $contentFile ? (string)file_get_contents($contentFile) : '';
$teachingWords = $teachingHtml !== '' ? count(preg_split('/\s+/u', trim(strip_tags($teachingHtml)), -1, PREG_SPLIT_NO_EMPTY)) : 0;
$teachingMinutes = max(1, (int)ceil($teachingWords / 200));
$hasTeaching = $teachingWords > 0;

// Fetch today's thought (only if display_time has passed or is NULL)
$dailyThought = null;
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS daily_thoughts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            content TEXT NOT NULL,
            author VARCHAR(255) DEFAULT NULL,
            display_date DATE NOT NULL,
            display_time TIME DEFAULT NULL,
            notification_sent TINYINT(1) DEFAULT 0,
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_date (display_date),
            INDEX idx_display_date (display_date),
            INDEX idx_created_by (created_by)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $stmt = $pdo->prepare('
        SELECT content, author
        FROM daily_thoughts
        WHERE display_date = CURDATE()
          AND (display_time IS NULL OR display_time <= CURTIME())
        LIMIT 1
    ');
    $stmt->execute();
    $dailyThought = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $e) {
    error_log("Daily thought fetch error: " . $e->getMessage());
}

// ----- Greeting, date & sky (South African time; JS refines to local) -----
$now = new DateTimeImmutable('now', new DateTimeZone(VOTD_TZ));
$hour = (int)$now->format('G');
$greetKey = $hour >= 5 && $hour < 12 ? 'good_morning' : ($hour >= 12 && $hour < 18 ? 'good_afternoon' : 'good_evening');
$sky = $hour >= 5 && $hour < 8 ? 'dawn' : ($hour >= 8 && $hour < 16 ? 'day' : ($hour >= 16 && $hour < 19 ? 'dusk' : 'night'));
$firstName = trim((string)($_SESSION['user_name'] ?? ''));
$todayLabel = votd_date_label($now, $lang);

// ----- Verse of the Day -----
$votd = votd_for_offset(0, $lang);
$votdShown = $votd ? $votd[$votd['primary']] : null;
$votdLen = $votdShown ? mb_strlen($votdShown['text']) : 0;
$votdSize = $votdLen < 90 ? 's' : ($votdLen < 170 ? 'm' : 'l');
$translationNames = ['af' => 'AFR 1933/53', 'en' => 'KJV'];

$VER = static fn(string $rel): int => (int)@filemtime(__DIR__ . $rel);

// ----- Feature cards & app tiles -----
$featured = [
    ['href' => '/bible/bible.php', 'key' => 'bible', 'desc' => 'wc_desc_bible', 'tone' => 'bible',
     'icon' => '<path d="M12 6.6C10.2 5.2 7.7 4.5 4.6 4.5H3v13.2h1.6c3.1 0 5.6.7 7.4 2.1 1.8-1.4 4.3-2.1 7.4-2.1H21V4.5h-1.6c-3.1 0-5.6.7-7.4 2.1z"/><path d="M12 6.6v13.2"/>'],
    ['href' => '/aislimbybel/aislimbybel.php', 'key' => 'ai_smart_bible', 'desc' => 'wc_desc_ai', 'tone' => 'ai',
     'icon' => '<path d="M11 3l1.9 4.9L17.8 9.8l-4.9 1.9L11 16.6l-1.9-4.9L4.2 9.8l4.9-1.9z"/><path d="M18.5 14.5l.9 2.3 2.3.9-2.3.9-.9 2.3-.9-2.3-2.3-.9 2.3-.9z"/>'],
    ['href' => '/opsomming/opsomming.php', 'key' => 'bible_summary', 'desc' => 'wc_desc_summary', 'tone' => 'summary',
     'icon' => '<rect x="3.5" y="3.5" width="5" height="17" rx="1.2"/><rect x="10" y="3.5" width="4" height="17" rx="1.2"/><path d="M15.6 4.6l3.8-.9 3.1 15.9-3.8.9z"/>'],
    ['href' => '/singemmanuel/singemmanuel.php', 'key' => 'sing_emmanuel', 'desc' => 'wc_desc_sing', 'tone' => 'sing',
     'icon' => '<path d="M9 18V5.5l11-2v12.5"/><circle cx="6" cy="18" r="3"/><circle cx="17" cy="16" r="3"/>'],
];

$apps = [
    ['href' => '/gospel_media/gospel.php', 'key' => 'gospel_media', 'tone' => 'media',
     'icon' => '<rect x="2.5" y="4" width="19" height="13.5" rx="3"/><path d="M10.2 8.2v5.3l4.6-2.65z"/><path d="M8 21h8"/>'],
    ['href' => '/prayers/prayers.php', 'key' => 'prayers', 'tone' => 'prayer',
     'icon' => '<path d="M12 20.5s-7.2-4.3-9.2-9C1.4 8.1 3.4 4.5 7 4.5c2 0 3.4 1.1 5 2.9 1.6-1.8 3-2.9 5-2.9 3.6 0 5.6 3.6 4.2 7-2 4.7-9.2 9-9.2 9z"/>'],
    ['href' => '/calendar/calendar.php', 'key' => 'calendar', 'tone' => 'calendar',
     'icon' => '<rect x="3" y="4.5" width="18" height="16.5" rx="3"/><path d="M3 9.5h18M8 2.5v4M16 2.5v4"/><circle cx="12" cy="15" r="1.4" fill="currentColor" stroke="none"/>'],
    ['href' => '/diary/diary.php', 'key' => 'diary', 'tone' => 'diary',
     'icon' => '<path d="M4 20h4.2L19.3 8.9a2.9 2.9 0 0 0-4.2-4.2L4 15.8z"/><path d="M13.6 6.2l4.2 4.2"/>'],
    ['href' => '/notifications/notifications.php', 'key' => 'notifications', 'tone' => 'notify', 'badge' => true,
     'icon' => '<path d="M18 8.5a6 6 0 0 0-12 0c0 6.5-2.8 8.5-2.8 8.5h17.6S18 15 18 8.5z"/><path d="M13.7 20.5a2 2 0 0 1-3.4 0"/>'],
    ['href' => '/gedagtes/gedagtes.php', 'key' => 'thought_history', 'tone' => 'thought',
     'icon' => '<path d="M9 18h6M10 21h4"/><path d="M12 3a6 6 0 0 0-3.6 10.8c.6.5 1 1.3 1 2.2h5.2c0-.9.4-1.7 1-2.2A6 6 0 0 0 12 3z"/>'],
];

$weekKeys = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

$jsConfig = [
    'lang'       => $lang,
    'bibleLang'  => votd_bible_lang($lang),
    'csrf'       => csrf_token(),
    'votd'       => $votd,
    'maxBack'    => VOTD_MAX_BACK,
    'translationNames' => $translationNames,
    'i18n'       => [
        'today'            => t('today'),
        'copied'           => t('copied'),
        'shareCopied'      => t('wc_share_copied'),
        'bookmarkAdded'    => t('bookmark_added'),
        'bookmarkRemoved'  => t('bookmark_removed'),
        'tryAgain'         => t('wc_try_again'),
        'readMore'         => t('wc_read_more'),
        'readLess'         => t('wc_read_less'),
        'streakDay'        => t('wc_streak_day'),
        'streakDays'       => t('wc_streak_days'),
        'verse'            => t('wc_verse'),
        'chapter'          => t('chapter'),
        'chapters'         => t('wc_chapters'),
        'chooseBook'       => t('choose_book'),
        'chooseChapter'    => t('choose_chapter'),
        'noResults'        => t('no_results'),
        'justNow'          => t('just_now'),
        'minutesAgo'       => t('minutes_ago'),
        'hoursAgo'         => t('hours_ago'),
        'daysAgo'          => t('days_ago'),
        'continueReading'  => t('sum_continue_reading'),
        'startReading'     => t('sum_start_reading'),
        'noReadingYet'     => t('wc_no_reading_yet'),
        'greetMorning'     => t('good_morning'),
        'greetAfternoon'   => t('good_afternoon'),
        'greetEvening'     => t('good_evening'),
    ],
];
?><!doctype html>
<html lang="<?= e($lang) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#0b0a09">
  <title><?= e(t('page_title_welcome')) ?></title>
  <script>document.documentElement.classList.add('wc-js');</script>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500;1,600&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="/welcome/css/welcome.css?v=<?= $VER('/welcome/css/welcome.css') ?>">
</head>
<body class="welcome-body">
  <?php require_once __DIR__ . '/header_footer/header.php'; ?>

  <main class="wc-main" id="wcMain">

    <!-- ===== Greeting ===== -->
    <section class="wc-hello wc-reveal" aria-label="<?= e(t('welcome')) ?>">
      <div class="wc-hello-text">
        <p class="wc-hello-date"><?= e($todayLabel) ?></p>
        <h2 class="wc-hello-title">
          <span data-greet><?= e(t($greetKey)) ?></span><?php if ($firstName !== ''): ?><span class="wc-hello-comma">,</span>
          <span class="wc-hello-name"><?= e($firstName) ?></span><?php endif; ?>
        </h2>
        <?php if ($town || $province): ?>
          <p class="wc-hello-place">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21.5s-7-6.6-7-12a7 7 0 0 1 14 0c0 5.4-7 12-7 12z"/><circle cx="12" cy="9.5" r="2.5"/></svg>
            <?= e($town) ?><?= ($town && $province) ? ', ' : '' ?><?= e($province) ?>
          </p>
        <?php endif; ?>
      </div>
      <a class="wc-streak-chip" href="#wcJourney" aria-label="<?= e(t('day_streak')) ?>">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 22c4.4 0 7.5-3 7.5-7.2 0-4.4-3.4-6.6-4.4-10.3-.2-.7-1-.9-1.5-.4C11.8 6 11 8.3 11.4 10.6c-1.1-.5-1.9-1.6-2.1-2.9-.1-.6-.8-.9-1.3-.5C6 9 4.5 11.6 4.5 14.8 4.5 19 7.6 22 12 22z"/></svg>
        <b data-streak-count>1</b>
      </a>
    </section>

    <!-- ===== Bible search ===== -->
    <form class="wc-search wc-reveal" action="/bible/bible.php" method="get" role="search" id="wcSearch">
      <svg class="wc-search-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20.5 20.5l-4.6-4.6"/></svg>
      <input class="wc-search-input" type="search" name="q" maxlength="120" autocomplete="off" enterkeyhint="search"
             placeholder="<?= e(t('search_bible')) ?>…" data-long-placeholder="<?= e(t('type_search_term')) ?>"
             aria-label="<?= e(t('search_bible')) ?>">
      <button class="wc-search-books" type="button" id="wcOpenBooks" aria-haspopup="dialog" aria-controls="wcBooks">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 6.6C10.2 5.2 7.7 4.5 4.6 4.5H3v13.2h1.6c3.1 0 5.6.7 7.4 2.1 1.8-1.4 4.3-2.1 7.4-2.1H21V4.5h-1.6c-3.1 0-5.6.7-7.4 2.1z"/><path d="M12 6.6v13.2"/></svg>
        <span><?= e(t('wc_books')) ?></span>
      </button>
    </form>

    <?php if ($votd && $votdShown): ?>
    <!-- ===== Verse of the Day ===== -->
    <section class="wc-votd wc-reveal" id="wcVotd" data-sky="<?= e($sky) ?>" aria-labelledby="wcVotdTitle">
      <div class="wc-sky" aria-hidden="true">
        <span class="wc-sky-stars"></span>
        <span class="wc-sky-rays"></span>
        <span class="wc-sky-orb"></span>
        <span class="wc-sky-haze"></span>
        <svg class="wc-sky-hills" viewBox="0 0 800 120" preserveAspectRatio="xMidYMax meet">
          <path class="h1" d="M0 72C110 40 230 58 340 66s200-36 310-32c70 3 120 18 150 24v62H0z"/>
          <path class="h1" d="M648.5 6h3.6v30h-3.6zM640.6 14h19.4v3.4h-19.4z"/>
          <path class="h2" d="M0 92c120-22 230 4 360-6s200-22 320-8c60 7 100 12 120 10v32H0z"/>
          <path class="h3" d="M0 108c140-12 260 6 400-4s260 2 400-4v20H0z"/>
        </svg>
        <span class="wc-sky-grain"></span>
      </div>

      <div class="wc-votd-inner">
        <header class="wc-votd-head">
          <span class="wc-votd-eyebrow" id="wcVotdTitle">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.5l2.2 6.8h7.1l-5.8 4.2 2.2 6.8-5.7-4.2-5.7 4.2 2.2-6.8-5.8-4.2h7.1z"/></svg>
            <?= e(t('verse_of_day')) ?>
          </span>
          <div class="wc-votd-nav">
            <button class="wc-icon-btn" type="button" data-votd-prev aria-label="<?= e(t('previous_day')) ?>">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg>
            </button>
            <button class="wc-icon-btn" type="button" data-votd-next aria-label="<?= e(t('next_day')) ?>" disabled>
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg>
            </button>
          </div>
        </header>

        <figure class="wc-votd-body" data-votd-body aria-live="polite">
          <p class="wc-votd-date" data-votd-date><?= e(t('today')) ?></p>
          <blockquote class="wc-votd-text" data-votd-text data-size="<?= $votdSize ?>" lang="<?= e($votd['primary']) ?>">
            <?= e($votdShown['text']) ?>
          </blockquote>
          <figcaption class="wc-votd-ref">
            <span data-votd-ref><?= e($votdShown['label']) ?></span>
            <span class="wc-votd-trans-name" data-votd-trans-name><?= e($translationNames[$votd['primary']]) ?></span>
          </figcaption>
        </figure>

        <div class="wc-votd-foot">
          <div class="wc-seg wc-seg--glass" role="group" aria-label="<?= e(t('wc_translation')) ?>">
            <?php foreach (['af', 'en'] as $tl): ?>
              <button type="button" data-trans="<?= $tl ?>" aria-pressed="<?= $votd['primary'] === $tl ? 'true' : 'false' ?>"><?= $tl === 'af' ? 'AFR' : 'KJV' ?></button>
            <?php endforeach; ?>
          </div>

          <div class="wc-votd-actions">
            <button class="wc-icon-btn wc-icon-btn--glass" type="button" data-act="bookmark" aria-pressed="false" aria-label="<?= e(t('bookmark')) ?>" title="<?= e(t('bookmark')) ?>">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18.5 21l-6.5-4.6L5.5 21V5a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2z"/></svg>
            </button>
            <button class="wc-icon-btn wc-icon-btn--glass" type="button" data-act="copy" aria-label="<?= e(t('copy')) ?>" title="<?= e(t('copy')) ?>">
              <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="8.5" y="8.5" width="12" height="12" rx="2.5"/><path d="M15.5 8.5V6a2.5 2.5 0 0 0-2.5-2.5H6A2.5 2.5 0 0 0 3.5 6v7A2.5 2.5 0 0 0 6 15.5h2.5"/></svg>
            </button>
            <button class="wc-icon-btn wc-icon-btn--glass" type="button" data-act="share" aria-label="<?= e(t('share')) ?>" title="<?= e(t('share')) ?>">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 15V3.5M7.5 8L12 3.5 16.5 8"/><path d="M5 12.5V18a2.5 2.5 0 0 0 2.5 2.5h9A2.5 2.5 0 0 0 19 18v-5.5"/></svg>
            </button>
          </div>
        </div>

        <a class="wc-votd-read" data-votd-read href="/bible/bible.php?ref=<?= rawurlencode($votdShown['ref']) ?>">
          <span><?= e(t('read_chapter')) ?></span>
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </a>
      </div>
    </section>
    <?php endif; ?>

    <!-- ===== Continue reading + streak ===== -->
    <div class="wc-duo">
      <a class="wc-card wc-continue wc-reveal" id="wcContinue" href="/bible/bible.php">
        <span class="wc-cover" data-cont-cover aria-hidden="true">
          <span class="wc-cover-spine"></span>
          <span class="wc-cover-title" data-cont-initial>&#8203;</span>
        </span>
        <span class="wc-continue-body">
          <span class="wc-eyebrow" data-cont-eyebrow><?= e(t('sum_start_reading')) ?></span>
          <strong class="wc-continue-title" data-cont-title><?= e(t('wc_open_bible')) ?></strong>
          <span class="wc-continue-meta" data-cont-meta><?= e(t('wc_no_reading_yet')) ?></span>
          <span class="wc-progress" aria-hidden="true"><span data-cont-bar></span></span>
        </span>
        <span class="wc-continue-go" aria-hidden="true">
          <svg viewBox="0 0 24 24"><path d="M8 5.5v13l10.5-6.5z"/></svg>
        </span>
      </a>

      <section class="wc-card wc-journey wc-reveal" id="wcJourney" aria-labelledby="wcJourneyTitle">
        <div class="wc-journey-top">
          <span class="wc-flame" aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="M12 22c4.4 0 7.5-3 7.5-7.2 0-4.4-3.4-6.6-4.4-10.3-.2-.7-1-.9-1.5-.4C11.8 6 11 8.3 11.4 10.6c-1.1-.5-1.9-1.6-2.1-2.9-.1-.6-.8-.9-1.3-.5C6 9 4.5 11.6 4.5 14.8 4.5 19 7.6 22 12 22z"/></svg>
          </span>
          <div class="wc-journey-count" id="wcJourneyTitle">
            <strong data-streak-count>1</strong>
            <span data-streak-unit><?= e(t('wc_streak_day')) ?></span>
          </div>
          <span class="wc-journey-best"><?= e(t('wc_streak_best')) ?> <b data-streak-best>1</b></span>
        </div>
        <ol class="wc-week" data-week aria-label="<?= e(t('this_week')) ?>">
          <?php foreach ($weekKeys as $wk): ?>
            <li><span class="wc-week-dot"></span><span class="wc-week-day"><?= e(t($wk)) ?></span></li>
          <?php endforeach; ?>
        </ol>
        <p class="wc-journey-hint"><?= e(t('wc_streak_hint')) ?></p>
      </section>
    </div>

    <!-- ===== My Bible ===== -->
    <section class="wc-section wc-mybible wc-reveal" id="wcMyBible" aria-labelledby="wcMyBibleTitle">
      <div class="wc-section-head">
        <h3 class="wc-section-title" id="wcMyBibleTitle"><?= e(t('wc_my_bible')) ?></h3>
        <a class="wc-section-link" href="/bible/bible.php"><?= e(t('wc_open_bible')) ?></a>
      </div>
      <div class="wc-stats">
        <a class="wc-stat wc-stat--bookmark" href="/bible/bible.php?panel=bookmarks">
          <span class="wc-stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M18.5 21l-6.5-4.6L5.5 21V5a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2z"/></svg></span>
          <b class="wc-stat-num" data-stat="bookmarks">–</b>
          <span class="wc-stat-label"><?= e(t('bookmarks')) ?></span>
        </a>
        <a class="wc-stat wc-stat--highlight" href="/bible/bible.php">
          <span class="wc-stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M9 14.5l-4.5 4.5H9l2-2"/><path d="M14.8 4.2l5 5L11 18l-5-5z"/></svg></span>
          <b class="wc-stat-num" data-stat="highlights">–</b>
          <span class="wc-stat-label"><?= e(t('wc_highlights')) ?></span>
        </a>
        <a class="wc-stat wc-stat--note" href="/bible/bible.php?panel=notes">
          <span class="wc-stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 20h8.5"/><path d="M16.3 3.7a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg></span>
          <b class="wc-stat-num" data-stat="notes">–</b>
          <span class="wc-stat-label"><?= e(t('notes')) ?></span>
        </a>
      </div>
      <a class="wc-latest" data-latest href="/bible/bible.php" hidden>
        <span class="wc-eyebrow"><?= e(t('wc_latest_bookmark')) ?></span>
        <span class="wc-latest-text" data-latest-text></span>
        <span class="wc-latest-ref" data-latest-ref></span>
      </a>
    </section>

    <!-- ===== Explore ===== -->
    <section class="wc-section wc-reveal" aria-labelledby="wcExploreTitle">
      <div class="wc-section-head">
        <h3 class="wc-section-title" id="wcExploreTitle"><?= e(t('wc_explore')) ?></h3>
      </div>
      <div class="wc-rail" role="list">
        <?php foreach ($featured as $f): ?>
          <a class="wc-feature wc-feature--<?= $f['tone'] ?>" href="<?= e($f['href']) ?>" role="listitem">
            <span class="wc-feature-glow" aria-hidden="true"></span>
            <span class="wc-feature-art" aria-hidden="true">
              <svg viewBox="0 0 24 24"><?= $f['icon'] ?></svg>
            </span>
            <span class="wc-feature-text">
              <strong><?= e(t($f['key'])) ?></strong>
              <span><?= e(t($f['desc'])) ?></span>
            </span>
            <span class="wc-feature-arrow" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span>
          </a>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- ===== More in the app ===== -->
    <section class="wc-section wc-reveal" aria-labelledby="wcAppsTitle">
      <div class="wc-section-head">
        <h3 class="wc-section-title" id="wcAppsTitle"><?= e(t('wc_more_features')) ?></h3>
      </div>
      <div class="wc-apps">
        <?php foreach ($apps as $a): ?>
          <a class="wc-app wc-app--<?= $a['tone'] ?>" href="<?= e($a['href']) ?>">
            <span class="wc-app-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24"><?= $a['icon'] ?></svg>
              <?php if (!empty($a['badge'])): ?><span class="wc-app-badge" data-notif-badge hidden></span><?php endif; ?>
            </span>
            <span class="wc-app-label"><?= e(t($a['key'])) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </section>

    <?php if ($dailyThought): ?>
    <!-- ===== Thought of the Day ===== -->
    <section class="wc-section wc-reveal" aria-labelledby="wcThoughtTitle">
      <div class="wc-section-head">
        <h3 class="wc-section-title" id="wcThoughtTitle"><?= e(t('thought_of_day')) ?></h3>
        <a class="wc-section-link" href="/gedagtes/gedagtes.php"><?= e(t('wc_more_thoughts')) ?></a>
      </div>
      <article class="wc-card wc-thought">
        <span class="wc-thought-mark" aria-hidden="true">&ldquo;</span>
        <p class="wc-thought-text"><?= e($dailyThought['content']) ?></p>
        <?php if (!empty($dailyThought['author'])): ?>
          <p class="wc-thought-author"><?= e($dailyThought['author']) ?></p>
        <?php endif; ?>
      </article>
    </section>
    <?php endif; ?>

    <!-- ===== Teaching of the Month ===== -->
    <section class="wc-section wc-reveal" aria-labelledby="wcTeachTitle">
      <div class="wc-section-head">
        <h3 class="wc-section-title" id="wcTeachTitle"><?= e(t('teaching_of_month')) ?></h3>
        <?php if ($hasTeaching): ?>
          <span class="wc-chip">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/></svg>
            <?= $teachingMinutes ?> <?= e(t('wc_min_read')) ?>
          </span>
        <?php endif; ?>
      </div>
      <article class="wc-card wc-teach" id="wcTeach">
        <p class="wc-teach-sub"><?= e(t('grow_faith')) ?></p>
        <?php if ($hasTeaching): ?>
          <div class="wc-teach-body wc-teaching-content" id="wcTeachBody">
            <?= $teachingHtml ?>
          </div>
          <button class="wc-teach-toggle" type="button" id="wcTeachToggle" aria-expanded="false" aria-controls="wcTeachBody" hidden>
            <span data-label><?= e(t('wc_read_more')) ?></span>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
          </button>
        <?php else: ?>
          <div class="wc-empty">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 6.6C10.2 5.2 7.7 4.5 4.6 4.5H3v13.2h1.6c3.1 0 5.6.7 7.4 2.1 1.8-1.4 4.3-2.1 7.4-2.1H21V4.5h-1.6c-3.1 0-5.6.7-7.4 2.1z"/><path d="M12 6.6v13.2"/></svg>
            <p><?= e(t('no_content')) ?></p>
          </div>
        <?php endif; ?>
      </article>
    </section>

    <!-- ===== Benediction ===== -->
    <section class="wc-blessing wc-reveal">
      <span class="wc-blessing-cross" aria-hidden="true">
        <svg viewBox="0 0 24 24"><path d="M10.6 2h2.8v6.2h5.4V11h-5.4v11h-2.8V11H5.2V8.2h5.4z"/></svg>
      </span>
      <blockquote class="wc-blessing-text"><?= e(t('quote_matthew_18_20')) ?></blockquote>
      <cite class="wc-blessing-ref"><?= e(t('quote_matthew_18_20_ref')) ?></cite>
    </section>

  </main>

  <!-- ===== Book picker (bottom sheet) ===== -->
  <div class="wc-sheet" id="wcBooks" role="dialog" aria-modal="true" aria-labelledby="wcBooksTitle" hidden>
    <div class="wc-sheet-backdrop" data-sheet-close></div>
    <div class="wc-sheet-panel">
      <span class="wc-sheet-grip" aria-hidden="true"></span>
      <header class="wc-sheet-head">
        <button class="wc-icon-btn" type="button" data-sheet-back aria-label="<?= e(t('back')) ?>" hidden>
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg>
        </button>
        <h3 class="wc-sheet-title" id="wcBooksTitle"><?= e(t('choose_book')) ?></h3>
        <button class="wc-icon-btn" type="button" data-sheet-close aria-label="<?= e(t('close')) ?>">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
        </button>
      </header>
      <div class="wc-sheet-tools" data-book-tools>
        <div class="wc-seg" role="tablist">
          <button type="button" role="tab" data-testament="ot" aria-selected="true"><?= e(t('old_testament')) ?></button>
          <button type="button" role="tab" data-testament="nt" aria-selected="false"><?= e(t('new_testament')) ?></button>
        </div>
        <input class="wc-sheet-filter" type="search" data-book-filter autocomplete="off"
               placeholder="<?= e(t('wc_filter_books')) ?>" aria-label="<?= e(t('wc_filter_books')) ?>">
      </div>
      <div class="wc-sheet-body">
        <div class="wc-book-grid" data-book-grid></div>
        <div class="wc-chapter-grid" data-chapter-grid hidden></div>
      </div>
    </div>
  </div>

  <div class="wc-toast" id="wcToast" role="status" aria-live="polite"></div>

  <script>window.WELCOME = <?= json_encode($jsConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
  <script src="/welcome/js/welcome.js?v=<?= $VER('/welcome/js/welcome.js') ?>"></script>
  <?php require_once __DIR__ . '/header_footer/footer.php'; ?>

</body>
</html>
