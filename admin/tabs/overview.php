<?php
/**
 * Overview — the admin's start page. First what waits for you (each kind
 * of work as one big card that takes you straight to it), then the tools
 * you may use with a line of status each, then what is coming up.
 * The numbers are $counts (admin_pending_counts(), index.php); the names
 * under them come from the same scope rules as the tabs themselves.
 */
$tz = new DateTimeZone('Africa/Johannesburg');
$hour = (int)(new DateTimeImmutable('now', $tz))->format('G');
$greeting = t($hour < 12 ? 'adm_greet_morning' : ($hour < 18 ? 'adm_greet_afternoon' : 'adm_greet_evening'));

/** "{n} …" with the one / many line */
$countLine = static function (int $n, string $one, string $many): string {
    return $n === 1 ? t($one) : str_replace('{n}', (string)$n, t($many));
};

/** "A · B · C en 2 meer" from labels already in the member's language */
$nameList = static function (array $labels, int $total): string {
    $line = implode(' · ', $labels);
    if ($total > count($labels)) {
        $line .= ' ' . str_replace('{n}', (string)($total - count($labels)), t('adm_and_more'));
    }
    return $line;
};

// ----- The first few names under each count -----
$previewUsers = [];
if ($counts['users'] > 0 && ($scope = admin_approval_scope($currentUser)) !== null) {
    try {
        [$where, $params] = admin_approval_where($scope, 'u');
        $st = $pdo->prepare("SELECT u.name, u.surname, u.amp_id, u.gender FROM users u WHERE {$where} ORDER BY u.surname, u.name LIMIT 3");
        $st->execute($params);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $previewUsers[] = admin_person($row, $lang);
        }
    } catch (Throwable $e) {
        error_log('Admin overview users: ' . $e->getMessage());
    }
}

$previewSpouse = [];
if ($counts['spouse'] > 0 && ($spouseScope = admin_spouse_scope($currentUser)) !== null) {
    try {
        [$spouseSql, $spouseParams] = $spouseScope;
        $st = $pdo->prepare("SELECT u1.name AS a_name, u1.surname AS a_surname, u1.amp_id AS a_amp_id, u1.gender AS a_gender,
            u2.name AS b_name, u2.surname AS b_surname, u2.amp_id AS b_amp_id, u2.gender AS b_gender
            FROM spouse_requests sr
            JOIN users u1 ON u1.id = sr.requester_id
            JOIN users u2 ON u2.id = sr.receiver_id
            WHERE sr.status = 'pending' AND {$spouseSql}
            AND u1.spouse_user_id IS NULL AND u2.spouse_user_id IS NULL
            ORDER BY sr.created_at DESC LIMIT 2");
        $st->execute($spouseParams);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $previewSpouse[] = admin_person($row, $lang, 'a_') . ' & ' . admin_person($row, $lang, 'b_');
        }
    } catch (Throwable $e) {
        error_log('Admin overview spouse: ' . $e->getMessage());
    }
}

$previewAppts = [];
if ($counts['appointments'] > 0) {
    try {
        $st = $pdo->prepare("SELECT aa.id, aa.appointment_date,
            u.name, u.surname, u.amp_id, u.gender
            FROM ampte_appointments aa
            JOIN users u ON u.id = aa.requester_id
            WHERE aa.target_user_id = ? AND aa.status = 'pending' AND aa.appointment_date >= CURDATE()
            ORDER BY aa.appointment_date ASC, aa.appointment_time ASC LIMIT 3");
        $st->execute([$userId]);
        $previewAppts = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('Admin overview appointments: ' . $e->getMessage());
    }
}
// Office bearers answer on the Appointments tab; everyone else in the calendar
$apptHref = $canManageAmpte ? '?tab=afsprake#afsprakePending' : '/calendar/calendar.php';
if (!$canManageAmpte && !empty($previewAppts[0])) {
    $apptHref = '/calendar/calendar.php#d=' . rawurlencode((string)$previewAppts[0]['appointment_date'])
        . '&i=' . rawurlencode('appointment-' . (int)$previewAppts[0]['id']);
}

// A spouse request made to me
$askedMe = null;
if ($counts['mine'] > 0) {
    try {
        $st = $pdo->prepare("SELECT sr.id, u.name, u.surname, u.amp_id, u.gender
            FROM spouse_requests sr JOIN users u ON u.id = sr.requester_id
            WHERE sr.receiver_id = ? AND sr.status = 'pending'
            ORDER BY sr.created_at DESC LIMIT 1");
        $st->execute([$userId]);
        $askedMe = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {
        error_log('Admin overview own spouse request: ' . $e->getMessage());
    }
}

// ----- Coming up: my next confirmed appointments -----
$upcoming = [];
try {
    $st = $pdo->prepare("SELECT aa.id, aa.requester_id, aa.appointment_date, aa.appointment_time, aa.location,
        r.name AS r_name, r.surname AS r_surname, r.amp_id AS r_amp_id, r.gender AS r_gender,
        tu.name AS t_name, tu.surname AS t_surname, tu.amp_id AS t_amp_id, tu.gender AS t_gender
        FROM ampte_appointments aa
        JOIN users r ON r.id = aa.requester_id
        JOIN users tu ON tu.id = aa.target_user_id
        WHERE aa.appointment_date >= CURDATE() AND aa.status = 'confirmed'
        AND (aa.requester_id = ? OR aa.target_user_id = ?)
        ORDER BY aa.appointment_date ASC, aa.appointment_time ASC
        LIMIT 3");
    $st->execute([$userId, $userId]);
    $upcoming = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Admin overview upcoming: ' . $e->getMessage());
}

// ----- The elders' two tools: teaching languages, and the coming week of thoughts -----
$teach = null;
$week = [];
if ($isElder) {
    $teach = admin_teaching_status((string)($currentUser['province_name'] ?? ''), (string)($currentUser['town_name'] ?? ''));

    require_once dirname(__DIR__, 2) . '/includes/daily_thoughts.php';
    $day = dt_now()->setTime(0, 0);
    for ($i = 0; $i < 7; $i++) {
        $week[$day->modify('+' . $i . ' days')->format('Y-m-d')] = false;
    }
    try {
        $dates = dt_run($pdo, 'SELECT display_date FROM daily_thoughts WHERE display_date BETWEEN ? AND ?', [array_key_first($week), array_key_last($week)])
            ->fetchAll(PDO::FETCH_COLUMN, 0);
        foreach ($dates as $d) {
            $d = substr((string)$d, 0, 10);
            if (isset($week[$d])) {
                $week[$d] = true;
            }
        }
    } catch (Throwable $e) {
        error_log('Admin overview thoughts: ' . $e->getMessage());
    }
}

$apptWith = static function (string $name): string {
    return admin_fill_html(t('cal_appt_with'), ['name' => '<strong>' . esc($name) . '</strong>']);
};
?>
<section class="adm-hello" aria-labelledby="admHello">
  <?= admin_avatar($currentUser['photo'] ?? null, $meName, 'adm-avatar adm-avatar--lg') ?>
  <div class="adm-hello-text">
    <p class="adm-kicker"><?= admin_icon('shield') ?><span><?= esc(t('adm_eyebrow')) ?></span></p>
    <h2 class="adm-hello-title" id="admHello"><span><?= esc($greeting) ?>,</span> <?= esc($meLabel) ?></h2>
    <?php if (!empty($currentUser['congregation_name'])): ?>
    <p class="adm-hello-sub"><?= admin_icon('church') ?><?= esc($currentUser['congregation_name']) ?><?= !empty($currentUser['town_name']) ? ' · ' . esc($currentUser['town_name']) : '' ?></p>
    <?php endif; ?>
  </div>
</section>

<section class="adm-section" aria-labelledby="admWaiting">
  <div class="adm-section-head">
    <h3 class="adm-section-title" id="admWaiting"><?= esc(t('adm_waiting_title')) ?></h3>
    <?php if ($counts['total'] > 0): ?><span class="adm-badge adm-badge--static"><?= (int)$counts['total'] ?></span><?php endif; ?>
  </div>

  <?php if ($counts['total'] === 0): ?>
  <div class="adm-clear">
    <span class="adm-clear-icon"><?= admin_icon('check') ?></span>
    <div>
      <p class="adm-clear-title"><?= esc(t('adm_all_clear')) ?></p>
      <p class="adm-clear-sub"><?= esc(t('adm_all_clear_sub')) ?></p>
    </div>
  </div>
  <?php else: ?>
  <div class="adm-tasks">
    <?php if ($askedMe): ?>
    <article class="adm-task adm-task--spouse">
      <span class="adm-task-icon"><?= admin_icon('heart') ?></span>
      <div class="adm-task-body">
        <p class="adm-task-title"><strong><?= esc(admin_person($askedMe, $lang)) ?></strong> <?= esc(t('wants_to_marry_you')) ?></p>
        <div class="adm-task-actions">
          <button type="button" class="admin-btn admin-btn-ok" data-spouse="accept" data-id="<?= (int)$askedMe['id'] ?>"><?= admin_icon('check') ?><span><?= esc(t('accept')) ?></span></button>
          <button type="button" class="admin-btn admin-btn-quiet-danger" data-spouse="reject" data-id="<?= (int)$askedMe['id'] ?>"><?= admin_icon('x') ?><span><?= esc(t('reject')) ?></span></button>
        </div>
      </div>
    </article>
    <?php endif; ?>

    <?php if ($counts['users'] > 0): ?>
    <a class="adm-task" href="?tab=approvals#users">
      <span class="adm-task-icon"><?= admin_icon('users') ?></span>
      <span class="adm-task-body">
        <span class="adm-task-title"><?= esc($countLine($counts['users'], 'adm_wait_users_one', 'adm_wait_users_many')) ?></span>
        <?php if ($previewUsers): ?><span class="adm-task-names"><?= esc($nameList($previewUsers, $counts['users'])) ?></span><?php endif; ?>
      </span>
      <span class="adm-task-go"><span><?= esc(t('adm_handle_now')) ?></span><?= admin_icon('chevron') ?></span>
    </a>
    <?php endif; ?>

    <?php if ($counts['spouse'] > 0): ?>
    <a class="adm-task" href="?tab=approvals#spouse">
      <span class="adm-task-icon"><?= admin_icon('heart') ?></span>
      <span class="adm-task-body">
        <span class="adm-task-title"><?= esc($countLine($counts['spouse'], 'adm_wait_spouse_one', 'adm_wait_spouse_many')) ?></span>
        <?php if ($previewSpouse): ?><span class="adm-task-names"><?= esc($nameList($previewSpouse, $counts['spouse'])) ?></span><?php endif; ?>
      </span>
      <span class="adm-task-go"><span><?= esc(t('adm_handle_now')) ?></span><?= admin_icon('chevron') ?></span>
    </a>
    <?php endif; ?>

    <?php if ($counts['appointments'] > 0): ?>
    <a class="adm-task" href="<?= esc($apptHref) ?>">
      <span class="adm-task-icon"><?= admin_icon('calendar') ?></span>
      <span class="adm-task-body">
        <span class="adm-task-title"><?= esc($countLine($counts['appointments'], 'adm_wait_appts_one', 'adm_wait_appts_many')) ?></span>
        <?php if ($previewAppts): ?><span class="adm-task-names"><?= esc($nameList(array_map(static fn($r) => admin_person($r, $lang), $previewAppts), $counts['appointments'])) ?></span><?php endif; ?>
      </span>
      <span class="adm-task-go"><span><?= esc(t('adm_answer')) ?></span><?= admin_icon('chevron') ?></span>
    </a>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</section>

<section class="adm-section" aria-labelledby="admTools">
  <div class="adm-section-head">
    <h3 class="adm-section-title" id="admTools"><?= esc(t('adm_tools')) ?></h3>
  </div>
  <div class="adm-tools">
    <?php if ($canApprove): ?>
    <a class="adm-tool" href="?tab=approvals">
      <span class="adm-tool-icon"><?= admin_icon('approvals') ?></span>
      <span class="adm-tool-name"><?= esc(t('approvals')) ?></span>
      <span class="adm-tool-desc"><?= esc(t('adm_tool_approvals')) ?></span>
      <?php if ($counts['approvals'] > 0): ?>
      <span class="adm-tool-status adm-tool-status--alert"><?= esc(str_replace('{n}', (string)$counts['approvals'], t('adm_waiting_aria'))) ?></span>
      <?php endif; ?>
    </a>
    <?php endif; ?>

    <?php if ($canManageAmpte): ?>
    <a class="adm-tool" href="?tab=afsprake">
      <span class="adm-tool-icon"><?= admin_icon('calendar') ?></span>
      <span class="adm-tool-name"><?= esc(t('appointments')) ?></span>
      <span class="adm-tool-desc"><?= esc(t('adm_tool_afsprake')) ?></span>
      <?php if ($counts['appointments'] > 0): ?>
      <span class="adm-tool-status adm-tool-status--alert"><?= esc(str_replace('{n}', (string)$counts['appointments'], t('adm_waiting_aria'))) ?></span>
      <?php endif; ?>
    </a>
    <?php endif; ?>

    <?php if ($isElder && $teach): ?>
    <a class="adm-tool" href="?tab=teaching">
      <span class="adm-tool-icon"><?= admin_icon('teaching') ?></span>
      <span class="adm-tool-name"><?= esc(t('monthly_teaching')) ?></span>
      <span class="adm-tool-desc"><?= esc(str_replace(['{n}', '{total}'], [(string)$teach['done'], (string)$teach['total']], t('adm_teach_progress'))) ?></span>
      <span class="adm-meter" aria-hidden="true"><i style="width: <?= $teach['total'] ? (int)round($teach['done'] / $teach['total'] * 100) : 0 ?>%"></i></span>
    </a>

    <a class="adm-tool" href="?tab=gedagte">
      <span class="adm-tool-icon"><?= admin_icon('thought') ?></span>
      <span class="adm-tool-name"><?= esc(t('daily_thought')) ?></span>
      <span class="adm-tool-desc"><?= esc(str_replace('{n}', (string)count(array_filter($week)), t('adm_thoughts_week'))) ?></span>
      <span class="adm-week" aria-hidden="true">
        <?php foreach ($week as $date => $has): ?>
        <i class="<?= $has ? 'is-set' : '' ?>"><?= (int)substr($date, 8, 2) ?></i>
        <?php endforeach; ?>
      </span>
    </a>
    <?php endif; ?>

    <a class="adm-tool" href="?tab=profile">
      <span class="adm-tool-icon"><?= admin_icon('user') ?></span>
      <span class="adm-tool-name"><?= esc(t('adm_nav_account')) ?></span>
      <span class="adm-tool-desc"><?= esc(t('adm_tool_account')) ?></span>
    </a>
  </div>
</section>

<?php if ($upcoming): ?>
<section class="adm-section" aria-labelledby="admUpcoming">
  <div class="adm-section-head">
    <h3 class="adm-section-title" id="admUpcoming"><?= esc(t('adm_appt_upcoming')) ?></h3>
    <a class="adm-section-link" href="<?= $canManageAmpte ? '?tab=afsprake' : '/calendar/calendar.php' ?>"><?= esc(t('adm_see_all')) ?><?= admin_icon('chevron') ?></a>
  </div>
  <ul class="adm-list">
    <?php foreach ($upcoming as $appt):
      $other = (int)$appt['requester_id'] === $userId ? admin_person($appt, $lang, 't_') : admin_person($appt, $lang, 'r_');
      $calLink = '/calendar/calendar.php#d=' . rawurlencode((string)$appt['appointment_date']) . '&i=' . rawurlencode('appointment-' . (int)$appt['id']);
    ?>
    <li>
      <a class="adm-list-row" href="<?= esc($calLink) ?>">
        <span class="adm-date-chip"><time data-date="<?= esc($appt['appointment_date']) ?>" data-style="chip" datetime="<?= esc($appt['appointment_date']) ?>"><?= esc($appt['appointment_date']) ?></time></span>
        <span class="adm-list-text">
          <span class="adm-list-title"><?= $apptWith($other) ?></span>
          <span class="adm-list-meta"><?= admin_icon('clock') ?><?= esc(substr((string)$appt['appointment_time'], 0, 5)) ?><?php if (!empty($appt['location'])): ?> · <?= esc($appt['location']) ?><?php endif; ?></span>
        </span>
        <?= admin_icon('chevron', 'adm-ico adm-list-go') ?>
      </a>
    </li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>
