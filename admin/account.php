<?php
declare(strict_types=1);
/**
 * Edit profile — "Light Through Glass".
 * Name, contact, language, place, photo (cropped in the browser, see
 * account.js), a spouse request and a few words about yourself. The form
 * posts to this page with the session's CSRF token; a saved form redirects
 * back here (so a refresh never saves twice) with the outcome shown once.
 * Roles are not on this page: a member cannot change their own office.
 */

require_once __DIR__ . '/../security/auth_gate.php';
require_once __DIR__ . '/../includes/languages.php';
require_once __DIR__ . '/../includes/admin_page.php';
require_once __DIR__ . '/api/notifications/helper.php';

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    header('Location: /login.php');
    exit;
}

if (isset($_GET['lang']) && in_array($_GET['lang'], SUPPORTED_LANGS, true)) {
    $_SESSION['language'] = $_GET['lang'];
    header('Location: /admin/account.php');
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

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$userId]);
$currentUser = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$currentUser) {
    header('Location: /logout.php');
    exit;
}

// ----- Lists the form needs (also what a posted value is checked against) -----
$provinces = [];
$townsByProvince = [];
$congsByTown = [];
try {
    $provinces = $pdo->query('SELECT id, name FROM provinces ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($pdo->query('SELECT id, name, province_id FROM towns ORDER BY name')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $townsByProvince[(string)$row['province_id']][] = ['id' => (int)$row['id'], 'name' => $row['name']];
    }
    foreach ($pdo->query('SELECT id, name, town_id FROM congregations ORDER BY name')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $congsByTown[(string)$row['town_id']][] = ['id' => (int)$row['id'], 'name' => $row['name']];
    }
} catch (Throwable $e) {
    error_log('Account lists: ' . $e->getMessage());
}

// Who can be asked to be a spouse: unmarried, approved, the other gender, same congregation
$spouseOptions = [];
if (!empty($currentUser['congregation_id']) && empty($currentUser['spouse_user_id'])) {
    try {
        $oppGender = (strtolower((string)($currentUser['gender'] ?? '')) === 'man') ? 'vrou' : 'man';
        $stmt = $pdo->prepare("SELECT id, name, surname FROM users
            WHERE congregation_id = ? AND gender = ? AND status = 'approved'
            AND id <> ? AND spouse_user_id IS NULL
            ORDER BY surname, name");
        $stmt->execute([$currentUser['congregation_id'], $oppGender, $userId]);
        $spouseOptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('Account spouse options: ' . $e->getMessage());
    }
}

// ----- Save -----
$errors = [];
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        if (!csrf_verify((string)($_POST['csrf_token'] ?? ''))) {
            $errors[] = 'adm_err_session';
        }

        $name = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 100);
        $surname = mb_substr(trim((string)($_POST['surname'] ?? '')), 0, 100);
        $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
        $phone = mb_substr(trim((string)($_POST['phone'] ?? '')), 0, 30);
        $language = in_array($_POST['language'] ?? '', SUPPORTED_LANGS, true) ? (string)$_POST['language'] : $lang;
        $about = mb_substr(trim((string)($_POST['about'] ?? '')), 0, 1000);

        $maritalStatus = (string)($_POST['marital_status'] ?? '');
        if (!in_array($maritalStatus, ['getroud', 'ongetroud', 'wewenaar', 'weduwee'], true)) {
            $maritalStatus = '';
        }

        $birthdate = trim((string)($_POST['birthdate'] ?? ''));
        if ($birthdate !== '') {
            $bd = DateTimeImmutable::createFromFormat('!Y-m-d', $birthdate);
            if (!$bd || $bd->format('Y-m-d') !== $birthdate || $bd > new DateTimeImmutable('today') || $bd < new DateTimeImmutable('1900-01-01')) {
                $errors[] = 'adm_acc_bad_birthdate';
            }
        }

        if ($name === '' || $surname === '') {
            $errors[] = 'adm_acc_name_required';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
            $errors[] = 'adm_acc_bad_email';
        } else {
            $dup = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
            $dup->execute([$email, $userId]);
            if ($dup->fetch()) {
                $errors[] = 'adm_acc_email_taken';
            }
        }

        // Province > town > congregation have to belong together
        $provinceId = ctype_digit((string)($_POST['province'] ?? '')) ? (int)$_POST['province'] : 0;
        $townId = ctype_digit((string)($_POST['town'] ?? '')) ? (int)$_POST['town'] : 0;
        $congId = ctype_digit((string)($_POST['congregation'] ?? '')) ? (int)$_POST['congregation'] : 0;
        $townName = null;
        $townOk = false;
        foreach ($townsByProvince[(string)$provinceId] ?? [] as $tw) {
            if ($tw['id'] === $townId) {
                $townOk = true;
                $townName = (string)$tw['name'];
            }
        }
        if (!$townOk) {
            $townId = 0;
        }
        $congOk = false;
        foreach ($congsByTown[(string)$townId] ?? [] as $cg) {
            if ($cg['id'] === $congId) {
                $congOk = true;
            }
        }
        if (!$congOk) {
            $congId = 0;
        }
        if (!in_array($provinceId, array_map('intval', array_column($provinces, 'id')), true)) {
            $provinceId = 0;
        }

        // The photo: cropped in the browser (data URL), else a plain upload
        $photoBinary = '';
        $cropped = (string)($_POST['cropped_photo_data'] ?? '');
        if ($cropped !== '' && preg_match('#^data:image/(?:jpeg|png|webp);base64,#i', $cropped)) {
            $photoBinary = (string)base64_decode(substr($cropped, (int)strpos($cropped, ',') + 1), true);
        } elseif (isset($_FILES['photo']) && ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK
                  && (int)($_FILES['photo']['size'] ?? 0) <= 12 * 1024 * 1024) {
            $photoBinary = (string)@file_get_contents((string)$_FILES['photo']['tmp_name']);
        }
        $newPhoto = null;
        if (!$errors && $photoBinary !== '') {
            $newPhoto = admin_store_profile_photo($userId, $photoBinary);
            if ($newPhoto === null) {
                $errors[] = 'adm_acc_bad_photo';
            }
        }

        if (!$errors) {
            $notices = ['profile_updated'];

            $cols = $pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN, 0);
            $set = ['name = ?', 'surname = ?', 'email = ?', 'phone = ?', 'language = ?', 'birthdate = ?'];
            $vals = [$name, $surname, $email, $phone, $language, ($birthdate !== '' ? $birthdate : null)];
            $optional = [
                'marital_status' => ($maritalStatus !== '' ? $maritalStatus : null),
                'province_id' => ($provinceId > 0 ? $provinceId : null),
                'town_id' => ($townId > 0 ? $townId : null),
                'congregation_id' => ($congId > 0 ? $congId : null),
                'town' => $townName,
                'about' => ($about !== '' ? $about : null),
            ];
            foreach ($optional as $col => $val) {
                if (in_array($col, $cols, true)) {
                    $set[] = "{$col} = ?";
                    $vals[] = $val;
                }
            }
            if ($newPhoto !== null) {
                $set[] = 'photo = ?';
                $vals[] = $newPhoto;
            }
            if (in_array('updated_at', $cols, true)) {
                $set[] = 'updated_at = CURRENT_TIMESTAMP';
            }
            $vals[] = $userId;

            $pdo->beginTransaction();
            $pdo->prepare('UPDATE users SET ' . implode(', ', $set) . ' WHERE id = ?')->execute($vals);

            // A new town / congregation: the rooms of the old one are left behind
            $oldTownId = (int)($currentUser['town_id'] ?? 0);
            if ($oldTownId > 0 && $townId > 0 && $oldTownId !== $townId) {
                $pdo->prepare("DELETE rm FROM room_memberships rm
                    INNER JOIN rooms r ON rm.room_id = r.id
                    WHERE rm.user_id = ? AND r.town_id = ?
                    AND r.type IN ('opsienerskap', 'sondagskool', 'jeug')")->execute([$userId, $oldTownId]);
            }
            $oldCongId = (int)($currentUser['congregation_id'] ?? 0);
            if ($oldCongId > 0 && $congId > 0 && $oldCongId !== $congId) {
                $pdo->prepare("DELETE rm FROM room_memberships rm
                    INNER JOIN rooms r ON rm.room_id = r.id
                    WHERE rm.user_id = ? AND r.gemeente_id = ? AND r.type = 'gemeente'")->execute([$userId, $oldCongId]);
            }
            $pdo->commit();

            // A spouse request: one pending at a time, only to someone the form offered
            $spouseId = ctype_digit((string)($_POST['spouse_user_id'] ?? '')) ? (int)$_POST['spouse_user_id'] : 0;
            $offered = array_map('intval', array_column($spouseOptions, 'id'));
            if ($spouseId > 0 && in_array($spouseId, $offered, true) && $congId === (int)($currentUser['congregation_id'] ?? 0)) {
                $check = $pdo->prepare("SELECT id FROM spouse_requests WHERE status = 'pending'
                    AND (requester_id IN (?, ?) OR receiver_id IN (?, ?)) LIMIT 1");
                $check->execute([$userId, $spouseId, $userId, $spouseId]);
                if ($check->fetch()) {
                    $notices[] = 'spouse_request_already_pending';
                } else {
                    $pdo->prepare("INSERT INTO spouse_requests (requester_id, receiver_id, status) VALUES (?, ?, 'pending')")
                        ->execute([$userId, $spouseId]);
                    createAdminNotification($spouseId, 'spouse_request', [
                        'from_name' => admin_full_name($currentUser),
                    ]);
                    $notices[] = 'spouse_request_sent';
                }
            }

            $_SESSION['language'] = $language;
            $_SESSION['admin_flash'] = $notices;
            header('Location: /admin/account.php');
            exit;
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Account save: ' . $e->getMessage());
        $errors[] = 'adm_err_server';
    }

    // Show what was typed again, not what is stored
    $currentUser = array_merge($currentUser, [
        'name' => $name ?? $currentUser['name'], 'surname' => $surname ?? $currentUser['surname'],
        'email' => $email ?? $currentUser['email'], 'phone' => $phone ?? $currentUser['phone'],
        'language' => $language ?? $currentUser['language'], 'birthdate' => $birthdate ?? $currentUser['birthdate'],
        'marital_status' => ($maritalStatus ?? '') ?: ($currentUser['marital_status'] ?? ''),
        'province_id' => ($provinceId ?? 0) ?: null, 'town_id' => ($townId ?? 0) ?: null,
        'congregation_id' => ($congId ?? 0) ?: null, 'about' => $about ?? ($currentUser['about'] ?? ''),
    ]);
}

// The outcome of the last save, shown once
$flash = [];
if (!empty($_SESSION['admin_flash']) && is_array($_SESSION['admin_flash'])) {
    $flash = $_SESSION['admin_flash'];
    unset($_SESSION['admin_flash']);
}

// A spouse request that involves me
$pendingSpouseRequest = null;
try {
    $stmt = $pdo->prepare("SELECT sr.id, sr.requester_id, u.name, u.surname FROM spouse_requests sr
        JOIN users u ON u.id = CASE WHEN sr.requester_id = ? THEN sr.receiver_id ELSE sr.requester_id END
        WHERE (sr.requester_id = ? OR sr.receiver_id = ?) AND sr.status = 'pending'
        LIMIT 1");
    $stmt->execute([$userId, $userId, $userId]);
    $pendingSpouseRequest = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $e) {
    error_log('Account pending spouse: ' . $e->getMessage());
}

// My spouse, when the link goes both ways
$spouseData = null;
if (!empty($currentUser['spouse_user_id'])) {
    $stmt = $pdo->prepare('SELECT name, surname FROM users WHERE id = ? AND spouse_user_id = ? LIMIT 1');
    $stmt->execute([(int)$currentUser['spouse_user_id'], $userId]);
    $spouseData = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$fullName = admin_full_name($currentUser);
$photoUrl = admin_photo_url($currentUser['photo'] ?? null);
$widowValue = (strtolower((string)($currentUser['gender'] ?? '')) === 'vrou') ? 'weduwee' : 'wewenaar';
$pidSel = (string)($currentUser['province_id'] ?? '');
$tidSel = (string)($currentUser['town_id'] ?? '');

$uiStrings = ['ok' => t('confirm'), 'cancel' => t('cancel'), 'networkError' => t('network_error')];
$accountData = [
    'townsByProvince' => $townsByProvince,
    'congsByTown' => $congsByTown,
    't' => [
        'select' => t('select'),
        'provFirst' => t('select_province_first'),
        'townFirst' => t('select_town_first'),
        'cropTitle' => t('adm_crop_title'),
        'cropHint' => t('adm_crop_hint'),
        'save' => t('save'),
        'cancel' => t('cancel'),
        'close' => t('close'),
        'zoomIn' => t('adm_zoom_in'),
        'zoomOut' => t('adm_zoom_out'),
        'photoChosen' => t('adm_photo_chosen'),
        'cancelRequest' => t('cancel_request'),
        'accepted' => t('adm_spouse_linked'),
    ],
];
?><!doctype html>
<html lang="<?= esc($lang) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="csrf-token" content="<?= esc(csrf_token()) ?>">
  <title><?= esc(t('edit_profile')) ?></title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;1,500;1,600&family=Parisienne&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="<?= admin_asset('/admin/css/admin.css') ?>">
  <link rel="stylesheet" href="<?= admin_asset('/admin/css/account.css') ?>">
  <link rel="stylesheet" href="<?= admin_asset('/admin/css/ui.css') ?>">
</head>
<body class="admin-body" data-tab="account">
  <?php require_once __DIR__ . '/../header_footer/header.php'; ?>

  <div class="admin-aura" aria-hidden="true"><div class="admin-rays"></div><div class="admin-halo"></div></div>

  <main class="admin-main account-main" id="adminMain">
    <section class="admin-hero" aria-labelledby="accountTitle">
      <p class="admin-eyebrow">
        <svg class="admin-eyebrow-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
        <span><?= esc(t('my_profile')) ?></span>
      </p>
      <h2 class="admin-title" id="accountTitle"><span><?= esc(t('edit_profile')) ?></span></h2>
      <p class="admin-sub"><?= esc(t('update_personal_info')) ?></p>
    </section>

    <div class="account-card">
      <?php foreach ($flash as $key): ?>
        <div class="admin-notice admin-notice-success" role="status"><?= esc(t((string)$key)) ?></div>
      <?php endforeach; ?>
      <?php foreach ($errors as $key): ?>
        <div class="admin-notice admin-notice-error" role="alert"><?= esc(t($key)) ?></div>
      <?php endforeach; ?>

      <?php if ($pendingSpouseRequest): ?>
        <div class="admin-notice admin-notice-info" id="spouseNotice">
          <?php if ((int)$pendingSpouseRequest['requester_id'] === $userId): ?>
            <?= esc(t('you_sent_spouse_request')) ?>
            <strong><?= esc(admin_full_name($pendingSpouseRequest)) ?></strong>.
            <?= esc(t('waiting_approval')) ?>
            <div class="account-notice-actions">
              <button type="button" class="admin-btn admin-btn-secondary" data-spouse="cancel" data-id="<?= (int)$pendingSpouseRequest['id'] ?>"><?= esc(t('cancel_request')) ?></button>
            </div>
          <?php else: ?>
            <strong><?= esc(admin_full_name($pendingSpouseRequest)) ?></strong>
            <?= esc(t('wants_to_marry_you')) ?>
            <div class="account-notice-actions">
              <button type="button" class="admin-btn admin-btn-primary" data-spouse="accept" data-id="<?= (int)$pendingSpouseRequest['id'] ?>"><?= esc(t('accept')) ?></button>
              <button type="button" class="admin-btn admin-btn-secondary" data-spouse="reject" data-id="<?= (int)$pendingSpouseRequest['id'] ?>"><?= esc(t('reject')) ?></button>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <form method="post" enctype="multipart/form-data" class="account-form admin-form" id="accountForm" novalidate>
        <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>">

        <div class="account-photo-section">
          <button type="button" class="account-avatar-wrapper" id="avatarPick" aria-label="<?= esc(t('profile_photo')) ?>">
            <?php if ($photoUrl !== ''): ?>
            <img id="avatar-preview" src="<?= esc($photoUrl) ?>" alt="" class="account-avatar" width="104" height="104">
            <?php else: ?>
            <span id="avatar-initials" class="account-avatar account-avatar-initials" aria-hidden="true"><?= esc(admin_initials($fullName)) ?></span>
            <img id="avatar-preview" src="" alt="" class="account-avatar" width="104" height="104" hidden>
            <?php endif; ?>
            <span class="account-avatar-overlay" aria-hidden="true">
              <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
            </span>
          </button>
          <div class="admin-field account-photo-field">
            <label for="photo" class="admin-label"><?= esc(t('profile_photo')) ?></label>
            <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp" class="account-input-file">
            <span class="admin-hint" id="photoHint"><?= esc(t('photo_hint')) ?></span>
          </div>
        </div>

        <div class="admin-form-row">
          <div class="admin-field">
            <label for="name" class="admin-label"><?= esc(t('name')) ?> *</label>
            <input type="text" id="name" name="name" value="<?= esc($currentUser['name'] ?? '') ?>" required maxlength="100" autocomplete="given-name" class="admin-input">
          </div>
          <div class="admin-field">
            <label for="surname" class="admin-label"><?= esc(t('surname')) ?> *</label>
            <input type="text" id="surname" name="surname" value="<?= esc($currentUser['surname'] ?? '') ?>" required maxlength="100" autocomplete="family-name" class="admin-input">
          </div>
        </div>

        <div class="admin-form-row">
          <div class="admin-field">
            <label for="email" class="admin-label"><?= esc(t('adm_email')) ?> *</label>
            <input type="email" id="email" name="email" value="<?= esc($currentUser['email'] ?? '') ?>" required maxlength="190" autocomplete="email" class="admin-input">
          </div>
          <div class="admin-field">
            <label for="phone" class="admin-label"><?= esc(t('phone')) ?></label>
            <input type="tel" id="phone" name="phone" value="<?= esc($currentUser['phone'] ?? '') ?>" maxlength="30" autocomplete="tel" class="admin-input">
          </div>
        </div>

        <div class="admin-form-row">
          <div class="admin-field">
            <label for="language" class="admin-label"><?= esc(t('language')) ?></label>
            <select id="language" name="language" class="admin-select">
              <?php foreach (SUPPORTED_LANGS as $lc): ?>
                <option value="<?= esc($lc) ?>" <?= ($currentUser['language'] ?? $lang) === $lc ? 'selected' : '' ?>><?= esc(LANG_NAMES[$lc]) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="admin-field">
            <label for="birthdate" class="admin-label"><?= esc(t('birthdate')) ?></label>
            <input type="date" id="birthdate" name="birthdate" value="<?= esc($currentUser['birthdate'] ?? '') ?>" max="<?= esc(date('Y-m-d')) ?>" autocomplete="bday" class="admin-input">
          </div>
        </div>

        <div class="admin-form-row">
          <div class="admin-field">
            <label for="marital_status" class="admin-label"><?= esc(t('marital_status')) ?></label>
            <select id="marital_status" name="marital_status" class="admin-select">
              <option value=""><?= esc(t('select')) ?></option>
              <option value="getroud" <?= ($currentUser['marital_status'] ?? '') === 'getroud' ? 'selected' : '' ?>><?= esc(t('married')) ?></option>
              <option value="ongetroud" <?= ($currentUser['marital_status'] ?? '') === 'ongetroud' ? 'selected' : '' ?>><?= esc(t('unmarried')) ?></option>
              <option value="<?= esc($widowValue) ?>" <?= in_array($currentUser['marital_status'] ?? '', ['weduwee', 'wewenaar'], true) ? 'selected' : '' ?>><?= esc(t($widowValue)) ?></option>
            </select>
          </div>
          <div class="admin-field">
            <label for="province" class="admin-label"><?= esc(t('province')) ?></label>
            <select id="province" name="province" class="admin-select">
              <option value=""><?= esc(t('select')) ?></option>
              <?php foreach ($provinces as $prov): ?>
                <option value="<?= (int)$prov['id'] ?>" <?= $pidSel === (string)$prov['id'] ? 'selected' : '' ?>><?= esc($prov['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="admin-form-row">
          <div class="admin-field">
            <label for="town" class="admin-label"><?= esc(t('town_city')) ?></label>
            <select id="town" name="town" class="admin-select" <?= $pidSel === '' ? 'disabled' : '' ?>>
              <?php if ($pidSel === ''): ?>
                <option value=""><?= esc(t('select_province_first')) ?></option>
              <?php else: ?>
                <option value=""><?= esc(t('select')) ?></option>
                <?php foreach ($townsByProvince[$pidSel] ?? [] as $tw): ?>
                  <option value="<?= (int)$tw['id'] ?>" <?= $tidSel === (string)$tw['id'] ? 'selected' : '' ?>><?= esc($tw['name']) ?></option>
                <?php endforeach; ?>
              <?php endif; ?>
            </select>
          </div>
          <div class="admin-field">
            <label for="congregation" class="admin-label"><?= esc(t('congregation')) ?></label>
            <select id="congregation" name="congregation" class="admin-select" <?= $tidSel === '' ? 'disabled' : '' ?>>
              <?php if ($tidSel === ''): ?>
                <option value=""><?= esc(t('select_town_first')) ?></option>
              <?php else: ?>
                <option value=""><?= esc(t('select')) ?></option>
                <?php foreach ($congsByTown[$tidSel] ?? [] as $cg): ?>
                  <option value="<?= (int)$cg['id'] ?>" <?= (string)($currentUser['congregation_id'] ?? '') === (string)$cg['id'] ? 'selected' : '' ?>><?= esc($cg['name']) ?></option>
                <?php endforeach; ?>
              <?php endif; ?>
            </select>
          </div>
        </div>

        <?php if (!empty($spouseOptions) && empty($currentUser['spouse_user_id']) && !$pendingSpouseRequest): ?>
        <div class="admin-field">
          <label for="spouse_user_id" class="admin-label"><?= esc(t('spouse')) ?></label>
          <select id="spouse_user_id" name="spouse_user_id" class="admin-select">
            <option value=""><?= esc(t('select')) ?>...</option>
            <?php foreach ($spouseOptions as $spouse): ?>
              <option value="<?= (int)$spouse['id'] ?>"><?= esc(admin_full_name($spouse)) ?></option>
            <?php endforeach; ?>
          </select>
          <span class="admin-hint"><?= esc(t('spouse_hint')) ?></span>
        </div>
        <?php elseif ($spouseData): ?>
        <div class="admin-notice admin-notice-success">
          <?= esc(t('you_are_linked_to')) ?> <strong><?= esc(admin_full_name($spouseData)) ?></strong>
        </div>
        <?php endif; ?>

        <div class="admin-field">
          <label for="about" class="admin-label"><?= esc(t('about')) ?></label>
          <textarea id="about" name="about" rows="4" maxlength="1000" placeholder="<?= esc(t('about_placeholder')) ?>" class="admin-textarea"><?= esc($currentUser['about'] ?? '') ?></textarea>
        </div>

        <div class="account-actions">
          <a href="/admin/index.php" class="admin-btn admin-btn-secondary"><?= esc(t('cancel')) ?></a>
          <button type="submit" class="admin-btn admin-btn-primary" id="accountSave"><?= esc(t('save')) ?></button>
        </div>
      </form>
    </div>
  </main>

  <script>
  window.OAC_UI_STRINGS = <?= json_encode($uiStrings, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  window.OAC_ACCOUNT = <?= json_encode($accountData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  </script>
  <script src="<?= admin_asset('/admin/js/ui.js') ?>"></script>
  <script src="<?= admin_asset('/admin/js/account.js') ?>"></script>

  <?php require_once __DIR__ . '/../header_footer/footer.php'; ?>
</body>
</html>
