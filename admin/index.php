<?php
declare(strict_types=1);
require_once __DIR__ . '/../security/auth_gate.php';
require_once __DIR__ . '/../includes/languages.php';

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
function esc($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$userId = (int)($_SESSION['user_id'] ?? 0);
if (!$userId) {
    header('Location: /login.php');
    exit;
}

$stmt = $pdo->prepare("SELECT u.*, 
    CASE WHEN u.gender='vrou' THEN a.female_name ELSE a.male_name END AS amp_name
    FROM users u 
    LEFT JOIN amptes a ON a.id = u.amp_id 
    WHERE u.id = ? LIMIT 1");
$stmt->execute([$userId]);
$currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$currentUser) {
    header('Location: /logout.php');
    exit;
}

$ampId = (int)($currentUser['amp_id'] ?? 0);
$isElder = ($ampId >= 1 && $ampId <= 5);
$canApprove = ($ampId >= 1 && $ampId <= 6);
$canManageAmpte = ($ampId >= 1 && $ampId <= 9);

$activeTab = $_GET['tab'] ?? 'profile';
$validTabs = ['profile', 'accounts', 'teaching', 'amptes', 'afsprake', 'settings', 'approvals'];
if (!in_array($activeTab, $validTabs)) {
    $activeTab = 'profile';
}

$VER = time();
?><!doctype html>
<html lang="<?= $lang ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= t('admin_dashboard') ?></title>
  
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
  
  <link rel="stylesheet" href="/admin/css/admin.css?v=<?= $VER ?>">
</head>
<body class="admin-body">
  <?php require_once __DIR__ . '/../header_footer/header.php'; ?>

  <div class="admin-hero">
    <div class="admin-hero-glow"></div>
    <div class="admin-hero-content">
      <h1 class="admin-hero-title"><?= t('admin_dashboard') ?></h1>
      <p class="admin-hero-subtitle"><?= t('admin_subtitle') ?></p>
    </div>
  </div>

  <main class="admin-main">
    <nav class="admin-tabs">
      <a href="?tab=profile" class="admin-tab <?= $activeTab === 'profile' ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2M12 11a4 4 0 100-8 4 4 0 000 8z" stroke="currentColor" stroke-width="2"/>
        </svg>
        <span><?= t('profile') ?></span>
      </a>

      <a href="?tab=accounts" class="admin-tab <?= $activeTab === 'accounts' ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <rect x="3" y="3" width="18" height="18" rx="2" stroke="currentColor" stroke-width="2"/>
        </svg>
        <span><?= t('accounts') ?></span>
      </a>

      <?php if ($isElder): ?>
      <a href="?tab=teaching" class="admin-tab <?= $activeTab === 'teaching' ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M4 19.5A2.5 2.5 0 016.5 17H20M4 19.5A2.5 2.5 0 016.5 22H20V2H6.5A2.5 2.5 0 004 4.5v15z" stroke="currentColor" stroke-width="2"/>
        </svg>
        <span><?= t('teaching') ?></span>
      </a>
      <?php endif; ?>

      <?php if ($canManageAmpte): ?>
      <a href="?tab=amptes" class="admin-tab <?= $activeTab === 'amptes' ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8zM23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75" stroke="currentColor" stroke-width="2"/>
        </svg>
        <span><?= t('offices') ?></span>
      </a>

      <a href="?tab=afsprake" class="admin-tab <?= $activeTab === 'afsprake' ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <rect x="3" y="4" width="18" height="18" rx="2" stroke="currentColor" stroke-width="2"/>
          <line x1="3" y1="10" x2="21" y2="10" stroke="currentColor" stroke-width="2"/>
        </svg>
        <span><?= t('appointments') ?></span>
      </a>
      <?php endif; ?>

      <a href="?tab=settings" class="admin-tab <?= $activeTab === 'settings' ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/>
          <path d="M12 1v6m0 6v6M23 12h-6m-6 0H1" stroke="currentColor" stroke-width="2"/>
        </svg>
        <span><?= t('settings') ?></span>
      </a>

      <?php if ($canApprove): ?>
      <a href="?tab=approvals" class="admin-tab <?= $activeTab === 'approvals' ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M22 11.08V12a10 10 0 11-5.93-9.14" stroke="currentColor" stroke-width="2"/>
          <polyline points="22 4 12 14.01 9 11.01" stroke="currentColor" stroke-width="2"/>
        </svg>
        <span><?= t('approvals') ?></span>
      </a>
      <?php endif; ?>
    </nav>

    <div class="admin-tabpanels">
      <?php
      switch ($activeTab) {
        case 'profile':
          require __DIR__ . '/tabs/profile.php';
          break;
        case 'accounts':
          require __DIR__ . '/tabs/accounts.php';
          break;
        case 'teaching':
          if ($isElder) require __DIR__ . '/tabs/teaching.php';
          break;
        case 'amptes':
          if ($canManageAmpte) require __DIR__ . '/tabs/amptes.php';
          break;
        case 'afsprake':
          if ($canManageAmpte) require __DIR__ . '/tabs/afsprake.php';
          break;
        case 'settings':
          require __DIR__ . '/tabs/settings.php';
          break;
        case 'approvals':
          if ($canApprove) require __DIR__ . '/tabs/approvals.php';
          break;
        default:
          require __DIR__ . '/tabs/profile.php';
      }
      ?>
    </div>
  </main>

  <script src="/admin/js/admin.js?v=<?= $VER ?>"></script>

  <?php require_once __DIR__ . '/../header_footer/footer.php'; ?>
</body>
</html>