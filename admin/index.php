<?php
declare(strict_types=1);
/**
 * Admin — "Light Through Glass".
 * The welcome page's atmosphere for the admin area; each tab is lit in its
 * own jewel. The tabs are server-side (?tab=…): profile, teaching, daily
 * thought, appointments, settings and approvals, each shown only to the
 * offices that may use it. Every write goes through /admin/api/ with the
 * session's CSRF token (OACUI.post in ui.js).
 */

require_once __DIR__ . '/../security/auth_gate.php';
require_once __DIR__ . '/../includes/languages.php';
require_once __DIR__ . '/../includes/admin_api.php';
require_once __DIR__ . '/../includes/admin_page.php';

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    header('Location: /login.php');
    exit;
}

if (isset($_GET['lang']) && in_array($_GET['lang'], SUPPORTED_LANGS, true)) {
    $_SESSION['language'] = $_GET['lang'];
    // Back to the same tab without ?lang= in the address
    $back = preg_match('/^[a-z]{3,10}$/', (string)($_GET['tab'] ?? '')) ? '?tab=' . $_GET['tab'] : '';
    header('Location: /admin/index.php' . $back);
    exit;
}
$lang = validate_language((string)($_SESSION['language'] ?? 'af'));

function t(string $key): string {
    global $lang;
    return __t($key, $lang);
}
function esc($s): string {
    return admin_e($s);
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

// A tab the member may not use falls back to the profile
$allowed = ['profile' => true, 'teaching' => $isElder, 'gedagte' => $isElder, 'afsprake' => $canManageAmpte, 'settings' => true, 'approvals' => $canApprove];
$activeTab = (string)($_GET['tab'] ?? 'profile');
if (empty($allowed[$activeTab])) {
    $activeTab = 'profile';
}

// Pending approvals for the tab badge (the same scope rules as the tab itself)
$approvalCount = 0;
if ($canApprove) {
    try {
        $scope = admin_approval_scope($currentUser);
        if ($scope !== null) {
            [$where, $params] = admin_approval_where($scope, 'u');
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM users u WHERE {$where}");
            $countStmt->execute($params);
            $approvalCount += (int)$countStmt->fetchColumn();
        }

        $spouseScope = admin_spouse_scope($currentUser);
        if ($spouseScope !== null) {
            [$spouseSql, $spouseParams] = $spouseScope;
            $spouseCountStmt = $pdo->prepare("SELECT COUNT(*) FROM spouse_requests sr
                JOIN users u1 ON u1.id = sr.requester_id
                JOIN users u2 ON u2.id = sr.receiver_id
                WHERE sr.status = 'pending'
                AND {$spouseSql}
                AND u1.spouse_user_id IS NULL
                AND u2.spouse_user_id IS NULL");
            $spouseCountStmt->execute($spouseParams);
            $approvalCount += (int)$spouseCountStmt->fetchColumn();
        }
    } catch (Throwable $e) {
        error_log('Admin approval count: ' . $e->getMessage()); // the badge is informational only
    }
}

$chipName = trim(($currentUser['name'] ?? '') . ' ' . ($currentUser['surname'] ?? ''));
$chipPhoto = admin_photo_url($currentUser['photo'] ?? null);

// The tabs. 'icon' is the inside of a 24x24 stroke icon.
$tabs = [
    ['id' => 'profile', 'key' => 'profile', 'show' => true,
     'icon' => '<path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/>'],
    ['id' => 'teaching', 'key' => 'teaching', 'show' => $isElder,
     'icon' => '<path d="M4 19.5A2.5 2.5 0 016.5 17H20M4 19.5A2.5 2.5 0 006.5 22H20V2H6.5A2.5 2.5 0 004 4.5v15z"/>'],
    ['id' => 'gedagte', 'key' => 'daily_thought', 'show' => $isElder,
     'icon' => '<path d="M9 21h6M10 17h4M12 2a6 6 0 00-3.5 10.9c.6.5 1 1.2 1 2V15h5v-.1c0-.8.4-1.5 1-2A6 6 0 0012 2z"/>'],
    ['id' => 'afsprake', 'key' => 'appointments', 'show' => $canManageAmpte,
     'icon' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M3 10h18M8 2v4M16 2v4"/>'],
    ['id' => 'settings', 'key' => 'settings', 'show' => true,
     'icon' => '<path d="M4 6h10M18 6h2M4 12h2M10 12h10M4 18h12M20 18h0"/><circle cx="16" cy="6" r="2"/><circle cx="8" cy="12" r="2"/><circle cx="18" cy="18" r="2"/>'],
    ['id' => 'approvals', 'key' => 'approvals', 'show' => $canApprove,
     'icon' => '<path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><path d="M22 4L12 14.01l-3-3"/>'],
];

$uiStrings = [
    'ok' => t('confirm'),
    'cancel' => t('cancel'),
    'networkError' => t('network_error'),
];

// Everything admin.js says out loud, in the member's language
$adminStrings = [
    'approved' => t('adm_user_approved'),
    'rejected' => t('adm_user_rejected'),
    'confirmReject' => t('adm_confirm_reject'),
    'confirmSpouseLink' => t('adm_confirm_spouse_link'),
    'confirmSpouseReject' => t('adm_confirm_spouse_reject'),
    'confirmUnlink' => t('confirm_unlink_spouse'),
    'spouseLinked' => t('adm_spouse_linked'),
    'spouseRejected' => t('adm_spouse_rejected'),
    'spouseUnlinked' => t('adm_spouse_unlinked'),
    'noPending' => t('no_pending_approvals'),
    'pwNoMatch' => t('passwords_no_match'),
    'pwMin' => t('adm_pw_min'),
    'pwSame' => t('adm_pw_same'),
    'pwChanged' => t('password_changed'),
    'pwFailed' => t('password_change_failed'),
];
?><!doctype html>
<html lang="<?= esc($lang) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="csrf-token" content="<?= esc(csrf_token()) ?>">
  <title><?= esc(t('admin_dashboard')) ?></title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;1,500;1,600&family=Parisienne&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="<?= admin_asset('/admin/css/admin.css') ?>">
  <link rel="stylesheet" href="<?= admin_asset('/admin/css/ui.css') ?>">
  <?php if ($activeTab === 'gedagte'): ?>
  <link rel="stylesheet" href="<?= admin_asset('/admin/css/gedagte.css') ?>">
  <?php elseif ($activeTab === 'afsprake'): ?>
  <link rel="stylesheet" href="<?= admin_asset('/admin/css/afsprake.css') ?>">
  <?php endif; ?>
  <script>
  window.OAC_UI_STRINGS = <?= json_encode($uiStrings, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  </script>
  <script src="<?= admin_asset('/admin/js/ui.js') ?>"></script>
</head>
<body class="admin-body" data-tab="<?= esc($activeTab) ?>">
  <?php require_once __DIR__ . '/../header_footer/header.php'; ?>

  <div class="admin-aura" aria-hidden="true"><div class="admin-rays"></div><div class="admin-halo"></div></div>

  <main class="admin-main" id="adminMain">
    <section class="admin-hero" aria-labelledby="adminTitle">
      <p class="admin-eyebrow">
        <svg class="admin-eyebrow-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/></svg>
        <span><?= esc(t('adm_eyebrow')) ?></span>
      </p>
      <h2 class="admin-title" id="adminTitle"><span><?= esc(t('admin_dashboard')) ?></span></h2>
      <p class="admin-sub"><?= esc(t('admin_subtitle')) ?></p>
      <div class="admin-user-chip">
        <?php if ($chipPhoto !== ''): ?>
        <img src="<?= esc($chipPhoto) ?>" alt="" width="30" height="30" decoding="async">
        <?php else: ?>
        <span class="admin-chip-initials" aria-hidden="true"><?= esc(admin_initials($chipName)) ?></span>
        <?php endif; ?>
        <strong><?= esc($chipName) ?></strong>
        <?php if (!empty($currentUser['amp_name'])): ?>
        <span class="admin-chip-role"><?= esc($currentUser['amp_name']) ?></span>
        <?php endif; ?>
      </div>
    </section>

    <nav class="admin-tabs" aria-label="<?= esc(t('admin_dashboard')) ?>">
      <?php foreach ($tabs as $tab): if (!$tab['show']) continue; $isActive = $activeTab === $tab['id']; ?>
      <a href="?tab=<?= esc($tab['id']) ?>" class="admin-tab admin-tab--<?= esc($tab['id']) ?><?= $isActive ? ' active' : '' ?>"<?= $isActive ? ' aria-current="page"' : '' ?>>
        <span class="admin-tab-orb"><svg viewBox="0 0 24 24" aria-hidden="true"><?= $tab['icon'] ?></svg></span>
        <span class="admin-tab-label"><?= esc(t($tab['key'])) ?></span>
        <?php if ($tab['id'] === 'approvals' && $approvalCount > 0): ?>
        <span class="admin-tab-badge" aria-label="<?= (int)$approvalCount ?>"><?= $approvalCount > 99 ? '99+' : (int)$approvalCount ?></span>
        <?php endif; ?>
      </a>
      <?php endforeach; ?>
    </nav>

    <div class="admin-tabpanels">
      <?php require __DIR__ . '/tabs/' . $activeTab . '.php'; ?>
    </div>

    <!-- Closing verse, by candlelight -->
    <section class="admin-bene">
      <div class="admin-candle" aria-hidden="true"><i class="admin-flame"></i><i class="admin-wick"></i><i class="admin-wax"></i></div>
      <blockquote class="admin-quote">
        <p class="admin-quote-text"><?= esc(t('quote_matthew_18_20')) ?></p>
        <cite class="admin-quote-source"><?= esc(t('quote_matthew_18_20_ref')) ?></cite>
      </blockquote>
    </section>
  </main>

  <script>
  window.OAC_ADMIN = <?= json_encode($adminStrings, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  </script>
  <script src="<?= admin_asset('/admin/js/admin.js') ?>"></script>

  <?php require_once __DIR__ . '/../header_footer/footer.php'; ?>
</body>
</html>
