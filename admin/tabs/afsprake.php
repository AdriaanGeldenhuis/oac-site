<?php
$myAmpId = (int)($currentUser['amp_id'] ?? 10);
$myTownId = (int)($currentUser['town_id'] ?? 0);
$myCongId = (int)($currentUser['congregation_id'] ?? 0);

// Links from the calendar / a profile: ?make_appointment=1&target_user=ID&date=Y-m-d&time=H:i
$makeAppointment = isset($_GET['make_appointment']) && $_GET['make_appointment'] === '1';
$targetUserId = isset($_GET['target_user']) && is_numeric($_GET['target_user']) ? (int)$_GET['target_user'] : 0;
$presetDate = isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$_GET['date']) ? (string)$_GET['date'] : '';
$presetTime = isset($_GET['time']) && preg_match('/^\d{2}:\d{2}$/', (string)$_GET['time']) ? (string)$_GET['time'] : '';

$targetUser = null;
if ($makeAppointment && $targetUserId) {
  try {
    $stmt = $pdo->prepare("SELECT id, name, surname, photo FROM users WHERE id = ? AND status = 'approved' AND id <> ? LIMIT 1");
    $stmt->execute([$targetUserId, $userId]);
    $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);
  } catch (Throwable $e) {
    $targetUser = null;
  }
}

$canSeeAmps = [];
$scopeFilter = '';
$scopeParams = [];

if ($myAmpId === 1) {
  $canSeeAmps = [2,3,4,5];
} elseif (in_array($myAmpId, [2,3,4])) {
  $canSeeAmps = [1,2,3,4,5,6,7,8,9,10];
  if ($myTownId) {
    $scopeFilter = 'AND u.town_id = :town_id';
    $scopeParams[':town_id'] = $myTownId;
  }
} elseif ($myAmpId === 5) {
  $canSeeAmps = [1,2,3,4,5,6,7,8,9,10];
  if ($myTownId) {
    $scopeFilter = 'AND u.town_id = :town_id';
    $scopeParams[':town_id'] = $myTownId;
  }
} elseif ($myAmpId === 6) {
  $canSeeAmps = [5,7,8,9,10];
  if ($myCongId) {
    $scopeFilter = 'AND u.congregation_id = :cong_id';
    $scopeParams[':cong_id'] = $myCongId;
  }
} elseif ($myAmpId === 7) {
  $canSeeAmps = [6,8,9,10];
  if ($myCongId) {
    $scopeFilter = 'AND u.congregation_id = :cong_id';
    $scopeParams[':cong_id'] = $myCongId;
  }
} elseif (in_array($myAmpId, [8,9])) {
  $canSeeAmps = [6,7,10];
  if ($myCongId) {
    $scopeFilter = 'AND u.congregation_id = :cong_id';
    $scopeParams[':cong_id'] = $myCongId;
  }
}

$amptes = [];
if (!empty($canSeeAmps)) {
  try {
    $ampList = implode(',', array_map('intval', $canSeeAmps));
    $sql = "SELECT u.id, u.name, u.surname, u.amp_id, u.email, u.phone, u.photo, u.gender,
      CASE WHEN u.gender='vrou' THEN a.female_name ELSE a.male_name END AS amp_name,
      c.name AS congregation
      FROM users u
      LEFT JOIN amptes a ON a.id = u.amp_id
      LEFT JOIN congregations c ON c.id = u.congregation_id
      WHERE u.amp_id IN ($ampList) 
      AND u.status = 'approved'
      AND u.id <> :me
      $scopeFilter
      ORDER BY u.amp_id ASC, u.gender ASC, u.surname ASC, u.name ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($scopeParams + [':me' => $userId]);
    $amptes = $stmt->fetchAll(PDO::FETCH_ASSOC);
  } catch (Throwable $e) {
    error_log('Admin afsprake list: ' . $e->getMessage());
    $amptes = [];
  }
}

$groupedAmptes = [];
foreach ($amptes as $amp) {
  $ampId = (int)$amp['amp_id'];
  if (!isset($groupedAmptes[$ampId])) {
    $groupedAmptes[$ampId] = [];
  }
  $groupedAmptes[$ampId][] = $amp;
}
ksort($groupedAmptes); // Apostel first

$myAppointments = [];
try {
  $stmt = $pdo->prepare("SELECT aa.id, aa.appointment_date, aa.appointment_time, aa.location, aa.notes,
    u.name AS requester_name, u.surname AS requester_surname
    FROM ampte_appointments aa
    JOIN users u ON u.id = aa.requester_id
    WHERE aa.target_user_id = ? AND aa.status = 'pending'
    AND aa.appointment_date >= CURDATE()
    ORDER BY aa.appointment_date ASC, aa.appointment_time ASC");
  $stmt->execute([$userId]);
  $myAppointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  error_log('Admin afsprake pending: ' . $e->getMessage());
  $myAppointments = [];
}
?>
<div class="afsprake-section" id="afsprakeRoot">
  <div class="admin-section-header">
    <div class="admin-icon-wrapper">
      <svg class="admin-icon" viewBox="0 0 24 24" aria-hidden="true">
        <rect x="3" y="4" width="18" height="18" rx="2"/>
        <path d="M3 10h18M8 2v4M16 2v4"/>
      </svg>
    </div>
    <h2 class="admin-section-title"><?= esc(t('appointments')) ?></h2>
  </div>

  <?php if (!empty($myAppointments)): ?>
  <section class="afsprake-pending-section" id="afsprakePending">
    <h3 class="admin-subtitle"><?= esc(t('pending_requests')) ?></h3>
    <div class="afsprake-list">
      <?php foreach ($myAppointments as $appt): ?>
      <article class="afsprake-card" data-card="<?= (int)$appt['id'] ?>">
        <p class="afsprake-header">
          <strong><?= esc(trim($appt['requester_name'] . ' ' . $appt['requester_surname'])) ?></strong>
          <?= esc(t('wants_appointment')) ?>
        </p>
        <div class="afsprake-details">
          <span class="afsprake-detail-item">
            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M3 10h18M8 2v4M16 2v4"/></svg>
            <time data-date="<?= esc($appt['appointment_date']) ?>" datetime="<?= esc($appt['appointment_date']) ?>"><?= esc($appt['appointment_date']) ?></time>
          </span>
          <span class="afsprake-detail-item">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
            <span><?= esc(substr((string)$appt['appointment_time'], 0, 5)) ?></span>
          </span>
          <?php if (!empty($appt['location'])): ?>
          <span class="afsprake-detail-item">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
            <span><?= esc($appt['location']) ?></span>
          </span>
          <?php endif; ?>
        </div>
        <?php if (!empty($appt['notes'])): ?>
        <p class="afsprake-notes"><strong><?= esc(t('notes')) ?>:</strong> <?= esc($appt['notes']) ?></p>
        <?php endif; ?>
        <div class="afsprake-actions">
          <button type="button" class="admin-btn admin-btn-primary confirm-appointment" data-id="<?= (int)$appt['id'] ?>">&#10003; <?= esc(t('confirm')) ?></button>
          <button type="button" class="admin-btn admin-btn-danger cancel-appointment" data-id="<?= (int)$appt['id'] ?>">&#10007; <?= esc(t('cancel')) ?></button>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if (!empty($groupedAmptes)): ?>
  <div class="afsprake-search-wrapper">
    <svg class="afsprake-search-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
    <input type="search" id="afsprakeSearch" class="admin-input afsprake-search-input" placeholder="<?= esc(t('search_offices')) ?>" aria-label="<?= esc(t('search_offices')) ?>" autocomplete="off">
  </div>

  <div class="afsprake-container">
    <?php foreach ($groupedAmptes as $groupAmpId => $ampList): ?>
    <section class="afsprake-group">
      <h3 class="admin-subtitle"><?= esc($ampList[0]['amp_name'] ?? '') ?></h3>
      <div class="afsprake-grid">
        <?php foreach ($ampList as $amp):
          $personName = trim(($amp['name'] ?? '') . ' ' . ($amp['surname'] ?? ''));
          $personPhoto = admin_photo_url($amp['photo'] ?? null);
        ?>
        <div class="afsprake-card-person" data-id="<?= (int)$amp['id'] ?>" data-name="<?= esc(mb_strtolower($personName)) ?>">
          <?php if ($personPhoto !== ''): ?>
          <img class="afsprake-avatar" src="<?= esc($personPhoto) ?>" alt="" width="52" height="52" loading="lazy" decoding="async">
          <?php else: ?>
          <span class="afsprake-avatar afsprake-avatar-initials" aria-hidden="true"><?= esc(admin_initials($personName)) ?></span>
          <?php endif; ?>
          <div class="afsprake-info">
            <h4><?= esc($personName) ?></h4>
            <?php if (!empty($amp['congregation'])): ?>
            <p class="afsprake-congregation"><?= esc($amp['congregation']) ?></p>
            <?php endif; ?>
          </div>
          <div class="afsprake-person-actions">
            <a href="/profile/?u=<?= (int)$amp['id'] ?>" class="afsprake-btn-icon" title="<?= esc(t('profile')) ?>" aria-label="<?= esc(t('profile')) ?>: <?= esc($personName) ?>">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            </a>
            <button type="button" class="afsprake-btn-icon afsprake-btn-appointment btn-make-appointment" data-id="<?= (int)$amp['id'] ?>" data-name="<?= esc($personName) ?>" title="<?= esc(t('make_appointment')) ?>" aria-label="<?= esc(t('make_appointment')) ?>: <?= esc($personName) ?>">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
            </button>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endforeach; ?>
    <p class="admin-muted" id="afsprakeNoMatch" hidden><?= esc(t('no_office_holders')) ?></p>
  </div>
  <?php else: ?>
  <p class="admin-muted"><?= esc(t('no_office_holders')) ?></p>
  <?php endif; ?>
</div>

<!-- Ask for an appointment -->
<div id="appointmentModal" class="afsprake-layer" hidden
     <?php if ($makeAppointment && $targetUser): ?>
     data-open-id="<?= (int)$targetUser['id'] ?>"
     data-open-name="<?= esc(trim($targetUser['name'] . ' ' . $targetUser['surname'])) ?>"
     data-open-date="<?= esc($presetDate) ?>"
     data-open-time="<?= esc($presetTime) ?>"
     <?php endif; ?>>
  <div class="afsprake-sheet" role="dialog" aria-modal="true" aria-labelledby="appointmentTitle">
    <div class="afsprake-sheet-head">
      <h3 id="appointmentTitle"><?= esc(t('make_appointment')) ?></h3>
      <button type="button" class="afsprake-modal-close" data-close aria-label="<?= esc(t('close')) ?>">&times;</button>
    </div>
    <form id="appointmentForm" class="admin-form" novalidate>
      <input type="hidden" id="appointmentTargetId" name="target_user_id">

      <div class="admin-field">
        <span class="admin-label"><?= esc(t('with')) ?></span>
        <div id="appointmentTargetName" class="afsprake-display-field"></div>
      </div>

      <div class="admin-form-row">
        <div class="admin-field">
          <label for="appointmentDate" class="admin-label"><?= esc(t('date')) ?></label>
          <input type="date" id="appointmentDate" name="appointment_date" class="admin-input" required>
        </div>
        <div class="admin-field">
          <label for="appointmentTime" class="admin-label"><?= esc(t('time')) ?></label>
          <input type="time" id="appointmentTime" name="appointment_time" class="admin-input" required>
        </div>
      </div>

      <div class="admin-field">
        <label for="appointmentLocation" class="admin-label"><?= esc(t('location')) ?></label>
        <input type="text" id="appointmentLocation" name="location" class="admin-input" maxlength="255" autocomplete="off">
      </div>

      <div class="admin-field">
        <label for="appointmentNotes" class="admin-label"><?= esc(t('notes')) ?></label>
        <textarea id="appointmentNotes" name="notes" class="admin-textarea" rows="3" maxlength="2000"></textarea>
      </div>

      <div class="admin-right">
        <button type="button" class="admin-btn admin-btn-secondary" data-close><?= esc(t('cancel')) ?></button>
        <button type="submit" class="admin-btn admin-btn-primary"><?= esc(t('send_request')) ?></button>
      </div>
    </form>
  </div>
</div>

<script>
  window.AFSPRAKE_LANG = <?= json_encode([
      'lang' => $lang,
      'requestSent' => t('adm_appt_sent'),
      'confirmed' => t('adm_appt_confirmed'),
      'cancelled' => t('adm_appt_cancelled'),
      'confirmCancel' => t('adm_appt_confirm_cancel'),
      'noMatch' => t('no_office_holders'),
      'pickDate' => t('adm_appt_pick'),
  ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="<?= admin_asset('/admin/js/afsprake.js') ?>"></script>
