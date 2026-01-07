<?php
$myAmpId = (int)($currentUser['amp_id'] ?? 10);
$myTownId = (int)($currentUser['town_id'] ?? 0);
$myCongId = (int)($currentUser['congregation_id'] ?? 0);

$makeAppointment = isset($_GET['make_appointment']) && $_GET['make_appointment'] === '1';
$targetUserId = isset($_GET['target_user']) && is_numeric($_GET['target_user']) ? (int)$_GET['target_user'] : 0;
$presetDate = isset($_GET['date']) ? trim($_GET['date']) : '';
$presetTime = isset($_GET['time']) ? trim($_GET['time']) : '';

$targetUser = null;
if ($makeAppointment && $targetUserId) {
  try {
    $stmt = $pdo->prepare("SELECT id, name, surname, photo FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$targetUserId]);
    $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);
  } catch (Throwable $e) {
    $targetUser = null;
  }
}

$canSeeAmps = [];
$scopeFilter = '';
$scopeParams = [];

if ($myAmpId === 1) {
  $canSeeAmps = [2,3,4];
} elseif (in_array($myAmpId, [2,3,4])) {
  $canSeeAmps = [1,5,6,7,8,9,10];
  if ($myTownId) {
    $scopeFilter = 'AND u.town_id = :town_id';
    $scopeParams[':town_id'] = $myTownId;
  }
} elseif ($myAmpId === 5) {
  $canSeeAmps = [2,3,4,6,7,8,9,10];
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
    $ampList = implode(',', $canSeeAmps);
    $sql = "SELECT u.id, u.name, u.surname, u.amp_id, u.email, u.phone, u.photo, u.gender,
      CASE WHEN u.gender='vrou' THEN a.female_name ELSE a.male_name END AS amp_name,
      c.name AS congregation
      FROM users u
      LEFT JOIN amptes a ON a.id = u.amp_id
      LEFT JOIN congregations c ON c.id = u.congregation_id
      WHERE u.amp_id IN ($ampList) 
      AND u.status = 'approved'
      $scopeFilter
      ORDER BY u.amp_id ASC, u.gender ASC, u.surname ASC, u.name ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($scopeParams);
    $amptes = $stmt->fetchAll(PDO::FETCH_ASSOC);
  } catch (Throwable $e) {
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
krsort($groupedAmptes);

$myAppointments = [];
try {
  $stmt = $pdo->prepare("SELECT aa.*, 
    u.name AS requester_name, u.surname AS requester_surname
    FROM ampte_appointments aa
    JOIN users u ON u.id = aa.requester_id
    WHERE aa.target_user_id = ? AND aa.status = 'pending'
    ORDER BY aa.appointment_date ASC, aa.appointment_time ASC");
  $stmt->execute([$userId]);
  $myAppointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $myAppointments = [];
}
?>

<div class="afsprake-section">
  <div class="admin-section-header">
    <div class="admin-icon-wrapper">
      <svg class="admin-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <rect x="3" y="4" width="18" height="18" rx="2" stroke="currentColor" stroke-width="2"/>
        <line x1="3" y1="10" x2="21" y2="10" stroke="currentColor" stroke-width="2"/>
      </svg>
    </div>
    <h2 class="admin-section-title"><?= t('appointments') ?></h2>
  </div>

  <?php if (!empty($myAppointments)): ?>
  <div class="afsprake-pending-section">
    <h3 class="afsprake-subtitle"><?= t('pending_requests') ?></h3>
    <div class="afsprake-list">
      <?php foreach ($myAppointments as $appt): ?>
      <div class="afsprake-card">
        <div class="afsprake-header">
          <strong><?= esc($appt['requester_name'] . ' ' . $appt['requester_surname']) ?></strong>
          <?= t('wants_appointment') ?>
        </div>
        <div class="afsprake-details">
          <div class="afsprake-detail-item">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <rect x="3" y="4" width="18" height="18" rx="2" stroke="currentColor" stroke-width="2"/>
              <line x1="3" y1="10" x2="21" y2="10" stroke="currentColor" stroke-width="2"/>
            </svg>
            <span><?= esc(date('d F Y', strtotime($appt['appointment_date']))) ?></span>
          </div>
          <div class="afsprake-detail-item">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
              <polyline points="12 6 12 12 16 14" stroke="currentColor" stroke-width="2"/>
            </svg>
            <span><?= esc($appt['appointment_time']) ?></span>
          </div>
          <?php if ($appt['location']): ?>
          <div class="afsprake-detail-item">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z" stroke="currentColor" stroke-width="2"/>
              <circle cx="12" cy="10" r="3" stroke="currentColor" stroke-width="2"/>
            </svg>
            <span><?= esc($appt['location']) ?></span>
          </div>
          <?php endif; ?>
          <?php if ($appt['notes']): ?>
          <div class="afsprake-notes">
            <strong><?= t('notes') ?>:</strong>
            <p><?= esc($appt['notes']) ?></p>
          </div>
          <?php endif; ?>
        </div>
        <div class="afsprake-actions">
          <button class="afsprake-btn afsprake-btn-confirm confirm-appointment" data-id="<?= (int)$appt['id'] ?>">
            ✓ <?= t('confirm') ?>
          </button>
          <button class="afsprake-btn afsprake-btn-cancel cancel-appointment" data-id="<?= (int)$appt['id'] ?>">
            ✗ <?= t('cancel') ?>
          </button>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <div class="afsprake-search-wrapper">
    <input type="text" id="afsprakeSearch" class="afsprake-search-input" placeholder="<?= t('search_offices') ?>">
    <svg class="afsprake-search-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
      <circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="2"/>
      <path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="2"/>
    </svg>
  </div>

  <?php if (empty($groupedAmptes)): ?>
  <p class="admin-muted"><?= t('no_office_holders') ?></p>
  <?php else: ?>
  <div class="afsprake-container">
    <?php foreach ($groupedAmptes as $ampId => $ampList): ?>
    <div class="afsprake-group">
      <h4 class="afsprake-group-title"><?= esc($ampList[0]['amp_name'] ?? '') ?></h4>
      <div class="afsprake-grid">
        <?php foreach ($ampList as $amp):
          $hasPhoto = !empty($amp['photo']);
          $personName = trim(($amp['name'] ?? '') . ' ' . ($amp['surname'] ?? ''));
          $nameParts = preg_split('/\s+/', $personName);
          $personInitials = '';
          if (count($nameParts) >= 2) {
              $personInitials = strtoupper(mb_substr($nameParts[0], 0, 1) . mb_substr($nameParts[count($nameParts) - 1], 0, 1));
          } elseif (count($nameParts) === 1 && !empty($nameParts[0])) {
              $personInitials = strtoupper(mb_substr($nameParts[0], 0, 2));
          }
        ?>
        <div class="afsprake-card-person" data-id="<?= (int)$amp['id'] ?>" data-name="<?= esc(strtolower($amp['name'] . ' ' . $amp['surname'])) ?>">
          <div class="afsprake-avatar">
            <?php if ($hasPhoto): ?>
            <img src="<?= esc($amp['photo']) ?>" alt="<?= esc($amp['name']) ?>">
            <?php else: ?>
            <div class="afsprake-avatar-initials"><?= esc($personInitials) ?></div>
            <?php endif; ?>
          </div>
          <div class="afsprake-info">
            <h4><?= esc($amp['name'] . ' ' . $amp['surname']) ?></h4>
            <p class="afsprake-role"><?= esc($amp['amp_name'] ?? '') ?></p>
            <p class="afsprake-congregation"><?= esc($amp['congregation'] ?? '') ?></p>
          </div>
          <div class="afsprake-person-actions">
            <a href="/profile/?u=<?= (int)$amp['id'] ?>" class="afsprake-btn-icon" title="<?= t('profile') ?>">
              <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2M12 11a4 4 0 100-8 4 4 0 000 8z" stroke="currentColor" stroke-width="2"/>
              </svg>
            </a>
            <a href="/calendar/view.php?u=<?= (int)$amp['id'] ?>" class="afsprake-btn-icon afsprake-btn-calendar" title="<?= t('view_calendar') ?>">
              <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect x="3" y="4" width="18" height="18" rx="2" stroke="currentColor" stroke-width="2"/>
                <line x1="3" y1="10" x2="21" y2="10" stroke="currentColor" stroke-width="2"/>
              </svg>
            </a>
            <button type="button" class="afsprake-btn-icon afsprake-btn-appointment btn-make-appointment" data-id="<?= (int)$amp['id'] ?>" data-name="<?= esc($amp['name'] . ' ' . $amp['surname']) ?>" title="<?= t('make_appointment') ?>">
              <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2"/>
              </svg>
            </button>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<div id="appointmentModal" class="afsprake-modal">
  <div class="afsprake-modal-overlay"></div>
  <div class="afsprake-modal-content">
    <div class="afsprake-modal-header">
      <h3><?= t('make_appointment') ?></h3>
      <button type="button" class="afsprake-modal-close" onclick="closeAppointmentModal()">&times;</button>
    </div>
    <div class="afsprake-modal-body">
      <form id="appointmentForm">
        <input type="hidden" id="appointmentTargetId" name="target_user_id">

        <div class="afsprake-form-group">
          <label><?= t('with') ?>:</label>
          <div id="appointmentTargetName" class="afsprake-display-field"></div>
        </div>

        <div class="afsprake-form-row">
          <div class="afsprake-form-group">
            <label for="appointmentDate"><?= t('date') ?></label>
            <input type="date" id="appointmentDate" name="appointment_date" class="afsprake-form-control" required>
          </div>
          <div class="afsprake-form-group">
            <label for="appointmentTime"><?= t('time') ?></label>
            <input type="time" id="appointmentTime" name="appointment_time" class="afsprake-form-control" required>
          </div>
        </div>

        <div class="afsprake-form-group">
          <label for="appointmentLocation"><?= t('location') ?></label>
          <input type="text" id="appointmentLocation" name="location" class="afsprake-form-control">
        </div>

        <div class="afsprake-form-group">
          <label for="appointmentNotes"><?= t('notes') ?></label>
          <textarea id="appointmentNotes" name="notes" class="afsprake-form-textarea" rows="4"></textarea>
        </div>

        <div class="afsprake-modal-actions">
          <button type="button" class="afsprake-btn afsprake-btn-secondary" onclick="closeAppointmentModal()"><?= t('cancel') ?></button>
          <button type="submit" class="afsprake-btn afsprake-btn-primary"><?= t('send_request') ?></button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php if ($makeAppointment && $targetUser): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
  openAppointmentModal(
    <?= (int)$targetUser['id'] ?>,
    '<?= esc($targetUser['name'] . ' ' . $targetUser['surname']) ?>',
    '<?= esc($presetDate) ?>',
    '<?= esc($presetTime) ?>'
  );
});
</script>
<?php endif; ?>

<link rel="stylesheet" href="/admin/css/afsprake.css?v=<?= $VER ?>">
<script src="/admin/js/afsprake.js?v=<?= $VER ?>"></script>