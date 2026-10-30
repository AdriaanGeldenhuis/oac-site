<?php
declare(strict_types=1);
/**
 * Admin ("Bestuur") — a plain, calm work tool.
 * One navigation (a sticky row of tabs on the phone, a side column on a
 * wide screen) with a red count on every tab where something waits, and
 * the Overview as the start page: what waits for you first, then your tools.
 * The tabs are server-side (?tab=…): overview, approvals, afsprake,
 * teaching, gedagte and profile ("My account", which also holds the
 * password form), each shown only to the offices that may use it.
 * Every write goes through /admin/api/ with the session's CSRF token
 * (OACUI.post in ui.js). People are always named office first:
 * "Oudste Kobus Nel" (admin_person()).
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
    tw.name AS town_name, pr.name AS province_name, cg.name AS congregation_name
    FROM users u
    LEFT JOIN towns tw ON tw.id = u.town_id
    LEFT JOIN provinces pr ON pr.id = tw.province_id
    LEFT JOIN congregations cg ON cg.id = u.congregation_id
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

// A tab the member may not use falls back to the overview.
// "settings" is the old address of the password form, now on My account.
$allowed = ['overview' => true, 'approvals' => $canApprove, 'afsprake' => $canManageAmpte, 'teaching' => $isElder, 'gedagte' => $isElder, 'profile' => true];
$activeTab = (string)($_GET['tab'] ?? 'overview');
if ($activeTab === 'settings') {
    $activeTab = 'profile';
}
if (empty($allowed[$activeTab])) {
    $activeTab = 'overview';
}

// What waits, by the same scope rules as the lists (includes/admin_api.php)
$counts = admin_pending_counts($pdo, $currentUser);

$meLabel = admin_person($currentUser, $lang);
$meName = admin_full_name($currentUser);

// The navigation: [tab, label, icon, count]
$nav = [
    ['overview', t('adm_nav_overview'), 'home', 0],
    ['approvals', t('approvals'), 'approvals', $counts['approvals']],
    ['afsprake', t('appointments'), 'calendar', $counts['appointments']],
    ['teaching', t('teaching'), 'teaching', 0],
    ['gedagte', t('daily_thought'), 'thought', 0],
    ['profile', t('adm_nav_account'), 'user', $counts['mine']],
];

// The heading of each tab (the overview greets instead)
$heads = [
    'approvals' => [t('approvals'), t('adm_tool_approvals')],
    'afsprake' => [t('appointments'), t('adm_tool_afsprake')],
    'teaching' => [t('monthly_teaching'), t('edit_teaching_desc')],
    'gedagte' => [t('daily_thought'), t('adm_tool_gedagte')],
    'profile' => [t('adm_nav_account'), t('adm_tool_account')],
];

$waitingAria = static function (int $n): string {
    return str_replace('{n}', (string)$n, t('adm_waiting_aria'));
};

$uiStrings = [
    'ok' => t('confirm'),
    'cancel' => t('cancel'),
    'networkError' => t('network_error'),
];

// Everything admin.js says out loud, in the member's language
$adminStrings = [
    'lang' => $lang,
    'today' => t('today'),
    'tomorrow' => t('tomorrow'),
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
    'waitingAria' => t('adm_waiting_aria'),
    'pwNoMatch' => t('passwords_no_match'),
    'pwMin' => t('adm_pw_min'),
    'pwSame' => t('adm_pw_same'),
    'pwChanged' => t('password_changed'),
    'pwFailed' => t('password_change_failed'),
    'pwShow' => t('adm_pw_show'),
    'pwHide' => t('adm_pw_hide'),
];
?><!doctype html>
<html lang="<?= esc($lang) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="csrf-token" content="<?= esc(csrf_token()) ?>">
  <title><?= esc(t('adm_eyebrow') . ' · ' . ($activeTab === 'overview' ? t('adm_nav_overview') : $heads[$activeTab][0])) ?></title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="<?= admin_asset('/admin/css/bestuur.css') ?>">
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
<body class="adm" data-tab="<?= esc($activeTab) ?>">
  <?php require_once __DIR__ . '/../header_footer/header.php'; ?>

  <div class="adm-shell">
    <nav class="adm-nav" aria-label="<?= esc(t('adm_eyebrow')) ?>">
      <div class="adm-nav-me">
        <?= admin_avatar($currentUser['photo'] ?? null, $meName, 'adm-avatar') ?>
        <div class="adm-nav-me-text">
          <strong><?= esc($meLabel) ?></strong>
          <?php if (!empty($currentUser['congregation_name'])): ?>
          <span><?= esc($currentUser['congregation_name']) ?></span>
          <?php endif; ?>
        </div>
      </div>
      <ul class="adm-nav-list">
        <?php foreach ($nav as [$id, $label, $icon, $count]): if (empty($allowed[$id])) continue; $on = $activeTab === $id; ?>
        <li>
          <a href="?tab=<?= esc($id) ?>" class="adm-nav-link<?= $on ? ' is-active' : '' ?>"<?= $on ? ' aria-current="page"' : '' ?>>
            <?= admin_icon($icon) ?>
            <span class="adm-nav-label"><?= esc($label) ?></span>
            <?php if ($id !== 'overview'): ?>
            <span class="adm-badge" data-badge="<?= esc($id) ?>" aria-label="<?= esc($waitingAria((int)$count)) ?>"<?= $count > 0 ? '' : ' hidden' ?>><?= $count > 99 ? '99+' : (int)$count ?></span>
            <?php endif; ?>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>
    </nav>

    <main class="adm-main" id="adminMain">
      <?php if ($activeTab !== 'overview'): ?>
      <header class="adm-head">
        <p class="adm-kicker"><?= admin_icon('shield') ?><span><?= esc(t('adm_eyebrow')) ?></span></p>
        <h2 class="adm-title"><?= esc($heads[$activeTab][0]) ?></h2>
        <p class="adm-sub"><?= esc($heads[$activeTab][1]) ?></p>
      </header>
      <?php endif; ?>

      <?php require __DIR__ . '/tabs/' . $activeTab . '.php'; ?>
    </main>
  </div>

  <script>
  window.OAC_ADMIN = <?= json_encode($adminStrings, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  </script>
  <script src="<?= admin_asset('/admin/js/admin.js') ?>"></script>

  <?php require_once __DIR__ . '/../header_footer/footer.php'; ?>
</body>
</html>
