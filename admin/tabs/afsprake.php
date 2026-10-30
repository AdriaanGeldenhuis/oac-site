<?php
/**
 * Appointments tab (amp 1-9), in the order of what needs doing:
 *   1. requests made to you (answer them),
 *   2. what is coming up (confirmed, and the requests you sent),
 *   3. the office bearers you may ask, by office, with a search and a filter.
 * Who is listed in 3 is the same rule as before ($canSeeAmps + town /
 * congregation). afsprake.js does the asking, answering and filtering.
 * People are named office first ("Priester Pieter van der Merwe").
 */
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
    $stmt = $pdo->prepare("SELECT id, name, surname, photo, amp_id, gender FROM users WHERE id = ? AND status = 'approved' AND id <> ? LIMIT 1");
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
    $sql = "SELECT u.id, u.name, u.surname, u.amp_id, u.photo, u.gender,
      c.name AS congregation
      FROM users u
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
  $groupedAmptes[(int)$amp['amp_id']][] = $amp;
}
ksort($groupedAmptes); // Apostel first

$myAppointments = [];
try {
  $stmt = $pdo->prepare("SELECT aa.id, aa.appointment_date, aa.appointment_time, aa.location, aa.notes,
    u.name, u.surname, u.amp_id, u.gender, u.photo
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

// What is coming up: confirmed appointments (either side) and the requests I sent
$upcoming = [];
try {
  $stmt = $pdo->prepare("SELECT aa.id, aa.requester_id, aa.appointment_date, aa.appointment_time, aa.location, aa.status,
    r.name AS r_name, r.surname AS r_surname, r.amp_id AS r_amp_id, r.gender AS r_gender,
    tu.name AS t_name, tu.surname AS t_surname, tu.amp_id AS t_amp_id, tu.gender AS t_gender
    FROM ampte_appointments aa
    JOIN users r ON r.id = aa.requester_id
    JOIN users tu ON tu.id = aa.target_user_id
    WHERE aa.appointment_date >= CURDATE()
    AND ((aa.status = 'confirmed' AND (aa.requester_id = ? OR aa.target_user_id = ?))
      OR (aa.status = 'pending' AND aa.requester_id = ?))
    ORDER BY aa.appointment_date ASC, aa.appointment_time ASC
    LIMIT 30");
  $stmt->execute([$userId, $userId, $userId]);
  $upcoming = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  error_log('Admin afsprake upcoming: ' . $e->getMessage());
  $upcoming = [];
}

// "Afspraak met <strong>Office Name</strong>", in the member's language
$apptWith = static function (string $name): string {
  return admin_fill_html(t('cal_appt_with'), ['name' => '<strong>' . esc($name) . '</strong>']);
};

/** The date / time / place line of an appointment */
$when = static function (array $appt): string {
  $date = (string)$appt['appointment_date'];
  $out = '<span>' . admin_icon('calendar') . '<time data-date="' . esc($date) . '" data-style="relative" datetime="' . esc($date) . '">' . esc($date) . '</time></span>'
    . '<span>' . admin_icon('clock') . esc(substr((string)$appt['appointment_time'], 0, 5)) . '</span>';
  if (!empty($appt['location'])) {
    $out .= '<span>' . admin_icon('pin') . esc($appt['location']) . '</span>';
  }
  return $out;
};
?>
<div id="afsprakeRoot">

  <?php if ($myAppointments): ?>
  <section class="adm-section" id="afsprakePending" data-appt-section aria-labelledby="apptPendingTitle">
    <div class="adm-section-head">
      <h3 class="adm-section-title" id="apptPendingTitle"><?= esc(t('adm_requests_to_you')) ?></h3>
      <span class="adm-badge adm-badge--static" data-count><?= count($myAppointments) ?></span>
    </div>
    <div class="adm-cards">
      <?php foreach ($myAppointments as $appt): $who = admin_person($appt, $lang); ?>
      <article class="adm-card adm-person afsprake-card afsprake-card--request" data-card="<?= (int)$appt['id'] ?>">
        <div class="adm-person-main">
          <?= admin_avatar($appt['photo'] ?? null, admin_full_name($appt)) ?>
          <div class="adm-person-text">
            <h4 class="adm-person-name adm-person-name--line"><strong><?= esc($who) ?></strong> <?= esc(t('wants_appointment')) ?></h4>
            <p class="adm-person-meta"><?= $when($appt) ?></p>
            <?php if (!empty($appt['notes'])): ?>
            <p class="afsprake-notes"><span class="admin-sr"><?= esc(t('notes')) ?>: </span><?= esc($appt['notes']) ?></p>
            <?php endif; ?>
          </div>
        </div>
        <div class="adm-person-actions">
          <button type="button" class="admin-btn admin-btn-ok confirm-appointment" data-id="<?= (int)$appt['id'] ?>" aria-label="<?= esc(t('confirm') . ': ' . $who) ?>"><?= admin_icon('check') ?><span><?= esc(t('confirm')) ?></span></button>
          <button type="button" class="admin-btn admin-btn-quiet-danger cancel-appointment" data-id="<?= (int)$appt['id'] ?>" aria-label="<?= esc(t('cancel') . ': ' . $who) ?>"><?= admin_icon('x') ?><span><?= esc(t('cancel')) ?></span></button>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <!-- Coming up; afsprake.js refreshes this slot after a confirm, a cancel or a new request -->
  <div id="afsprakeUpcomingSlot">
    <section class="adm-section" data-appt-section aria-labelledby="apptUpcomingTitle">
      <div class="adm-section-head">
        <h3 class="adm-section-title" id="apptUpcomingTitle"><?= esc(t('adm_appt_upcoming')) ?></h3>
        <?php if ($upcoming): ?><span class="adm-count" data-count><?= count($upcoming) ?></span><?php endif; ?>
      </div>
      <?php if (!$upcoming): ?>
      <p class="adm-muted-line"><?= esc(t('adm_appt_none')) ?></p>
      <?php else: ?>
      <ul class="adm-list">
        <?php foreach ($upcoming as $appt):
          $iAsked = (int)$appt['requester_id'] === $userId;
          $other = $iAsked ? admin_person($appt, $lang, 't_') : admin_person($appt, $lang, 'r_');
          $isPending = $appt['status'] === 'pending';
          $calLink = '/calendar/calendar.php#d=' . rawurlencode((string)$appt['appointment_date']) . '&i=' . rawurlencode('appointment-' . (int)$appt['id']);
        ?>
        <li class="adm-list-row afsprake-card" data-card="<?= (int)$appt['id'] ?>">
          <span class="adm-date-chip"><time data-date="<?= esc($appt['appointment_date']) ?>" data-style="chip" datetime="<?= esc($appt['appointment_date']) ?>"><?= esc($appt['appointment_date']) ?></time></span>
          <span class="adm-list-text">
            <span class="adm-list-title"><?= $apptWith($other) ?></span>
            <span class="adm-list-meta">
              <span class="adm-status <?= $isPending ? 'adm-status--wait' : 'adm-status--ok' ?>"><?= esc(t($isPending ? 'cal_status_pending' : 'cal_status_confirmed')) ?></span>
              <span><?= admin_icon('clock') ?><?= esc(substr((string)$appt['appointment_time'], 0, 5)) ?></span>
              <?php if (!empty($appt['location'])): ?><span><?= admin_icon('pin') ?><?= esc($appt['location']) ?></span><?php endif; ?>
            </span>
          </span>
          <span class="adm-list-actions">
            <a class="admin-btn admin-btn-sm admin-btn-secondary" href="<?= esc($calLink) ?>" aria-label="<?= esc(t('dy_open_calendar')) ?>"><?= admin_icon('calendar') ?><span class="adm-hide-sm"><?= esc(t('dy_open_calendar')) ?></span></a>
            <button type="button" class="admin-btn admin-btn-sm admin-btn-quiet-danger cancel-appointment" data-id="<?= (int)$appt['id'] ?>" aria-label="<?= esc(t('cancel') . ': ' . $other) ?>"><?= admin_icon('x') ?><span class="adm-hide-sm"><?= esc(t('cancel')) ?></span></button>
          </span>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </section>
  </div>

  <section class="adm-section" aria-labelledby="apptFindTitle">
    <div class="adm-section-head">
      <h3 class="adm-section-title" id="apptFindTitle"><?= esc(t('make_appointment')) ?></h3>
    </div>

    <?php if ($groupedAmptes): ?>
    <p class="adm-section-hint"><?= esc(t('adm_appt_find_hint')) ?></p>
    <div class="afsprake-tools">
      <label class="adm-search">
        <?= admin_icon('search') ?>
        <span class="admin-sr"><?= esc(t('search_offices')) ?></span>
        <input type="search" id="afsprakeSearch" class="admin-input" placeholder="<?= esc(t('search_offices')) ?>" autocomplete="off">
      </label>
      <?php if (count($groupedAmptes) > 1): ?>
      <div class="adm-chips" role="group" aria-label="<?= esc(t('role')) ?>">
        <button type="button" class="adm-chip adm-chip--btn" data-office="" aria-pressed="true"><?= esc(t('adm_filter_all')) ?></button>
        <?php foreach ($groupedAmptes as $groupAmpId => $ampList): ?>
        <button type="button" class="adm-chip adm-chip--btn" data-office="<?= (int)$groupAmpId ?>" aria-pressed="false"><?= esc(admin_office_group((int)$groupAmpId, $lang)) ?> <span class="adm-chip-n"><?= count($ampList) ?></span></button>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <div class="afsprake-container">
      <?php foreach ($groupedAmptes as $groupAmpId => $ampList): ?>
      <section class="afsprake-group" data-office="<?= (int)$groupAmpId ?>">
        <h4 class="adm-group-title"><?= esc(admin_office_group((int)$groupAmpId, $lang)) ?></h4>
        <ul class="adm-list">
          <?php foreach ($ampList as $amp):
            $label = admin_person($amp, $lang);
          ?>
          <li class="adm-list-row afsprake-card-person" data-id="<?= (int)$amp['id'] ?>" data-name="<?= esc(mb_strtolower($label . ' ' . ($amp['congregation'] ?? ''))) ?>">
            <?= admin_avatar($amp['photo'] ?? null, admin_full_name($amp)) ?>
            <span class="adm-list-text">
              <span class="adm-list-title"><?= esc($label) ?></span>
              <?php if (!empty($amp['congregation'])): ?><span class="adm-list-meta"><?= admin_icon('church') ?><?= esc($amp['congregation']) ?></span><?php endif; ?>
            </span>
            <span class="adm-list-actions">
              <a href="/profile/?u=<?= (int)$amp['id'] ?>" class="admin-btn admin-btn-sm admin-btn-icon" title="<?= esc(t('profile')) ?>" aria-label="<?= esc(t('profile') . ': ' . $label) ?>"><?= admin_icon('user') ?></a>
              <button type="button" class="admin-btn admin-btn-sm admin-btn-primary btn-make-appointment" data-id="<?= (int)$amp['id'] ?>" data-name="<?= esc($label) ?>" aria-label="<?= esc(t('make_appointment') . ': ' . $label) ?>"><?= admin_icon('plus') ?><span class="adm-hide-sm"><?= esc(t('make_appointment')) ?></span></button>
            </span>
          </li>
          <?php endforeach; ?>
        </ul>
      </section>
      <?php endforeach; ?>
      <p class="adm-muted-line" id="afsprakeNoMatch" hidden><?= esc(t('no_office_holders')) ?></p>
    </div>
    <?php else: ?>
    <p class="adm-muted-line"><?= esc(t('no_office_holders')) ?></p>
    <?php endif; ?>
  </section>
</div>

<!-- Ask for an appointment -->
<div id="appointmentModal" class="afsprake-layer" hidden
     <?php if ($makeAppointment && $targetUser): ?>
     data-open-id="<?= (int)$targetUser['id'] ?>"
     data-open-name="<?= esc(admin_person($targetUser, $lang)) ?>"
     data-open-date="<?= esc($presetDate) ?>"
     data-open-time="<?= esc($presetTime) ?>"
     <?php endif; ?>>
  <div class="afsprake-sheet" role="dialog" aria-modal="true" aria-labelledby="appointmentTitle">
    <div class="afsprake-sheet-head">
      <h3 id="appointmentTitle"><?= esc(t('make_appointment')) ?></h3>
      <button type="button" class="afsprake-modal-close" data-close aria-label="<?= esc(t('close')) ?>"><?= admin_icon('x') ?></button>
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
        <button type="submit" class="admin-btn admin-btn-primary"><?= admin_icon('send') ?><span><?= esc(t('send_request')) ?></span></button>
      </div>
    </form>
  </div>
</div>

<script>
  window.AFSPRAKE_LANG = <?= json_encode([
      'lang' => $lang,
      'today' => t('today'),
      'tomorrow' => t('tomorrow'),
      'requestSent' => t('adm_appt_sent'),
      'confirmed' => t('adm_appt_confirmed'),
      'cancelled' => t('adm_appt_cancelled'),
      'confirmCancel' => t('adm_appt_confirm_cancel'),
      'noMatch' => t('no_office_holders'),
      'pickDate' => t('adm_appt_pick'),
  ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="<?= admin_asset('/admin/js/afsprake.js') ?>"></script>
