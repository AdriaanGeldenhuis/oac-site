<?php
declare(strict_types=1);
/**
 * A member's profile — "Light Through Glass", lit in rose like the admin
 * profile tab. Built on the admin's shared styles (admin.css tokens, cards,
 * buttons; ui.css/ui.js toasts and confirm dialogs), profile.css only adds
 * what is its own.
 *
 * Who may do what is decided below and is unchanged: change the office and
 * delete (a higher office, in the same congregation / town as the rule
 * says; the APIs check it again), view and export the calendar
 * (cal_view_rights()). The office list only offers what the server accepts.
 */
require_once __DIR__ . '/../security/auth_gate.php';
require_once __DIR__ . '/../includes/languages.php';
require_once __DIR__ . '/../includes/admin_page.php';
require_once __DIR__ . '/../calendar/lib/calendar.php';

$lang = $_SESSION['language'] ?? 'af';
if (isset($_GET['lang']) && in_array($_GET['lang'], SUPPORTED_LANGS, true)) {
    $lang = $_GET['lang'];
    $_SESSION['language'] = $lang;
}
$lang = validate_language((string)$lang);
// Translation helper using central 5-language system
function t(string $key): string {
    global $lang;
    return __t($key, $lang);
}
function esc($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$meId = (int)($_SESSION['user_id'] ?? 0);
// ?u=ID (older links, such as the birthday notifications, use ?user_id=ID)
$rawTargetId = $_GET['u'] ?? ($_GET['user_id'] ?? null);
$targetId = is_numeric($rawTargetId) ? (int)$rawTargetId : $meId;

if ($targetId <= 0) {
    header('Location: /welcome.php');
    exit;
}

// Load target user
$stmt = $pdo->prepare("SELECT u.*,
    CASE WHEN u.gender='vrou' THEN a.female_name ELSE a.male_name END AS amp_name,
    t.name AS town_name, c.name AS congregation_name, p.name AS province_name
    FROM users u
    LEFT JOIN amptes a ON a.id = u.amp_id
    LEFT JOIN towns t ON t.id = u.town_id
    LEFT JOIN congregations c ON c.id = u.congregation_id
    LEFT JOIN provinces p ON p.id = t.province_id
    WHERE u.id = ? LIMIT 1");
$stmt->execute([$targetId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    http_response_code(404);
    die('User not found');
}

// Load my permissions
$stmt = $pdo->prepare("SELECT amp_id, town_id, congregation_id FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$meId]);
$me = $stmt->fetch(PDO::FETCH_ASSOC);

$myAmpId = (int)($me['amp_id'] ?? 10);
$myTownId = (int)($me['town_id'] ?? 0);
$myCongId = (int)($me['congregation_id'] ?? 0);

$targetAmpId = (int)($user['amp_id'] ?? 10);
$targetTownId = (int)($user['town_id'] ?? 0);
$targetCongId = (int)($user['congregation_id'] ?? 0);

// Determine permissions
$canEdit = false;
$canViewCalendar = false;
$canExportCalendar = false;
$canDelete = false;

if ($meId === $targetId) {
    $canViewCalendar = true;
} else {
    if ($myAmpId < $targetAmpId) {
        $canEdit = true;
        $canDelete = true;
        $canViewCalendar = true;

        if ($myAmpId >= 6) {
            if ($myCongId !== $targetCongId) {
                $canEdit = false;
                $canDelete = false;
            }
        } elseif ($myAmpId >= 2) {
            if ($myTownId !== $targetTownId) {
                $canEdit = false;
                $canDelete = false;
            }
        }
    } elseif ($myAmpId <= 5 && $myTownId === $targetTownId) {
        $canViewCalendar = true;
    }
}

// Calendar buttons follow the calendar's own rule, so they never lead to
// "access denied" and export is only offered where the server allows it
$calRights = cal_view_rights(['id' => $meId] + ($me ?: []), ['id' => $targetId] + $user);
$canViewCalendar = $calRights['view'];
$canExportCalendar = $calRights['export'];

$isMe = $meId === $targetId;
$fullName = trim(($user['name'] ?? '') . ' ' . ($user['surname'] ?? ''));
$gender = (string)($user['gender'] ?? 'man');
$officeName = !empty($user['amp_id']) ? get_translated_office((int)$user['amp_id'], $gender, $lang) : '';

// The photo as the admin pages show it (an old relative path lives under /assets/uploads/)
$photo = trim((string)($user['photo'] ?? ''));
if ($photo !== '' && strpos($photo, 'http') !== 0 && strpos($photo, '/') !== 0) {
    $photo = '/assets/uploads/' . $photo;
}
$photo = admin_photo_url($photo);
$initials = admin_initials($fullName);

// A date in the member's language: "12 April 1968"
$monthKeys = ['january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december'];
$birthdate = '';
if (!empty($user['birthdate'])) {
    $bd = DateTimeImmutable::createFromFormat('!Y-m-d', substr((string)$user['birthdate'], 0, 10));
    if ($bd) {
        $birthdate = (int)$bd->format('j') . ' ' . t($monthKeys[(int)$bd->format('n') - 1]) . ' ' . $bd->format('Y');
    }
}

// Get spouse if exists
$spouse = null;
if (!empty($user['spouse_user_id'])) {
    $spouseStmt = $pdo->prepare("SELECT id, name, surname, photo FROM users WHERE id = ? LIMIT 1");
    $spouseStmt->execute([$user['spouse_user_id']]);
    $spouse = $spouseStmt->fetch(PDO::FETCH_ASSOC);
}

// The offices this member may be given: only those the server accepts (below my own)
$officeOptions = [];
if ($canEdit) {
    $ampStmt = $pdo->query("SELECT id FROM amptes ORDER BY id");
    foreach ($ampStmt->fetchAll(PDO::FETCH_COLUMN) as $ampOption) {
        if ((int)$ampOption > $myAmpId) {
            $officeOptions[(int)$ampOption] = get_translated_office((int)$ampOption, $gender, $lang);
        }
    }
}

$hasActions = $isMe || $canViewCalendar || $canExportCalendar || $canEdit || $canDelete;

$uiStrings = ['ok' => t('confirm'), 'cancel' => t('cancel'), 'networkError' => t('network_error')];
$profileData = [
    'userId' => $targetId,
    'name' => $fullName,
    't' => [
        'saved' => t('saved'),
        'deleted' => t('deleted_success'),
        'deleteQ' => str_replace('{name}', $fullName, t('prf_delete_q')),
        'cannotUndo' => t('cal_cannot_undo'),
        'delete' => t('delete'),
        'exporting' => t('cal_exporting'),
    ],
];
$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

/** One row of an info card: an icon, a label and a value (or a link) */
function prf_row(string $icon, string $label, string $value, string $href = '', bool $highlight = false): void {
    if ($value === '') return;
    ?>
    <div class="prf-row">
      <span class="prf-row-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><?= $icon ?></svg></span>
      <div class="prf-row-text">
        <span class="prf-row-label"><?= esc($label) ?></span>
        <?php if ($href !== ''): ?>
        <a class="prf-row-value prf-row-link" href="<?= esc($href) ?>"><?= esc($value) ?></a>
        <?php else: ?>
        <span class="prf-row-value<?= $highlight ? ' prf-row-value--accent' : '' ?>"><?= esc($value) ?></span>
        <?php endif; ?>
      </div>
    </div>
    <?php
}

$icons = [
    'mail' => '<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><path d="M22 6l-10 7L2 6"/>',
    'phone' => '<path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.13.96.36 1.9.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0122 16.92z"/>',
    'cake' => '<path d="M20 21v-8a2 2 0 00-2-2H6a2 2 0 00-2 2v8M4 16s.5-1 2-1 2.5 2 4 2 2.5-2 4-2 2.5 2 4 2 2-1 2-1M2 21h20M7 8v3M12 8v3M17 8v3M7 4h.01M12 4h.01M17 4h.01"/>',
    'person' => '<path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/>',
    'heart' => '<path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/>',
    'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
    'map' => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/>',
    'home' => '<path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><path d="M9 22V12h6v10"/>',
    'people' => '<path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/>',
];
?><!doctype html>
<html lang="<?= esc($lang) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="csrf-token" content="<?= esc(csrf_token()) ?>">
  <title><?= esc(t('profile')) ?> - <?= esc($fullName) ?></title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;1,500;1,600&family=Parisienne&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="<?= admin_asset('/admin/css/admin.css') ?>">
  <link rel="stylesheet" href="<?= admin_asset('/admin/css/ui.css') ?>">
  <link rel="stylesheet" href="<?= admin_asset('/profile/css/profile.css') ?>">
  <script>
  window.OAC_UI_STRINGS = <?= json_encode($uiStrings, $jsonFlags) ?>;
  </script>
  <script src="<?= admin_asset('/admin/js/ui.js') ?>"></script>
</head>
<body class="admin-body prf-body" data-tab="profile">
  <?php require_once __DIR__ . '/../header_footer/header.php'; ?>

  <div class="admin-aura" aria-hidden="true"><div class="admin-rays"></div><div class="admin-halo"></div></div>

  <main class="admin-main prf-main">
    <section class="prf-hero" aria-labelledby="prfName">
      <div class="prf-avatar-ring">
        <?php if ($photo !== ''): ?>
        <img class="prf-avatar" src="<?= esc($photo) ?>" alt="" width="128" height="128" decoding="async" data-initials="<?= esc($initials) ?>">
        <?php else: ?>
        <span class="prf-avatar prf-avatar--initials" aria-hidden="true"><?= esc($initials) ?></span>
        <?php endif; ?>
      </div>
      <h1 class="admin-title prf-name" id="prfName"><span><?= esc($fullName) ?></span></h1>
      <?php if ($officeName !== ''): ?>
      <p class="admin-eyebrow prf-office">
        <svg class="admin-eyebrow-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/></svg>
        <span><?= esc($officeName) ?></span>
      </p>
      <?php endif; ?>
      <?php
        $place = implode(' · ', array_filter([(string)($user['congregation_name'] ?? ''), (string)($user['town_name'] ?? '')]));
        if ($place !== ''):
      ?>
      <p class="admin-sub prf-place"><?= esc($place) ?></p>
      <?php endif; ?>
    </section>

    <?php if ($hasActions): ?>
    <nav class="prf-actions" aria-label="<?= esc(t('actions')) ?>">
      <?php if ($isMe): ?>
      <a href="/admin/account.php" class="admin-btn admin-btn-primary">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20h9M16.5 3.5a2.12 2.12 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
        <span><?= esc(t('edit_profile')) ?></span>
      </a>
      <?php endif; ?>

      <?php if ($canViewCalendar): ?>
      <a href="<?= $isMe ? '/calendar/calendar.php' : '/calendar/view.php?u=' . $targetId ?>" class="admin-btn <?= $isMe ? 'admin-btn-secondary' : 'admin-btn-primary' ?>">
        <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M3 10h18M8 2v4M16 2v4"/></svg>
        <span><?= esc(t('calendar')) ?></span>
      </a>
      <?php endif; ?>

      <?php if ($canExportCalendar && !$isMe): ?>
      <a href="/profile/api/export_calendar.php?user_id=<?= $targetId ?>" class="admin-btn admin-btn-secondary" data-export>
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
        <span><?= esc(t('export')) ?></span>
      </a>
      <?php endif; ?>

      <?php if ($canEdit && $officeOptions): ?>
      <button type="button" class="admin-btn admin-btn-secondary" data-open-office aria-haspopup="dialog">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        <span><?= esc(t('prf_change_office')) ?></span>
      </button>
      <?php endif; ?>

      <?php if ($canDelete): ?>
      <button type="button" class="admin-btn admin-btn-danger" data-delete-member>
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
        <span><?= esc(t('delete')) ?></span>
      </button>
      <?php endif; ?>
    </nav>
    <?php endif; ?>

    <div class="prf-grid">
      <section class="prf-card" aria-labelledby="prfPersonal">
        <h2 class="admin-subtitle" id="prfPersonal"><?= esc(t('personal_information')) ?></h2>
        <div class="prf-rows">
          <?php
            prf_row($icons['mail'], t('adm_email'), (string)($user['email'] ?? ''), !empty($user['email']) ? 'mailto:' . $user['email'] : '');
            $phone = trim((string)($user['phone'] ?? ''));
            prf_row($icons['phone'], t('phone'), $phone, $phone !== '' ? 'tel:' . preg_replace('/[^0-9+]/', '', $phone) : '');
            prf_row($icons['cake'], t('birthdate'), $birthdate);
            prf_row($icons['person'], t('gender'), !empty($user['gender']) ? t(strtolower((string)$user['gender'])) : '');
            prf_row($icons['heart'], t('marital_status'), !empty($user['marital_status']) ? t(strtolower((string)$user['marital_status'])) : '');
          ?>
        </div>
      </section>

      <section class="prf-card" aria-labelledby="prfChurch">
        <h2 class="admin-subtitle" id="prfChurch"><?= esc(t('church_information')) ?></h2>
        <div class="prf-rows">
          <?php
            prf_row($icons['shield'], t('office'), $officeName, '', true);
            prf_row($icons['map'], t('province'), (string)($user['province_name'] ?? ''));
            prf_row($icons['home'], t('town'), (string)($user['town_name'] ?? ''));
            prf_row($icons['people'], t('congregation'), (string)($user['congregation_name'] ?? ''));
          ?>
        </div>
      </section>

      <?php if ($spouse):
        $spouseName = trim(($spouse['name'] ?? '') . ' ' . ($spouse['surname'] ?? ''));
        $spousePhoto = trim((string)($spouse['photo'] ?? ''));
        if ($spousePhoto !== '' && strpos($spousePhoto, 'http') !== 0 && strpos($spousePhoto, '/') !== 0) {
            $spousePhoto = '/assets/uploads/' . $spousePhoto;
        }
        $spousePhoto = admin_photo_url($spousePhoto);
      ?>
      <section class="prf-card" aria-labelledby="prfSpouse">
        <h2 class="admin-subtitle" id="prfSpouse"><?= esc(t('spouse')) ?></h2>
        <a href="/profile/?u=<?= (int)$spouse['id'] ?>" class="prf-spouse">
          <?php if ($spousePhoto !== ''): ?>
          <img class="prf-spouse-avatar" src="<?= esc($spousePhoto) ?>" alt="" width="52" height="52" loading="lazy" decoding="async" data-initials="<?= esc(admin_initials($spouseName)) ?>">
          <?php else: ?>
          <span class="prf-spouse-avatar prf-avatar--initials" aria-hidden="true"><?= esc(admin_initials($spouseName)) ?></span>
          <?php endif; ?>
          <span class="prf-spouse-text">
            <strong><?= esc($spouseName) ?></strong>
            <span><?= esc(t('view_profile')) ?></span>
          </span>
          <svg class="prf-spouse-chev" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg>
        </a>
      </section>
      <?php endif; ?>

      <?php if (!empty($user['about'])): ?>
      <section class="prf-card prf-card--wide" aria-labelledby="prfAbout">
        <h2 class="admin-subtitle" id="prfAbout"><?= esc(t('about')) ?></h2>
        <p class="prf-about"><?= nl2br(esc($user['about'])) ?></p>
      </section>
      <?php endif; ?>
    </div>
  </main>

  <?php if ($canEdit && $officeOptions): ?>
  <!-- Change the office (only the offices below your own are offered; the server checks again) -->
  <dialog class="prf-dialog" id="officeDialog" aria-labelledby="officeTitle">
    <form id="officeForm" class="admin-form" novalidate>
      <div class="prf-dialog-head">
        <h2 class="admin-section-title" id="officeTitle"><?= esc(t('prf_change_office')) ?></h2>
        <button type="button" class="prf-dialog-close" data-close-office aria-label="<?= esc(t('close')) ?>">&times;</button>
      </div>
      <p class="prf-dialog-who"><?= esc($fullName) ?></p>
      <input type="hidden" name="user_id" value="<?= $targetId ?>">
      <div class="admin-field">
        <label for="officeSelect" class="admin-label"><?= esc(t('office')) ?></label>
        <select id="officeSelect" name="amp_id" class="admin-select">
          <?php foreach ($officeOptions as $optId => $optName): ?>
          <option value="<?= $optId ?>"<?= $optId === $targetAmpId ? ' selected' : '' ?>><?= esc($optName) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="admin-right">
        <button type="button" class="admin-btn admin-btn-secondary" data-close-office><?= esc(t('cancel')) ?></button>
        <button type="submit" class="admin-btn admin-btn-primary"><?= esc(t('save')) ?></button>
      </div>
    </form>
  </dialog>
  <?php endif; ?>

  <script>
  window.OAC_PROFILE = <?= json_encode($profileData, $jsonFlags) ?>;
  </script>
  <script src="<?= admin_asset('/profile/js/profile.js') ?>"></script>

  <?php require_once __DIR__ . '/../header_footer/footer.php'; ?>
</body>
</html>
