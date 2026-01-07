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

$spouseRequests = [];
try {
    $spouseStmt = $pdo->prepare("SELECT sr.id, sr.requester_id, sr.receiver_id,
        u1.name AS req_name, u1.surname AS req_surname,
        u2.name AS rec_name, u2.surname AS rec_surname
        FROM spouse_requests sr
        JOIN users u1 ON u1.id = sr.requester_id
        JOIN users u2 ON u2.id = sr.receiver_id
        WHERE sr.status = 'pending'
        AND (u1.congregation_id = :cong_id OR u2.congregation_id = :cong_id2)
        ORDER BY sr.created_at DESC");
    $spouseStmt->execute([':cong_id' => $congId, ':cong_id2' => $congId]);
    $spouseRequests = $spouseStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $spouseRequests = [];
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
            <td><?= htmlspecialchars(trim(($user['name'] ?? '') . ' ' . ($user['surname'] ?? ''))) ?></td>
            <td><?= htmlspecialchars($user['amp'] ?? '') ?></td>
            <td><?= htmlspecialchars($user['congregation'] ?? '') ?></td>
            <td>
              <div class="approval-actions">
                <button class="admin-btn approve-btn" data-id="<?= (int)$user['id'] ?>">
                  ✓
                </button>
                <button class="admin-btn reject-btn" data-id="<?= (int)$user['id'] ?>">
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

  <h3 class="approval-subtitle" style="margin-top:40px"><?= t('spouse_requests') ?></h3>
  
  <?php if (empty($spouseRequests)): ?>
    <p class="admin-muted"><?= t('no_pending_spouse') ?></p>
  <?php else: ?>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th><?= t('requester') ?></th>
            <th><?= t('receiver') ?></th>
            <th><?= t('status') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($spouseRequests as $req): ?>
          <tr>
            <td><?= htmlspecialchars($req['req_name'] . ' ' . $req['req_surname']) ?></td>
            <td><?= htmlspecialchars($req['rec_name'] . ' ' . $req['rec_surname']) ?></td>
            <td>
              <span class="admin-badge admin-badge-warning"><?= t('pending') ?></span>
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
</div>

<style>
.approval-subtitle {
  font-size: 1.4rem;
  color: var(--color-rosegold-light);
  margin-bottom: 20px;
  font-weight: 400;
}

.approval-actions {
  display: flex;
  gap: 8px;
}

.admin-badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 500;
}

.admin-badge-warning {
  background: rgba(255, 193, 7, 0.2);
  border: 1px solid rgba(255, 193, 7, 0.5);
  color: #ffc107;
}
</style>