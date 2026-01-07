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

$meId = (int)($_SESSION['user_id'] ?? 0);
$targetId = isset($_GET['u']) && is_numeric($_GET['u']) ? (int)$_GET['u'] : $meId;

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

if ($myAmpId >= 1 && $myAmpId <= 9) {
    $canExportCalendar = true;
}

$fullName = trim(($user['name'] ?? '') . ' ' . ($user['surname'] ?? ''));
$hasPhoto = !empty($user['photo']);
$avatar = $hasPhoto ? $user['photo'] : '/assets/img/avatar-default.png';

// Generate initials from name
$initials = '';
$nameParts = preg_split('/\s+/', $fullName);
if (count($nameParts) >= 2) {
    $initials = strtoupper(mb_substr($nameParts[0], 0, 1) . mb_substr($nameParts[count($nameParts) - 1], 0, 1));
} elseif (count($nameParts) === 1 && !empty($nameParts[0])) {
    $initials = strtoupper(mb_substr($nameParts[0], 0, 2));
}

// Get spouse if exists
$spouse = null;
if (!empty($user['spouse_user_id'])) {
    $spouseStmt = $pdo->prepare("SELECT id, name, surname, photo FROM users WHERE id = ? LIMIT 1");
    $spouseStmt->execute([$user['spouse_user_id']]);
    $spouse = $spouseStmt->fetch(PDO::FETCH_ASSOC);
}

$VER = time();
?><!doctype html>
<html lang="<?= $lang ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= esc(t('profile')) ?> - <?= esc($fullName) ?></title>
  
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
  
  <link rel="stylesheet" href="/profile/css/profile.css?v=<?= $VER ?>">
</head>
<body class="profile-body">
  <?php require_once __DIR__ . '/../header_footer/header.php'; ?>

  <main class="profile-main">
    <section class="profile-hero">
      <div class="profile-hero-glow"></div>
      <div class="profile-hero-content">
        <div class="profile-avatar-wrapper">
          <?php if ($hasPhoto): ?>
          <img src="<?= esc($avatar) ?>" alt="<?= esc($fullName) ?>" class="profile-avatar-large">
          <?php else: ?>
          <div class="profile-avatar-large profile-avatar-initials"><?= esc($initials) ?></div>
          <?php endif; ?>
        </div>
        <div class="profile-hero-info">
          <h1 class="profile-hero-name"><?= esc($fullName) ?></h1>
          <?php if (!empty($user['amp_id'])): ?>
          <p class="profile-hero-amp"><?= esc(get_translated_office((int)$user['amp_id'], $user['gender'] ?? 'man', $lang)) ?></p>
          <?php endif; ?>
        </div>
      </div>
    </section>

    <?php if ($canEdit || $canViewCalendar || $canExportCalendar || $canDelete): ?>
    <section class="profile-actions">
      <a href="/profile/?u=<?= $targetId ?>" class="profile-btn">
        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2M12 11a4 4 0 100-8 4 4 0 000 8z" stroke="currentColor" stroke-width="2"/>
        </svg>
        <span><?= esc(t('profile')) ?></span>
      </a>
      
      <?php if ($canViewCalendar): ?>
      <a href="/calendar/view.php?u=<?= $targetId ?>" class="profile-btn profile-btn-primary">
        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <rect x="3" y="4" width="18" height="18" rx="2" stroke="currentColor" stroke-width="2"/>
          <line x1="3" y1="10" x2="21" y2="10" stroke="currentColor" stroke-width="2"/>
        </svg>
        <span><?= esc(t('calendar')) ?></span>
      </a>
      <?php endif; ?>
      
      <?php if ($canExportCalendar): ?>
      <button type="button" class="profile-btn" onclick="exportCalendar(<?= $targetId ?>)">
        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3" stroke="currentColor" stroke-width="2"/>
        </svg>
        <span><?= esc(t('export')) ?></span>
      </button>
      <?php endif; ?>
      
      <?php if ($canEdit): ?>
      <button type="button" class="profile-btn" onclick="openEditModal(<?= $targetId ?>)">
        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M12 20h9M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z" stroke="currentColor" stroke-width="2"/>
        </svg>
        <span><?= esc(t('edit')) ?></span>
      </button>
      <?php endif; ?>
      
      <?php if ($canDelete): ?>
      <button type="button" class="profile-btn profile-btn-danger" onclick="deleteUser(<?= $targetId ?>, '<?= esc($fullName) ?>')">
        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2" stroke="currentColor" stroke-width="2"/>
        </svg>
        <span><?= esc(t('delete')) ?></span>
      </button>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <section class="profile-grid">
      <div class="profile-card">
        <h2 class="profile-card-title"><?= esc(t('personal_information')) ?></h2>
        <div class="profile-info-list">
          <?php if (!empty($user['email'])): ?>
          <div class="profile-info-item">
            <svg class="profile-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" stroke="currentColor" stroke-width="2"/>
              <path d="M22 6l-10 7L2 6" stroke="currentColor" stroke-width="2"/>
            </svg>
            <div class="profile-info-content">
              <span class="profile-info-label"><?= esc(t('email')) ?></span>
              <span class="profile-info-value"><?= esc($user['email']) ?></span>
            </div>
          </div>
          <?php endif; ?>
          
          <?php if (!empty($user['phone'])): ?>
          <div class="profile-info-item">
            <svg class="profile-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z" stroke="currentColor" stroke-width="2"/>
            </svg>
            <div class="profile-info-content">
              <span class="profile-info-label"><?= esc(t('phone')) ?></span>
              <span class="profile-info-value"><?= esc($user['phone']) ?></span>
            </div>
          </div>
          <?php endif; ?>
          
          <?php if (!empty($user['birthdate'])): ?>
          <div class="profile-info-item">
            <svg class="profile-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <rect x="3" y="4" width="18" height="18" rx="2" stroke="currentColor" stroke-width="2"/>
            </svg>
            <div class="profile-info-content">
              <span class="profile-info-label"><?= esc(t('birthdate')) ?></span>
              <span class="profile-info-value"><?= esc(date('d F Y', strtotime($user['birthdate']))) ?></span>
            </div>
          </div>
          <?php endif; ?>
          
          <?php if (!empty($user['gender'])): ?>
          <div class="profile-info-item">
            <svg class="profile-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
            </svg>
            <div class="profile-info-content">
              <span class="profile-info-label"><?= esc(t('gender')) ?></span>
              <span class="profile-info-value"><?= esc(t(strtolower($user['gender']))) ?></span>
            </div>
          </div>
          <?php endif; ?>

          <?php if (!empty($user['marital_status'])): ?>
          <div class="profile-info-item">
            <svg class="profile-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z" stroke="currentColor" stroke-width="2"/>
            </svg>
            <div class="profile-info-content">
              <span class="profile-info-label"><?= esc(t('marital_status')) ?></span>
              <span class="profile-info-value"><?= esc(t(strtolower($user['marital_status']))) ?></span>
            </div>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="profile-card">
        <h2 class="profile-card-title"><?= esc(t('church_information')) ?></h2>
        <div class="profile-info-list">
          <?php if (!empty($user['amp_id'])): ?>
          <div class="profile-info-item">
            <svg class="profile-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M12 2L2 7l10 5 10-5-10-5z" stroke="currentColor" stroke-width="2"/>
            </svg>
            <div class="profile-info-content">
              <span class="profile-info-label"><?= esc(t('office')) ?></span>
              <span class="profile-info-value profile-info-highlight"><?= esc(get_translated_office((int)$user['amp_id'], $user['gender'] ?? 'man', $lang)) ?></span>
            </div>
          </div>
          <?php endif; ?>
          
          <?php if (!empty($user['province_name'])): ?>
          <div class="profile-info-item">
            <svg class="profile-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z" stroke="currentColor" stroke-width="2"/>
            </svg>
            <div class="profile-info-content">
              <span class="profile-info-label"><?= esc(t('province')) ?></span>
              <span class="profile-info-value"><?= esc($user['province_name']) ?></span>
            </div>
          </div>
          <?php endif; ?>
          
          <?php if (!empty($user['town_name'])): ?>
          <div class="profile-info-item">
            <svg class="profile-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z" stroke="currentColor" stroke-width="2"/>
            </svg>
            <div class="profile-info-content">
              <span class="profile-info-label"><?= esc(t('town')) ?></span>
              <span class="profile-info-value"><?= esc($user['town_name']) ?></span>
            </div>
          </div>
          <?php endif; ?>
          
          <?php if (!empty($user['congregation_name'])): ?>
          <div class="profile-info-item">
            <svg class="profile-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8zM23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75" stroke="currentColor" stroke-width="2"/>
            </svg>
            <div class="profile-info-content">
              <span class="profile-info-label"><?= esc(t('congregation')) ?></span>
              <span class="profile-info-value"><?= esc($user['congregation_name']) ?></span>
            </div>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($spouse):
        $spouseHasPhoto = !empty($spouse['photo']);
        $spouseFullName = trim(($spouse['name'] ?? '') . ' ' . ($spouse['surname'] ?? ''));
        $spouseNameParts = preg_split('/\s+/', $spouseFullName);
        $spouseInitials = '';
        if (count($spouseNameParts) >= 2) {
            $spouseInitials = strtoupper(mb_substr($spouseNameParts[0], 0, 1) . mb_substr($spouseNameParts[count($spouseNameParts) - 1], 0, 1));
        } elseif (count($spouseNameParts) === 1 && !empty($spouseNameParts[0])) {
            $spouseInitials = strtoupper(mb_substr($spouseNameParts[0], 0, 2));
        }
      ?>
      <div class="profile-card">
        <h2 class="profile-card-title"><?= esc(t('spouse')) ?></h2>
        <div class="profile-spouse-card">
          <a href="/profile/?u=<?= (int)$spouse['id'] ?>" class="profile-spouse-link">
            <?php if ($spouseHasPhoto): ?>
            <img src="<?= esc($spouse['photo']) ?>" alt="<?= esc($spouse['name']) ?>" class="profile-spouse-avatar">
            <?php else: ?>
            <div class="profile-spouse-avatar profile-avatar-initials profile-avatar-initials-sm"><?= esc($spouseInitials) ?></div>
            <?php endif; ?>
            <div class="profile-spouse-info">
              <p class="profile-spouse-name"><?= esc($spouseFullName) ?></p>
              <p class="profile-spouse-label"><?= esc(t('view_profile')) ?> →</p>
            </div>
          </a>
        </div>
      </div>
      <?php endif; ?>

      <?php if (!empty($user['about'])): ?>
      <div class="profile-card profile-card-full">
        <h2 class="profile-card-title"><?= esc(t('about')) ?></h2>
        <div class="profile-about">
          <?= nl2br(esc($user['about'])) ?>
        </div>
      </div>
      <?php endif; ?>
    </section>
  </main>

  <div id="editModal" class="profile-modal" style="display:none;">
    <div class="profile-modal-overlay" onclick="closeEditModal()"></div>
    <div class="profile-modal-content">
      <div class="profile-modal-header">
        <h3 class="profile-modal-title"><?= esc(t('edit_user')) ?></h3>
        <button type="button" class="profile-modal-close" onclick="closeEditModal()">&times;</button>
      </div>
      <div class="profile-modal-body">
        <form id="editForm">
          <input type="hidden" id="editUserId" name="user_id">
          
          <div class="profile-form-group">
            <label for="editAmpId"><?= esc(t('office')) ?></label>
            <select id="editAmpId" name="amp_id" class="profile-form-select">
              <?php
              $ampStmt = $pdo->query("SELECT id, male_name, female_name FROM amptes ORDER BY id");
              while ($amp = $ampStmt->fetch(PDO::FETCH_ASSOC)):
              ?>
              <option value="<?= (int)$amp['id'] ?>"><?= esc($amp['male_name']) ?> / <?= esc($amp['female_name']) ?></option>
              <?php endwhile; ?>
            </select>
          </div>
          
          <div class="profile-modal-actions">
            <button type="button" class="profile-btn" onclick="closeEditModal()"><?= esc(t('cancel')) ?></button>
            <button type="submit" class="profile-btn profile-btn-primary"><?= esc(t('save')) ?></button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="/profile/js/profile.js?v=<?= $VER ?>"></script>

  <?php require_once __DIR__ . '/../header_footer/footer.php'; ?>
</body>
</html>