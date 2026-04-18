<?php
// Cache-busted: v3
declare(strict_types=1);
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
require_once __DIR__ . '/security/auth_gate.php';
require_once __DIR__ . '/includes/languages.php';
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

$VER = time();

// Quick navigation items
$navItems = [
    ['href' => '/gospel_media/gospel.php', 'key' => 'gospel_media', 'icon' => '<path d="M21 3H3c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h5v2h8v-2h5c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 14H3V5h18v12zM10 15l5.5-4.5L10 6v9z"/>'],
    ['href' => '/prayers/prayers.php', 'key' => 'prayers', 'icon' => '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>'],
    ['href' => '/aislimbybel/aislimbybel.php', 'key' => 'ai_smart_bible', 'icon' => '<path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/>'],
    ['href' => '/bible/bible.php', 'key' => 'bible', 'icon' => '<path d="M18 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM6 4h5v8l-2.5-1.5L6 12V4z"/>'],
    ['href' => '/calendar/calendar.php', 'key' => 'calendar', 'icon' => '<path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM9 10H7v2h2v-2zm4 0h-2v2h2v-2zm4 0h-2v2h2v-2z"/>'],
    ['href' => '/diary/diary.php', 'key' => 'diary', 'icon' => '<path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>'],
    ['href' => '/singemmanuel/singemmanuel.php', 'key' => 'sing_emmanuel', 'icon' => '<path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/>'],
    ['href' => '/notifications/notifications.php', 'key' => 'notifications', 'icon' => '<path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.89 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/>'],
    ['href' => '/gedagtes/gedagtes.php', 'key' => 'thought_history', 'icon' => '<path d="M9 21c0 .55.45 1 1 1h4c.55 0 1-.45 1-1v-1H9v1zm3-19C8.14 2 5 5.14 5 9c0 2.38 1.19 4.47 3 5.74V17c0 .55.45 1 1 1h6c.55 0 1-.45 1-1v-2.26c1.81-1.27 3-3.36 3-5.74 0-3.86-3.14-7-7-7z"/>'],
];
?><!doctype html>
<html lang="<?= $lang ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= t('page_title_welcome') ?></title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Parisienne&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="/welcome/css/welcome.css?v=<?= $VER ?>">
</head>
<body class="welcome-body">
  <?php require_once __DIR__ . '/header_footer/header.php'; ?>

  <main class="wc-main">

    <!-- Greeting -->
    <div class="wc-greeting">
      <h2 class="wc-page-title"><?= t('welcome_title') ?></h2>
      <?php if ($town || $province): ?>
        <p class="wc-location">
          <svg class="wc-location-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
          <?= htmlspecialchars($town ?: '') ?><?= ($town && $province) ? ', ' : '' ?><?= htmlspecialchars($province ?: '') ?>
        </p>
      <?php endif; ?>
    </div>

    <!-- Quick Navigation -->
    <section class="wc-nav-section">
      <h3 class="wc-section-label"><?= t('quick_navigation') ?></h3>
      <div class="wc-nav-grid">
        <?php foreach ($navItems as $item): ?>
          <a href="<?= $item['href'] ?>" class="wc-nav-card">
            <span class="wc-nav-icon-wrap">
              <svg class="wc-nav-icon" viewBox="0 0 24 24" fill="currentColor"><?= $item['icon'] ?></svg>
            </span>
            <span class="wc-nav-label"><?= t($item['key']) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- Thought of the Day -->
    <?php if ($dailyThought): ?>
    <section class="wc-thought-section">
      <h3 class="wc-section-label"><?= t('thought_of_day') ?></h3>
      <div class="wc-thought-card">
        <div class="wc-thought-content"><?= htmlspecialchars($dailyThought['content']) ?></div>
        <?php if (!empty($dailyThought['author'])): ?>
          <div class="wc-thought-author">&mdash; <?= htmlspecialchars($dailyThought['author']) ?></div>
        <?php endif; ?>
      </div>
    </section>
    <?php endif; ?>

    <!-- Teaching Section -->
    <section class="wc-teaching-section">
      <h3 class="wc-section-label"><?= t('teaching_of_month') ?></h3>
      <p class="wc-section-subtitle"><?= t('grow_faith') ?></p>
      <div class="wc-teaching-card">
        <div class="wc-card-decoration"></div>
        <div class="wc-teaching-content">
          <?php
            if ($contentFile && is_readable($contentFile)) {
              readfile($contentFile);
            } else {
              echo '<div class="wc-no-content">
                      <svg viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                      </svg>
                      <p>' . t('no_content') . '</p>
                    </div>';
            }
          ?>
        </div>
      </div>
    </section>

    <!-- Quote Section -->
    <section class="wc-quote-section">
      <blockquote class="wc-quote-card">
        <p class="wc-quote-text"><?= t('quote_matthew_18_20') ?></p>
        <cite class="wc-quote-source"><?= t('quote_matthew_18_20_ref') ?></cite>
      </blockquote>
    </section>

  </main>

  <script src="/welcome/js/welcome.js?v=<?= $VER ?>"></script>
  <?php require_once __DIR__ . '/header_footer/footer.php'; ?>

</body>
</html>
