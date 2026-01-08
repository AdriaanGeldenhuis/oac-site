<?php
// =====================================================================
// /notifications/notifications.php — AI Notifications
// =====================================================================

require_once __DIR__ . '/../security/auth_gate.php';
require_once __DIR__ . '/../includes/languages.php';

// -------------------------- Taal --------------------------------------
$pageLang = $_SESSION['language'] ?? 'af';
if (isset($_GET['lang']) && in_array($_GET['lang'], SUPPORTED_LANGS, true)) {
  $_SESSION['language'] = $_GET['lang'];
  $pageLang = $_GET['lang'];
  header('Location: /notifications/notifications.php');
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
  <title><?= esc(t('ai_notifications')) ?></title>
  
  <!-- Fonts -->
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
  
  <link rel="stylesheet" href="/notifications/css/notifications.css?v=<?= time() ?>">
</head>
<body class="notif-body">
  <?php require_once __DIR__ . '/../header_footer/header.php'; ?>

  <!-- Hero Section -->
  <div class="notif-hero">
    <div class="notif-hero-glow"></div>
    <div class="notif-hero-content">
      <h1 class="notif-hero-title"><?= esc(t('ai_notifications')) ?></h1>
      <p class="notif-hero-subtitle"><?= esc(t('stay_updated')) ?></p>
    </div>
    <div class="notif-sparkles">
      <span class="notif-sparkle" style="--delay: 0s; --x: 10%; --y: 20%;"></span>
      <span class="notif-sparkle" style="--delay: 0.5s; --x: 85%; --y: 30%;"></span>
      <span class="notif-sparkle" style="--delay: 1s; --x: 50%; --y: 60%;"></span>
      <span class="notif-sparkle" style="--delay: 1.5s; --x: 20%; --y: 80%;"></span>
      <span class="notif-sparkle" style="--delay: 2s; --x: 90%; --y: 70%;"></span>
    </div>
  </div>

  <main class="notif-main">
    <!-- Actions Section -->
    <section class="notif-section">
      <div class="notif-actions">
        <button class="notif-btn notif-btn-primary" id="markAllRead">
          <span class="notif-btn-shine"></span>
          <svg class="notif-btn-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M9 11l3 3L22 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          <span class="notif-btn-text"><?= esc(t('mark_all_read')) ?></span>
        </button>

        <a href="/admin/fcm-debug.php" class="notif-btn notif-btn-secondary" style="text-decoration:none;">
          <svg class="notif-btn-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          <span class="notif-btn-text">FCM Debug</span>
        </a>

        <button class="notif-btn notif-btn-secondary" id="filterToggle">
          <svg class="notif-btn-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          <span class="notif-btn-text"><?= esc(t('filter')) ?></span>
        </button>
      </div>

      <!-- Filter Options (hidden by default) -->
      <div class="notif-filters" id="filterOptions" hidden>
        <button class="notif-filter-btn active" data-filter="all"><?= esc(t('all')) ?></button>
        <button class="notif-filter-btn" data-filter="unread"><?= esc(t('unread')) ?></button>
        <button class="notif-filter-btn" data-filter="reminder"><?= esc(t('reminders')) ?></button>
        <button class="notif-filter-btn" data-filter="info"><?= esc(t('info')) ?></button>
      </div>
    </section>

    <!-- Notifications List Section -->
    <section class="notif-section">
      <div class="notif-section-header">
        <div class="notif-icon-wrapper">
          <svg class="notif-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 01-3.46 0" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>
        <h2 class="notif-section-title"><?= esc(t('your_notifications')) ?></h2>
        <div class="notif-loading" id="notifLoading" hidden>
          <div class="notif-spinner"></div>
        </div>
      </div>

      <div class="notif-list" id="notifList">
        <!-- Populated by JS -->
      </div>
    </section>
  </main>

  <script>
    window.LANG = '<?= $pageLang ?>';
    window.T = {
      noNotifications: '<?= esc(t('no_notifications')) ?>',
      markRead: '<?= esc(t('mark_as_read')) ?>',
      delete: '<?= esc(t('delete')) ?>',
      confirm: '<?= esc(t('are_you_sure')) ?>',
      success: '<?= esc(t('success')) ?>',
      error: '<?= esc(t('error')) ?>',
      justNow: '<?= esc(t('just_now')) ?>',
      minutesAgo: '<?= esc(t('minutes_ago')) ?>',
      hoursAgo: '<?= esc(t('hours_ago')) ?>',
      daysAgo: '<?= esc(t('days_ago')) ?>'
    };
  </script>
  <script src="/notifications/js/notifications.js?v=<?= time() ?>"></script>

  <?php require_once __DIR__ . '/../header_footer/footer.php'; ?>

</body>
</html>