<?php
declare(strict_types=1);
require_once __DIR__ . '/../security/auth_gate.php';
require_once __DIR__ . '/../includes/languages.php';

$lang = $_SESSION['language'] ?? 'af';
if (isset($_GET['lang']) && in_array($_GET['lang'], SUPPORTED_LANGS, true)) {
    $lang = $_GET['lang'];
    $_SESSION['language'] = $lang;
}

// T() for backwards compat: AF gets Afrikaans, all others get English
function T($af, $en) {
    global $lang;
    return $lang === 'af' ? $af : $en;
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
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
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
        $currentSpouseId = $currentUser['spouse_user_id'] ?? null;
        
        if ($newSpouseId && $newSpouseId !== $currentSpouseId && empty($currentSpouseId)) {
            $checkStmt = $pdo->prepare("SELECT id FROM spouse_requests WHERE 
                ((requester_id = ? AND receiver_id = ?) OR 
                 (requester_id = ? AND receiver_id = ?))
                AND status = 'pending' LIMIT 1");
            $checkStmt->execute([$userId, $newSpouseId, $newSpouseId, $userId]);
            
            if (!$checkStmt->fetch()) {
                $reqStmt = $pdo->prepare("INSERT INTO spouse_requests (requester_id, receiver_id, status) VALUES (?, ?, 'pending')");
                $reqStmt->execute([$userId, $newSpouseId]);
                
                $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, type, payload) VALUES (?, 'spouse_request', ?)");
                $notifStmt->execute([$newSpouseId, json_encode([
                    'from_id' => $userId,
                    'from_name' => trim($currentUser['name'] . ' ' . $currentUser['surname'])
                ])]);
                
                $notice = T('Eggenoot versoek gestuur. Wag vir goedkeuring.', 'Spouse request sent. Waiting for approval.');
            }
        }
        
        $name = trim($_POST['name'] ?? '');
        $surname = trim($_POST['surname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $language = in_array($_POST['language'] ?? 'af', ['af','en'], true) ? $_POST['language'] : 'af';
        $birthdate = trim($_POST['birthdate'] ?? '');
        $marital_status = $_POST['marital_status'] ?? null;
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
        
        $_SESSION['language'] = $language;
        if (!$notice) {
            $notice = T('Profiel suksesvol opgedateer', 'Profile updated successfully');
        }
        
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Throwable $e) {
        $error = T('Fout: ', 'Error: ') . $e->getMessage();
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
  <title><?= T('Wysig Profiel', 'Edit Profile') ?></title>
  
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
</head>
<body class="account-body">
  <?php require_once __DIR__ . '/../header_footer/header.php'; ?>

  <div class="account-hero">
    <div class="account-hero-glow"></div>
    <div class="account-hero-content">
      <h1 class="account-hero-title"><?= T('Wysig Profiel', 'Edit Profile') ?></h1>
      <p class="account-hero-subtitle"><?= T('Werk jou persoonlike inligting by', 'Update your personal information') ?></p>
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
            <?= T('Jy het \'n eggenoot versoek gestuur na ', 'You sent a spouse request to ') ?>
            <strong><?= htmlspecialchars($pendingSpouseRequest['name'] . ' ' . $pendingSpouseRequest['surname']) ?></strong>.
            <?= T('Wag vir goedkeuring.', 'Waiting for approval.') ?>
          <?php else: ?>
            <strong><?= htmlspecialchars($pendingSpouseRequest['name'] . ' ' . $pendingSpouseRequest['surname']) ?></strong>
            <?= T(' wil met jou trou.', ' wants to marry you.') ?>
            <div class="spouse-actions">
              <button class="account-btn account-btn-primary" onclick="handleSpouseRequest(<?= $pendingSpouseRequest['id'] ?>, 'accept')">
                <?= T('Aanvaar', 'Accept') ?>
              </button>
              <button class="account-btn account-btn-secondary" onclick="handleSpouseRequest(<?= $pendingSpouseRequest['id'] ?>, 'reject')">
                <?= T('Verwerp', 'Reject') ?>
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
            <label for="photo" class="account-label"><?= T('Profiel Foto', 'Profile Photo') ?></label>
            <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp" class="account-input-file">
            <small class="account-hint"><?= T('Kies \'n foto om op te laai. Die foto sal outomaties 600x600px gewees word.', 'Choose a photo to upload. The photo will be automatically resized to 600x600px.') ?></small>
          </div>
        </div>

        <div class="account-form-grid">
          <div class="account-field">
            <label for="name" class="account-label"><?= T('Naam', 'Name') ?> *</label>
            <input type="text" id="name" name="name" value="<?= htmlspecialchars($currentUser['name'] ?? '') ?>" required class="account-input">
          </div>
          <div class="account-field">
            <label for="surname" class="account-label"><?= T('Van', 'Surname') ?> *</label>
            <input type="text" id="surname" name="surname" value="<?= htmlspecialchars($currentUser['surname'] ?? '') ?>" required class="account-input">
          </div>
        </div>

        <div class="account-form-grid">
          <div class="account-field">
            <label for="email" class="account-label">Email *</label>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($currentUser['email'] ?? '') ?>" required class="account-input">
          </div>
          <div class="account-field">
            <label for="phone" class="account-label"><?= T('Selfoon', 'Phone') ?></label>
            <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($currentUser['phone'] ?? '') ?>" class="account-input">
          </div>
        </div>

        <div class="account-form-grid">
          <div class="account-field">
            <label for="language" class="account-label"><?= T('Taal', 'Language') ?></label>
            <select id="language" name="language" class="account-select">
              <option value="af" <?= ($currentUser['language'] ?? 'af') === 'af' ? 'selected' : '' ?>>Afrikaans</option>
              <option value="en" <?= ($currentUser['language'] ?? 'af') === 'en' ? 'selected' : '' ?>>English</option>
            </select>
          </div>
          <div class="account-field">
            <label for="birthdate" class="account-label"><?= T('Geboortedatum', 'Birthdate') ?></label>
            <input type="date" id="birthdate" name="birthdate" value="<?= htmlspecialchars($currentUser['birthdate'] ?? '') ?>" class="account-input">
          </div>
        </div>

        <div class="account-form-grid">
          <div class="account-field">
            <label for="marital_status" class="account-label"><?= T('Huwelikstatus', 'Marital Status') ?></label>
            <select id="marital_status" name="marital_status" class="account-select">
              <option value=""><?= T('Kies', 'Select') ?></option>
              <option value="getroud" <?= ($currentUser['marital_status'] ?? '') === 'getroud' ? 'selected' : '' ?>><?= T('Getroud', 'Married') ?></option>
              <option value="ongetroud" <?= ($currentUser['marital_status'] ?? '') === 'ongetroud' ? 'selected' : '' ?>><?= T('Ongetroud', 'Unmarried') ?></option>
            </select>
          </div>
          <div class="account-field">
            <label for="province" class="account-label"><?= T('Provinsie', 'Province') ?></label>
            <select id="province" name="province" class="account-select">
              <option value=""><?= T('Kies', 'Select') ?></option>
              <?php foreach ($provinces as $prov): ?>
                <option value="<?= (int)$prov['id'] ?>" <?= ((int)($currentUser['province_id'] ?? 0) === (int)$prov['id']) ? 'selected' : '' ?>><?= htmlspecialchars($prov['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="account-form-grid">
          <div class="account-field">
            <label for="town" class="account-label"><?= T('Stad/Dorp', 'Town/City') ?></label>
            <select id="town" name="town" <?= empty($currentUser['province_id']) ? 'disabled' : '' ?> class="account-select">
              <?php
              $pidSel = (string)($currentUser['province_id'] ?? '');
              $tidSelInt = (int)($currentUser['town_id'] ?? 0);
              if (!$pidSel) {
                echo '<option value="">'.T('Kies provinsie eers','Select province first').'</option>';
              } else {
                echo '<option value="">'.T('Kies','Select').'</option>';
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
            <label for="congregation" class="account-label"><?= T('Gemeente', 'Congregation') ?></label>
            <select id="congregation" name="congregation" <?= empty($currentUser['town_id']) ? 'disabled' : '' ?> class="account-select">
              <?php
              $tidSel = (string)($currentUser['town_id'] ?? '');
              $cidSelInt = (int)($currentUser['congregation_id'] ?? 0);
              if (!$tidSel) {
                echo '<option value="">'.T('Kies dorp eers','Select town first').'</option>';
              } else {
                echo '<option value="">'.T('Kies','Select').'</option>';
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

        <?php if (!empty($spouseOptions) && empty($currentUser['spouse_user_id'])): ?>
        <div class="account-field">
          <label for="spouse_user_id" class="account-label"><?= T('Eggenoot/Eggenote', 'Spouse') ?></label>
          <select id="spouse_user_id" name="spouse_user_id" class="account-select">
            <option value=""><?= T('Kies...', 'Select...') ?></option>
            <?php foreach ($spouseOptions as $spouse): ?>
              <option value="<?= (int)$spouse['id'] ?>"><?= htmlspecialchars($spouse['name'] . ' ' . $spouse['surname']) ?></option>
            <?php endforeach; ?>
          </select>
          <small class="account-hint"><?= T('Stuur \'n versoek om jou eggenoot te koppel. Die ander persoon moet goedkeur.', 'Send a request to link your spouse. The other person must approve.') ?></small>
        </div>
        <?php elseif (!empty($currentUser['spouse_user_id'])): ?>
        <?php
          $spouseStmt = $pdo->prepare("SELECT name, surname FROM users WHERE id = ? LIMIT 1");
          $spouseStmt->execute([$currentUser['spouse_user_id']]);
          $spouseData = $spouseStmt->fetch(PDO::FETCH_ASSOC);
        ?>
        <div class="account-notice account-notice-success">
          <?= T('Jy is gekoppel aan ', 'You are linked to ') ?>
          <strong><?= htmlspecialchars(($spouseData['name'] ?? '') . ' ' . ($spouseData['surname'] ?? '')) ?></strong>
        </div>
        <?php endif; ?>

        <div class="account-field">
          <label for="about" class="account-label"><?= T('Oor', 'About') ?></label>
          <textarea id="about" name="about" rows="4" placeholder="<?= T('Vertel ons meer van jouself', 'Tell us more about yourself') ?>" class="account-textarea"><?= htmlspecialchars($currentUser['about'] ?? '') ?></textarea>
        </div>

        <div class="account-actions">
          <a href="/admin/index.php" class="account-btn account-btn-secondary"><?= T('Kanselleer', 'Cancel') ?></a>
          <button type="submit" class="account-btn account-btn-primary"><?= T('Stoor', 'Save') ?></button>
        </div>
      </form>
    </div>
  </main>

  <script src="/admin/js/account.js?v=<?= $VER ?>"></script>
  <script>
  const townsByProvince = <?= json_encode($townsByProvince, JSON_UNESCAPED_UNICODE) ?>;
  const congsByTown = <?= json_encode($congsByTown, JSON_UNESCAPED_UNICODE) ?>;
  const txtSelect = <?= json_encode(T('Kies','Select')) ?>;
  const txtProvFirst = <?= json_encode(T('Kies provinsie eers','Select province first')) ?>;
  const txtTownFirst = <?= json_encode(T('Kies dorp eers','Select town first')) ?>;

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
        alert(data.error || 'Error');
      }
    } catch (e) {
      alert('Network error: ' + e.message);
    }
  }
  </script>

  <?php require_once __DIR__ . '/../header_footer/footer.php'; ?>
</body>
</html>