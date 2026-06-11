<?php
$ampId = (int)($currentUser['amp_id'] ?? 0);
$townId = $currentUser['town_id'] ?? null;
$congId = $currentUser['congregation_id'] ?? null;

$roleFilter = '';
$scopeFilter = '';
$params = [];

if ($ampId === 6) {
    $roleFilter = 'AND u.amp_id IN (7,8,9,10)';
    if ($congId) {
        $scopeFilter = 'AND u.congregation_id = :cid';
        $params[':cid'] = $congId;
    }
} elseif ($ampId === 5) {
    $roleFilter = 'AND u.amp_id IN (5,6)';
    if ($townId) {
        $scopeFilter = 'AND u.town_id = :tid';
        $params[':tid'] = $townId;
    }
} elseif ($ampId >= 2 && $ampId <= 4) {
    $roleFilter = 'AND u.amp_id IN (2,3,4,5)';
    if ($townId) {
        $scopeFilter = 'AND u.town_id = :tid';
        $params[':tid'] = $townId;
    }
} elseif ($ampId === 1) {
    $roleFilter = 'AND u.amp_id = 1';
}

$pending = [];
if ($roleFilter) {
    try {
        $sql = "SELECT u.id, u.name, u.surname, u.email, 
                CASE WHEN u.gender='vrou' THEN a.female_name ELSE a.male_name END AS amp,
                c.name AS congregation
                FROM users u
                LEFT JOIN amptes a ON a.id = u.amp_id
                LEFT JOIN congregations c ON c.id = u.congregation_id
                WHERE u.status = 'pending' {$roleFilter} {$scopeFilter}
                ORDER BY u.surname, u.name";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $pending = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $pending = [];
    }
}

// Spouse scope: priests see their congregation, elders and higher offices
// without a congregation fall back to their town
$spouseScope = '';
$spouseParams = [];
if ($congId) {
    $spouseScope = '(u1.congregation_id = :scope_a OR u2.congregation_id = :scope_b)';
    $spouseParams = [':scope_a' => $congId, ':scope_b' => $congId];
} elseif ($townId) {
    $spouseScope = '(u1.town_id = :scope_a OR u2.town_id = :scope_b)';
    $spouseParams = [':scope_a' => $townId, ':scope_b' => $townId];
}

$spouseRequests = [];
if ($spouseScope) {
    try {
        // Clean up stale pending requests where users are already connected
        $cleanupStmt = $pdo->prepare("UPDATE spouse_requests sr
            JOIN users u1 ON u1.id = sr.requester_id
            JOIN users u2 ON u2.id = sr.receiver_id
            SET sr.status = 'accepted', sr.updated_at = CURRENT_TIMESTAMP
            WHERE sr.status = 'pending'
            AND (u1.spouse_user_id = u2.id OR u2.spouse_user_id = u1.id)");
        $cleanupStmt->execute();

        // Only show pending requests where users are NOT already connected as spouses
        $spouseStmt = $pdo->prepare("SELECT sr.id, sr.requester_id, sr.receiver_id,
            u1.name AS req_name, u1.surname AS req_surname,
            u2.name AS rec_name, u2.surname AS rec_surname
            FROM spouse_requests sr
            JOIN users u1 ON u1.id = sr.requester_id
            JOIN users u2 ON u2.id = sr.receiver_id
            WHERE sr.status = 'pending'
            AND {$spouseScope}
            AND u1.spouse_user_id IS NULL
            AND u2.spouse_user_id IS NULL
            ORDER BY sr.created_at DESC");
        $spouseStmt->execute($spouseParams);
        $spouseRequests = $spouseStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $spouseRequests = [];
    }
}

// Linked couples in the same scope (each couple listed once)
$linkedCouples = [];
if ($spouseScope) {
    try {
        $coupleStmt = $pdo->prepare("SELECT
            u1.id AS a_id, u1.name AS a_name, u1.surname AS a_surname,
            u2.id AS b_id, u2.name AS b_name, u2.surname AS b_surname
            FROM users u1
            JOIN users u2 ON u2.id = u1.spouse_user_id AND u2.spouse_user_id = u1.id
            WHERE u1.id < u2.id
            AND {$spouseScope}
            ORDER BY u1.surname, u1.name");
        $coupleStmt->execute($spouseParams);
        $linkedCouples = $coupleStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $linkedCouples = [];
    }
}
?>
<div class="admin-section admin-stack">
  <div class="admin-section-header">
    <div class="admin-icon-wrapper">
      <svg class="admin-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        <polyline points="22 4 12 14.01 9 11.01" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </div>
    <h2 class="admin-section-title"><?= t('approvals') ?></h2>
  </div>

  <h3 class="approval-subtitle"><?= t('user_approvals') ?></h3>
  
  <?php if (empty($pending)): ?>
    <p class="admin-muted"><?= t('no_pending_approvals') ?></p>
  <?php else: ?>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th><?= t('name') ?></th>
            <th><?= t('role') ?></th>
            <th><?= t('congregation') ?></th>
            <th><?= t('actions') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pending as $user): ?>
          <tr id="row-<?= (int)$user['id'] ?>">
            <td data-label="<?= esc(t('name')) ?>"><?= htmlspecialchars(trim(($user['name'] ?? '') . ' ' . ($user['surname'] ?? ''))) ?></td>
            <td data-label="<?= esc(t('role')) ?>"><?= htmlspecialchars($user['amp'] ?? '') ?></td>
            <td data-label="<?= esc(t('congregation')) ?>"><?= htmlspecialchars($user['congregation'] ?? '') ?></td>
            <td data-label="">
              <div class="approval-actions">
                <button class="approve-btn" data-id="<?= (int)$user['id'] ?>" title="<?= esc(t('approve')) ?>">
                  ✓
                </button>
                <button class="reject-btn" data-id="<?= (int)$user['id'] ?>" title="<?= esc(t('reject')) ?>">
                  ✗
                </button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <?php if (!empty($spouseRequests)): ?>
  <h3 class="approval-subtitle" style="margin-top:40px"><?= t('spouse_requests') ?></h3>

  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr>
          <th><?= t('requester') ?></th>
          <th><?= t('receiver') ?></th>
          <th><?= t('actions') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($spouseRequests as $req): ?>
        <tr id="spouse-row-<?= (int)$req['id'] ?>">
          <td data-label="<?= esc(t('requester')) ?>"><?= htmlspecialchars($req['req_name'] . ' ' . $req['req_surname']) ?></td>
          <td data-label="<?= esc(t('receiver')) ?>"><?= htmlspecialchars($req['rec_name'] . ' ' . $req['rec_surname']) ?></td>
          <td data-label="">
            <div class="approval-actions">
              <button class="spouse-approve-btn" data-id="<?= (int)$req['id'] ?>" title="<?= esc(t('approve')) ?>">
                ✓
              </button>
              <button class="spouse-reject-btn" data-id="<?= (int)$req['id'] ?>" title="<?= esc(t('reject')) ?>">
                ✗
              </button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="admin-muted" style="margin-top:10px">
    <?= t('spouse_auto_approve_note') ?>
  </p>
  <?php endif; ?>

  <?php if (!empty($linkedCouples)): ?>
  <h3 class="approval-subtitle" style="margin-top:40px"><?= t('linked_spouses') ?></h3>

  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr>
          <th><?= t('name') ?></th>
          <th><?= t('spouse') ?></th>
          <th><?= t('actions') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($linkedCouples as $couple): ?>
        <tr id="couple-row-<?= (int)$couple['a_id'] ?>">
          <td data-label="<?= esc(t('name')) ?>"><?= htmlspecialchars(trim($couple['a_name'] . ' ' . $couple['a_surname'])) ?></td>
          <td data-label="<?= esc(t('spouse')) ?>"><?= htmlspecialchars(trim($couple['b_name'] . ' ' . $couple['b_surname'])) ?></td>
          <td data-label="">
            <div class="approval-actions">
              <button class="spouse-unlink-btn" data-id="<?= (int)$couple['a_id'] ?>"
                      title="<?= esc(t('unlink')) ?>">
                <svg viewBox="0 0 24 24" fill="none" width="16" height="16"><path d="M18.84 12.25l1.72-1.71a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M5.17 11.75l-1.72 1.71a5 5 0 0 0 7.07 7.07l1.72-1.71"/><line x1="8" y1="2" x2="8" y2="5"/><line x1="2" y1="8" x2="5" y2="8"/><line x1="16" y1="19" x2="16" y2="22"/><line x1="19" y1="16" x2="22" y2="16"/></svg>
                <?= t('unlink') ?>
              </button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<script>
  window.OAC_APPROVALS_LANG = {
    confirmUnlink: <?= json_encode(t('confirm_unlink_spouse')) ?>
  };
</script>