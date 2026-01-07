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

$VER = time();
?><!doctype html>
<html lang="<?= $lang ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= t('page_title_welcome') ?></title>
  
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
  
  <link rel="stylesheet" href="/welcome/css/welcome.css?v=<?= $VER ?>">
</head>
<body class="welcome-body">
  <?php require_once __DIR__ . '/header_footer/header.php'; ?>
  <!-- Hero Section -->
  <div class="wc-hero">
    <div class="wc-hero-glow"></div>
    <div class="wc-hero-content">
      <h1 class="wc-hero-title"><?= t('welcome_title') ?></h1>
      <?php if ($town || $province): ?>
        <p class="wc-hero-location">
          <svg class="wc-location-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z" fill="currentColor"/>
          </svg>
          <?= htmlspecialchars($town ?: '') ?><?= ($town && $province) ? ', ' : '' ?><?= htmlspecialchars($province ?: '') ?>
        </p>
      <?php endif; ?>
    </div>
    <div class="wc-sparkles">
      <span class="wc-sparkle" style="--delay: 0s; --x: 15%; --y: 25%;"></span>
      <span class="wc-sparkle" style="--delay: 0.5s; --x: 80%; --y: 35%;"></span>
      <span class="wc-sparkle" style="--delay: 1s; --x: 45%; --y: 65%;"></span>
      <span class="wc-sparkle" style="--delay: 1.5s; --x: 25%; --y: 75%;"></span>
      <span class="wc-sparkle" style="--delay: 2s; --x: 85%; --y: 65%;"></span>
    </div>
    <div class="wc-hero-scroll">
      <div class="wc-scroll-arrow"></div>
    </div>
  </div>

  <main class="wc-main">
    

    <!-- Teaching Section -->
    <section class="wc-section wc-teaching-section">
      <div class="wc-section-header">
        <div class="wc-icon-wrapper">
          <svg class="wc-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z" fill="currentColor"/>
          </svg>
        </div>
        <div>
          <h2 class="wc-section-title"><?= t('teaching_of_month') ?></h2>
          <p class="wc-section-subtitle"><?= t('grow_faith') ?></p>
        </div>
      </div>

      <div class="wc-teaching-card">
        <div class="wc-card-decoration"></div>
        <div class="wc-teaching-content">
          <?php
            if ($contentFile && is_readable($contentFile)) {
              readfile($contentFile);
            } else {
              echo '<div class="wc-no-content">
                      <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z" fill="currentColor"/>
                      </svg>
                      <p>' . t('no_content') . '</p>
                    </div>';
            }
          ?>
        </div>
      </div>
    </section>

    <!-- Quote Section -->
    <section class="wc-section wc-quote-section">
      <div class="wc-quote-card">
        <div class="wc-quote-icon">❝</div>
        <blockquote class="wc-quote-text">
          <?= t('quote_matthew_18_20') ?>
        </blockquote>
        <cite class="wc-quote-source"><?= t('quote_matthew_18_20_ref') ?></cite>
      </div>
    </section>
  </main>

  <script src="/welcome/js/welcome.js?v=<?= $VER ?>"></script>

  <?php require_once __DIR__ . '/header_footer/footer.php'; ?>

</body>
</html>