<?php
declare(strict_types=1);
require_once __DIR__ . '/../security/auth_gate.php';
require_once __DIR__ . '/../includes/languages.php';
require_once __DIR__ . '/api/notifications/helper.php';

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

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    header('Location: /login.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$userId]);
$currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$currentUser) {
    header('Location: /logout.php');
    exit;
}

$notice = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $photoSaved = false;

        // Handle cropped photo data (base64 from cropper)
        if (!empty($_POST['cropped_photo_data']) && strpos($_POST['cropped_photo_data'], 'data:image/') === 0) {
            $uploadDir = __DIR__ . '/../assets/uploads/' . $userId . '/profile';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0775, true);
            }

            // Clear old files
            foreach (glob($uploadDir . '/*') as $f) {
                if (is_file($f)) @unlink($f);
            }

            // Decode base64 image
            $base64Data = $_POST['cropped_photo_data'];
            $base64Data = preg_replace('#^data:image/\w+;base64,#i', '', $base64Data);
            $imageData = base64_decode($base64Data);

            if ($imageData !== false && function_exists('imagecreatefromstring')) {
                $src = @imagecreatefromstring($imageData);
                if ($src) {
                    $webpTarget = $uploadDir . '/profile_' . $userId . '.webp';
                    $jpgTarget = $uploadDir . '/profile_' . $userId . '.jpg';

                    // Try to save as WebP first
                    if (function_exists('imagewebp')) {
                        $photoSaved = @imagewebp($src, $webpTarget, 82);
                        if ($photoSaved && is_file($jpgTarget)) @unlink($jpgTarget);
                    }

                    // Fallback to JPEG
                    if (!$photoSaved && function_exists('imagejpeg')) {
                        $photoSaved = @imagejpeg($src, $jpgTarget, 85);
                        if ($photoSaved && is_file($webpTarget)) @unlink($webpTarget);
                    }

                    @imagedestroy($src);
                }
            }

            if ($photoSaved) {
                $webpPath = '/assets/uploads/' . $userId . '/profile/profile_' . $userId . '.webp';
                $jpgPath = '/assets/uploads/' . $userId . '/profile/profile_' . $userId . '.jpg';

                $relative = null;
                if (file_exists(__DIR__ . '/..' . $webpPath)) {
                    $relative = $webpPath;
                } elseif (file_exists(__DIR__ . '/..' . $jpgPath)) {
                    $relative = $jpgPath;
                }

                if ($relative) {
                    $pdo->prepare('UPDATE users SET photo = ? WHERE id = ?')->execute([$relative, $userId]);
                    $currentUser['photo'] = $relative;
                }
            }
        }
        // Fallback: handle regular file upload (without cropper)
        elseif (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../assets/uploads/' . $userId . '/profile';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0775, true);
            }

            foreach (glob($uploadDir . '/*') as $f) {
                if (is_file($f)) @unlink($f);
            }

            $tmpFile = $_FILES['photo']['tmp_name'];
            $blob = @file_get_contents($tmpFile);
            $saved = false;

            if ($blob !== false && function_exists('imagecreatefromstring')) {
                $src = @imagecreatefromstring($blob);
                if ($src) {
                    $w = @imagesx($src);
                    $h = @imagesy($src);

                    if ($w && $h) {
                        $size = min($w, $h);
                        $xOff = (int)(($w - $size) / 2);
                        $yOff = (int)(($h - $size) / 2);

                        $canvas = @imagecreatetruecolor(600, 600);
                        if ($canvas) {
                            @imagealphablending($canvas, false);
                            @imagesavealpha($canvas, true);
                            @imagecopyresampled($canvas, $src, 0, 0, $xOff, $yOff, 600, 600, $size, $size);

                            $webpTarget = $uploadDir . '/profile_' . $userId . '.webp';
                            $jpgTarget = $uploadDir . '/profile_' . $userId . '.jpg';

                            if (function_exists('imagewebp')) {
                                $saved = @imagewebp($canvas, $webpTarget, 82);
                                if ($saved && is_file($jpgTarget)) @unlink($jpgTarget);
                            }

                            if (!$saved && function_exists('imagejpeg')) {
                                $saved = @imagejpeg($canvas, $jpgTarget, 85);
                                if ($saved && is_file($webpTarget)) @unlink($webpTarget);
                            }

                            @imagedestroy($canvas);
                        }
                    }
                    @imagedestroy($src);
                }
            }

            if ($saved) {
                $relative = null;
                $webpPath = '/assets/uploads/' . $userId . '/profile/profile_' . $userId . '.webp';
                $jpgPath = '/assets/uploads/' . $userId . '/profile/profile_' . $userId . '.jpg';

                if (file_exists(__DIR__ . '/..' . $webpPath)) {
                    $relative = $webpPath;
                } elseif (file_exists(__DIR__ . '/..' . $jpgPath)) {
                    $relative = $jpgPath;
                }

                if ($relative) {
                    $pdo->prepare('UPDATE users SET photo = ? WHERE id = ?')->execute([$relative, $userId]);
                    $currentUser['photo'] = $relative;
                }
            }
        }
        
        $newSpouseId = isset($_POST['spouse_user_id']) && is_numeric($_POST['spouse_user_id']) ? (int)$_POST['spouse_user_id'] : null;
        $currentSpouseId = isset($currentUser['spouse_user_id']) ? (int)$currentUser['spouse_user_id'] : 0;

        if ($newSpouseId && $newSpouseId !== $userId && $newSpouseId !== $currentSpouseId && !$currentSpouseId) {
            // One pending request at a time, in any direction, with anyone -
            // otherwise the same person can propose to several people at once
            $checkStmt = $pdo->prepare("SELECT id FROM spouse_requests WHERE
                (requester_id = ? OR receiver_id = ? OR requester_id = ? OR receiver_id = ?)
                AND status = 'pending' LIMIT 1");
            $checkStmt->execute([$userId, $userId, $newSpouseId, $newSpouseId]);

            if ($checkStmt->fetch()) {
                $notice = t('spouse_request_already_pending');
            } else {
                // The chosen person must exist and still be unmarried
                $recStmt = $pdo->prepare('SELECT id FROM users WHERE id = ? AND spouse_user_id IS NULL LIMIT 1');
                $recStmt->execute([$newSpouseId]);

                if ($recStmt->fetch()) {
                    $reqStmt = $pdo->prepare("INSERT INTO spouse_requests (requester_id, receiver_id, status) VALUES (?, ?, 'pending')");
                    $reqStmt->execute([$userId, $newSpouseId]);

                    createAdminNotification($newSpouseId, 'spouse_request', [
                        'from_name' => trim($currentUser['name'] . ' ' . $currentUser['surname'])
                    ]);

                    $notice = t('spouse_request_sent');
                }
            }
        }
        
        $name = trim($_POST['name'] ?? '');
        $surname = trim($_POST['surname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $language = in_array($_POST['language'] ?? 'af', SUPPORTED_LANGS, true) ? $_POST['language'] : 'af';
        $birthdate = trim($_POST['birthdate'] ?? '');
        $marital_status = $_POST['marital_status'] ?? null;
        if (!in_array($marital_status, ['getroud', 'ongetroud', 'wewenaar', 'weduwee'], true)) {
            $marital_status = null;
        }
        $province_id = isset($_POST['province']) && is_numeric($_POST['province']) ? (int)$_POST['province'] : null;
        $town_id = isset($_POST['town']) && is_numeric($_POST['town']) ? (int)$_POST['town'] : null;
        $congregation_id = isset($_POST['congregation']) && is_numeric($_POST['congregation']) ? (int)$_POST['congregation'] : null;
        $about = trim($_POST['about'] ?? '');
        
        $town_name = null;
        if ($town_id) {
            $stmtTown = $pdo->prepare('SELECT name FROM towns WHERE id = ? LIMIT 1');
            $stmtTown->execute([$town_id]);
            $tmpName = $stmtTown->fetchColumn();
            if ($tmpName !== false) $town_name = (string)$tmpName;
        }
        
        $cols = $pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN, 0);
        $setParts = [];
        $vals = [];
        
        $setParts[] = 'name = ?'; $vals[] = $name;
        $setParts[] = 'surname = ?'; $vals[] = $surname;
        $setParts[] = 'email = ?'; $vals[] = $email;
        $setParts[] = 'phone = ?'; $vals[] = $phone;
        $setParts[] = 'language = ?'; $vals[] = $language;
        $setParts[] = 'birthdate = ?'; $vals[] = ($birthdate ?: null);
        
        if (in_array('marital_status', $cols, true)) {
            $setParts[] = 'marital_status = ?';
            $vals[] = ($marital_status ?: null);
        }
        
        if (in_array('province_id', $cols, true)) {
            $setParts[] = 'province_id = ?';
            $vals[] = ($province_id ?: null);
        }
        
        if (in_array('town_id', $cols, true)) {
            $setParts[] = 'town_id = ?';
            $vals[] = ($town_id ?: null);
        }
        
        if (in_array('congregation_id', $cols, true)) {
            $setParts[] = 'congregation_id = ?';
            $vals[] = ($congregation_id ?: null);
        }
        
        if (in_array('town', $cols, true)) {
            $setParts[] = 'town = ?';
            $vals[] = ($town_name ?: null);
        }
        
        if (in_array('about', $cols, true)) {
            $setParts[] = 'about = ?';
            $vals[] = ($about !== '' ? $about : null);
        }
        
        if (in_array('updated_at', $cols, true)) {
            $setParts[] = 'updated_at = CURRENT_TIMESTAMP';
        }
        
        $sql = 'UPDATE users SET ' . implode(', ', $setParts) . ' WHERE id = ?';
        $vals[] = $userId;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($vals);

        // If town changed, remove old room memberships (chosen rooms from old town)
        $oldTownId = (int)($currentUser['town_id'] ?? 0);
        $newTownId = $town_id ?? 0;

        if ($oldTownId > 0 && $newTownId > 0 && $oldTownId !== $newTownId) {
            // Delete memberships for rooms that belong to the OLD town
            // This includes: opsienerskap, sondagskool, jeug rooms from the old town
            $deleteStmt = $pdo->prepare("
                DELETE rm FROM room_memberships rm
                INNER JOIN rooms r ON rm.room_id = r.id
                WHERE rm.user_id = ?
                AND r.town_id = ?
                AND r.type IN ('opsienerskap', 'sondagskool', 'jeug')
            ");
            $deleteStmt->execute([$userId, $oldTownId]);

            error_log("User {$userId} moved from town {$oldTownId} to {$newTownId} - removed old room memberships");
        }

        // If congregation changed, also remove gemeente room memberships from old congregation
        $oldCongId = (int)($currentUser['congregation_id'] ?? 0);
        $newCongId = $congregation_id ?? 0;

        if ($oldCongId > 0 && $newCongId > 0 && $oldCongId !== $newCongId) {
            // Delete memberships for gemeente rooms from old congregation
            $deleteStmt = $pdo->prepare("
                DELETE rm FROM room_memberships rm
                INNER JOIN rooms r ON rm.room_id = r.id
                WHERE rm.user_id = ?
                AND r.gemeente_id = ?
                AND r.type = 'gemeente'
            ");
            $deleteStmt->execute([$userId, $oldCongId]);

            error_log("User {$userId} moved from congregation {$oldCongId} to {$newCongId} - removed old gemeente room memberships");
        }

        $_SESSION['language'] = $language;
        if (!$notice) {
            $notice = t('profile_updated');
        }
        
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Throwable $e) {
        $error = t('error') . ': ' . $e->getMessage();
    }
}

$provinces = [];
try {
    $provinces = $pdo->query('SELECT id, name FROM provinces ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $provinces = [];
}

$townRows = [];
try {
    $townRows = $pdo->query('SELECT id, name, province_id FROM towns ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $townRows = [];
}

$townsByProvince = [];
foreach ($townRows as $row) {
    $pid = (string)$row['province_id'];
    $townsByProvince[$pid][] = ['id' => (int)$row['id'], 'name' => $row['name']];
}

$congRows = [];
try {
    $congRows = $pdo->query('SELECT id, name, town_id FROM congregations ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $congRows = [];
}

$congsByTown = [];
foreach ($congRows as $row) {
    $tid = (string)$row['town_id'];
    $congsByTown[$tid][] = ['id' => (int)$row['id'], 'name' => $row['name']];
}

$spouseOptions = [];
try {
    $oppGender = (strtolower($currentUser['gender'] ?? '') === 'man') ? 'vrou' : 'man';
    $congId = $currentUser['congregation_id'] ?? null;
    
    if ($congId && empty($currentUser['spouse_user_id'])) {
        $spouseStmt = $pdo->prepare("SELECT id, name, surname FROM users 
            WHERE congregation_id = ? 
            AND gender = ? 
            AND status = 'approved' 
            AND id != ?
            AND spouse_user_id IS NULL
            ORDER BY surname, name");
        $spouseStmt->execute([$congId, $oppGender, $userId]);
        $spouseOptions = $spouseStmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    $spouseOptions = [];
}

$pendingSpouseRequest = null;
try {
    $pendingStmt = $pdo->prepare("SELECT sr.*, u.name, u.surname FROM spouse_requests sr
        JOIN users u ON u.id = CASE WHEN sr.requester_id = ? THEN sr.receiver_id ELSE sr.requester_id END
        WHERE (sr.requester_id = ? OR sr.receiver_id = ?)
        AND sr.status = 'pending'
        LIMIT 1");
    $pendingStmt->execute([$userId, $userId, $userId]);
    $pendingSpouseRequest = $pendingStmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $pendingSpouseRequest = null;
}

$VER = time();
?><!doctype html>
<html lang="<?= $lang ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= t('edit_profile') ?></title>
  
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
  
  <link rel="stylesheet" href="/admin/css/account.css?v=<?= $VER ?>">
  <link rel="stylesheet" href="/admin/css/ui.css?v=<?= $VER ?>">
</head>
<body class="account-body">
  <?php require_once __DIR__ . '/../header_footer/header.php'; ?>

  <div class="account-hero">
    <div class="account-hero-glow"></div>
    <div class="account-hero-content">
      <h1 class="account-hero-title"><?= t('edit_profile') ?></h1>
      <p class="account-hero-subtitle"><?= t('update_personal_info') ?></p>
    </div>
  </div>

  <main class="account-main">
    <div class="account-container">
      <?php if ($notice): ?>
        <div class="account-notice account-notice-success"><?= htmlspecialchars($notice) ?></div>
      <?php endif; ?>
      
      <?php if ($error): ?>
        <div class="account-notice account-notice-error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php if ($pendingSpouseRequest): ?>
        <div class="account-notice account-notice-info">
          <?php if ($pendingSpouseRequest['requester_id'] == $userId): ?>
            <?= t('you_sent_spouse_request') ?>
            <strong><?= htmlspecialchars($pendingSpouseRequest['name'] . ' ' . $pendingSpouseRequest['surname']) ?></strong>.
            <?= t('waiting_approval') ?>
            <div class="spouse-actions">
              <button type="button" class="account-btn account-btn-secondary" onclick="handleSpouseRequest(<?= (int)$pendingSpouseRequest['id'] ?>, 'cancel')">
                <?= t('cancel_request') ?>
              </button>
            </div>
          <?php else: ?>
            <strong><?= htmlspecialchars($pendingSpouseRequest['name'] . ' ' . $pendingSpouseRequest['surname']) ?></strong>
            <?= t('wants_to_marry_you') ?>
            <div class="spouse-actions">
              <button class="account-btn account-btn-primary" onclick="handleSpouseRequest(<?= $pendingSpouseRequest['id'] ?>, 'accept')">
                <?= t('accept') ?>
              </button>
              <button class="account-btn account-btn-secondary" onclick="handleSpouseRequest(<?= $pendingSpouseRequest['id'] ?>, 'reject')">
                <?= t('reject') ?>
              </button>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <form method="post" enctype="multipart/form-data" class="account-form">
        <div class="account-photo-section">
          <div class="account-avatar-wrapper">
            <?php
            $avatar = !empty($currentUser['photo']) ? htmlspecialchars($currentUser['photo']) : '/assets/img/avatar-default.png';
            ?>
            <img id="avatar-preview" src="<?= $avatar ?>" alt="Profile" class="account-avatar">
            <div class="account-avatar-overlay">
              <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 5v14m-7-7h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
              </svg>
            </div>
          </div>
          <div class="account-field">
            <label for="photo" class="account-label"><?= t('profile_photo') ?></label>
            <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp" class="account-input-file">
            <small class="account-hint"><?= t('photo_hint') ?></small>
          </div>
        </div>

        <div class="account-form-grid">
          <div class="account-field">
            <label for="name" class="account-label"><?= t('name') ?> *</label>
            <input type="text" id="name" name="name" value="<?= htmlspecialchars($currentUser['name'] ?? '') ?>" required class="account-input">
          </div>
          <div class="account-field">
            <label for="surname" class="account-label"><?= t('surname') ?> *</label>
            <input type="text" id="surname" name="surname" value="<?= htmlspecialchars($currentUser['surname'] ?? '') ?>" required class="account-input">
          </div>
        </div>

        <div class="account-form-grid">
          <div class="account-field">
            <label for="email" class="account-label">Email *</label>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($currentUser['email'] ?? '') ?>" required class="account-input">
          </div>
          <div class="account-field">
            <label for="phone" class="account-label"><?= t('phone') ?></label>
            <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($currentUser['phone'] ?? '') ?>" class="account-input">
          </div>
        </div>

        <div class="account-form-grid">
          <div class="account-field">
            <label for="language" class="account-label"><?= t('language') ?></label>
            <select id="language" name="language" class="account-select">
              <?php foreach (SUPPORTED_LANGS as $lc): ?>
                <option value="<?= $lc ?>" <?= ($currentUser['language'] ?? 'af') === $lc ? 'selected' : '' ?>><?= LANG_NAMES[$lc] ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="account-field">
            <label for="birthdate" class="account-label"><?= t('birthdate') ?></label>
            <input type="date" id="birthdate" name="birthdate" value="<?= htmlspecialchars($currentUser['birthdate'] ?? '') ?>" class="account-input">
          </div>
        </div>

        <div class="account-form-grid">
          <div class="account-field">
            <label for="marital_status" class="account-label"><?= t('marital_status') ?></label>
            <select id="marital_status" name="marital_status" class="account-select">
              <option value=""><?= t('select') ?></option>
              <option value="getroud" <?= ($currentUser['marital_status'] ?? '') === 'getroud' ? 'selected' : '' ?>><?= t('married') ?></option>
              <option value="ongetroud" <?= ($currentUser['marital_status'] ?? '') === 'ongetroud' ? 'selected' : '' ?>><?= t('unmarried') ?></option>
              <?php $widowValue = (strtolower($currentUser['gender'] ?? '') === 'vrou') ? 'weduwee' : 'wewenaar'; ?>
              <option value="<?= $widowValue ?>" <?= in_array($currentUser['marital_status'] ?? '', ['weduwee', 'wewenaar'], true) ? 'selected' : '' ?>><?= t($widowValue) ?></option>
            </select>
          </div>
          <div class="account-field">
            <label for="province" class="account-label"><?= t('province') ?></label>
            <select id="province" name="province" class="account-select">
              <option value=""><?= t('select') ?></option>
              <?php foreach ($provinces as $prov): ?>
                <option value="<?= (int)$prov['id'] ?>" <?= ((int)($currentUser['province_id'] ?? 0) === (int)$prov['id']) ? 'selected' : '' ?>><?= htmlspecialchars($prov['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="account-form-grid">
          <div class="account-field">
            <label for="town" class="account-label"><?= t('town_city') ?></label>
            <select id="town" name="town" <?= empty($currentUser['province_id']) ? 'disabled' : '' ?> class="account-select">
              <?php
              $pidSel = (string)($currentUser['province_id'] ?? '');
              $tidSelInt = (int)($currentUser['town_id'] ?? 0);
              if (!$pidSel) {
                echo '<option value="">'.t('select_province_first').'</option>';
              } else {
                echo '<option value="">'.t('select').'</option>';
                if (isset($townsByProvince[$pidSel])) {
                  foreach ($townsByProvince[$pidSel] as $t) {
                    $sel = ($tidSelInt === (int)$t['id']) ? 'selected' : '';
                    echo '<option value="'.(int)$t['id'].'" '.$sel.'>'.htmlspecialchars($t['name']).'</option>';
                  }
                }
              }
              ?>
            </select>
          </div>
          <div class="account-field">
            <label for="congregation" class="account-label"><?= t('congregation') ?></label>
            <select id="congregation" name="congregation" <?= empty($currentUser['town_id']) ? 'disabled' : '' ?> class="account-select">
              <?php
              $tidSel = (string)($currentUser['town_id'] ?? '');
              $cidSelInt = (int)($currentUser['congregation_id'] ?? 0);
              if (!$tidSel) {
                echo '<option value="">'.t('select_town_first').'</option>';
              } else {
                echo '<option value="">'.t('select').'</option>';
                if (isset($congsByTown[$tidSel])) {
                  foreach ($congsByTown[$tidSel] as $c) {
                    $sel = ($cidSelInt === (int)$c['id']) ? 'selected' : '';
                    echo '<option value="'.(int)$c['id'].'" '.$sel.'>'.htmlspecialchars($c['name']).'</option>';
                  }
                }
              }
              ?>
            </select>
          </div>
        </div>

        <?php if (!empty($spouseOptions) && empty($currentUser['spouse_user_id']) && !$pendingSpouseRequest): ?>
        <div class="account-field">
          <label for="spouse_user_id" class="account-label"><?= t('spouse') ?></label>
          <select id="spouse_user_id" name="spouse_user_id" class="account-select">
            <option value=""><?= t('select') ?>...</option>
            <?php foreach ($spouseOptions as $spouse): ?>
              <option value="<?= (int)$spouse['id'] ?>"><?= htmlspecialchars($spouse['name'] . ' ' . $spouse['surname']) ?></option>
            <?php endforeach; ?>
          </select>
          <small class="account-hint"><?= t('spouse_hint') ?></small>
        </div>
        <?php elseif (!empty($currentUser['spouse_user_id'])): ?>
        <?php
          $spouseStmt = $pdo->prepare("SELECT name, surname FROM users WHERE id = ? LIMIT 1");
          $spouseStmt->execute([$currentUser['spouse_user_id']]);
          $spouseData = $spouseStmt->fetch(PDO::FETCH_ASSOC);
        ?>
        <div class="account-notice account-notice-success">
          <?= t('you_are_linked_to') ?>
          <strong><?= htmlspecialchars(($spouseData['name'] ?? '') . ' ' . ($spouseData['surname'] ?? '')) ?></strong>
        </div>
        <?php endif; ?>

        <div class="account-field">
          <label for="about" class="account-label"><?= t('about') ?></label>
          <textarea id="about" name="about" rows="4" placeholder="<?= t('about_placeholder') ?>" class="account-textarea"><?= htmlspecialchars($currentUser['about'] ?? '') ?></textarea>
        </div>

        <div class="account-actions">
          <a href="/admin/index.php" class="account-btn account-btn-secondary"><?= t('cancel') ?></a>
          <button type="submit" class="account-btn account-btn-primary"><?= t('save') ?></button>
        </div>
      </form>
    </div>
  </main>

  <script>
  window.OAC_UI_STRINGS = {
    ok: <?= json_encode(t('confirm')) ?>,
    cancel: <?= json_encode(t('cancel')) ?>
  };
  </script>
  <script src="/admin/js/ui.js?v=<?= $VER ?>"></script>
  <script src="/admin/js/account.js?v=<?= $VER ?>"></script>
  <script>
  const townsByProvince = <?= json_encode($townsByProvince, JSON_UNESCAPED_UNICODE) ?>;
  const congsByTown = <?= json_encode($congsByTown, JSON_UNESCAPED_UNICODE) ?>;
  const txtSelect = <?= json_encode(t('select')) ?>;
  const txtProvFirst = <?= json_encode(t('select_province_first')) ?>;
  const txtTownFirst = <?= json_encode(t('select_town_first')) ?>;

  const provSel = document.getElementById('province');
  const townSel = document.getElementById('town');
  const congSel = document.getElementById('congregation');

  function updateTowns() {
    const pid = provSel.value;
    townSel.innerHTML = '';
    let opt = document.createElement('option');
    
    if (!pid) {
      opt.value = '';
      opt.textContent = txtProvFirst;
      townSel.appendChild(opt);
      townSel.disabled = true;
    } else {
      opt.value = '';
      opt.textContent = txtSelect;
      townSel.appendChild(opt);
      
      if (townsByProvince[pid]) {
        townsByProvince[pid].forEach(function(row) {
          const o = document.createElement('option');
          o.value = row.id;
          o.textContent = row.name;
          townSel.appendChild(o);
        });
      }
      townSel.disabled = false;
    }
    
    congSel.innerHTML = '';
    let copt = document.createElement('option');
    copt.value = '';
    copt.textContent = txtTownFirst;
    congSel.appendChild(copt);
    congSel.disabled = true;
  }

  function updateCongs() {
    const tid = townSel.value;
    congSel.innerHTML = '';
    let opt = document.createElement('option');
    
    if (!tid) {
      opt.value = '';
      opt.textContent = txtTownFirst;
      congSel.appendChild(opt);
      congSel.disabled = true;
    } else {
      opt.value = '';
      opt.textContent = txtSelect;
      congSel.appendChild(opt);
      
      if (congsByTown[tid]) {
        congsByTown[tid].forEach(function(row) {
          const o = document.createElement('option');
          o.value = row.id;
          o.textContent = row.name;
          congSel.appendChild(o);
        });
      }
      congSel.disabled = false;
    }
  }

  provSel.addEventListener('change', updateTowns);
  townSel.addEventListener('change', updateCongs);

  async function handleSpouseRequest(requestId, action) {
    try {
      const response = await fetch('/admin/api/spouse/' + action + '.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({request_id: requestId})
      });

      const data = await response.json();
      if (data.success) {
        location.reload();
      } else {
        OACUI.toast(data.error || 'Error', 'error');
      }
    } catch (e) {
      OACUI.toast('Network error: ' + e.message, 'error');
    }
  }
  </script>

  <?php require_once __DIR__ . '/../header_footer/footer.php'; ?>
</body>
</html>