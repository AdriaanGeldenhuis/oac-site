<?php
/**
 * Approvals tab — members waiting for approval, spouse requests, linked
 * couples. Who sees whom comes from admin_approval_scope() /
 * admin_spouse_scope() (includes/admin_api.php), the rule the badge and
 * the approve / reject endpoints use too. admin.js does the clicking.
 *
 * Wide screens get a table; on phones every row becomes a small card
 * (name, office · congregation, then the buttons), see admin.css.
 */
$pending = [];
$scope = admin_approval_scope($currentUser);
if ($scope !== null) {
    try {
        [$where, $params] = admin_approval_where($scope, 'u');
        $stmt = $pdo->prepare("SELECT u.id, u.name, u.surname, u.email,
                CASE WHEN u.gender='vrou' THEN a.female_name ELSE a.male_name END AS amp,
                c.name AS congregation
                FROM users u
                LEFT JOIN amptes a ON a.id = u.amp_id
                LEFT JOIN congregations c ON c.id = u.congregation_id
                WHERE {$where}
                ORDER BY u.surname, u.name");
        $stmt->execute($params);
        $pending = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('Admin approvals list: ' . $e->getMessage());
    }
}

$spouseRequests = [];
$linkedCouples = [];
$spouseScope = admin_spouse_scope($currentUser);
if ($spouseScope !== null) {
    [$spouseSql, $spouseParams] = $spouseScope;

    // Housekeeping on its own: requests whose two members are already linked
    // are answered. If this ever fails, the list below still shows.
    try {
        $pdo->exec("UPDATE spouse_requests sr
            JOIN users u1 ON u1.id = sr.requester_id
            JOIN users u2 ON u2.id = sr.receiver_id
            SET sr.status = 'accepted', sr.updated_at = CURRENT_TIMESTAMP
            WHERE sr.status = 'pending'
            AND (u1.spouse_user_id = u2.id OR u2.spouse_user_id = u1.id)");
    } catch (Throwable $e) {
        error_log('Admin spouse request clean-up: ' . $e->getMessage());
    }

    try {
        $stmt = $pdo->prepare("SELECT sr.id,
            u1.name AS req_name, u1.surname AS req_surname,
            u2.name AS rec_name, u2.surname AS rec_surname
            FROM spouse_requests sr
            JOIN users u1 ON u1.id = sr.requester_id
            JOIN users u2 ON u2.id = sr.receiver_id
            WHERE sr.status = 'pending'
            AND {$spouseSql}
            AND u1.spouse_user_id IS NULL
            AND u2.spouse_user_id IS NULL
            ORDER BY sr.created_at DESC");
        $stmt->execute($spouseParams);
        $spouseRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('Admin spouse requests: ' . $e->getMessage());
    }

    try {
        // Each couple once
        $stmt = $pdo->prepare("SELECT
            u1.id AS a_id, u1.name AS a_name, u1.surname AS a_surname,
            u2.name AS b_name, u2.surname AS b_surname
            FROM users u1
            JOIN users u2 ON u2.id = u1.spouse_user_id AND u2.spouse_user_id = u1.id
            WHERE u1.id < u2.id
            AND {$spouseSql}
            ORDER BY u1.surname, u1.name");
        $stmt->execute($spouseParams);
        $linkedCouples = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('Admin linked couples: ' . $e->getMessage());
    }
}

// The small stroke icons on the buttons
$iconYes = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 6L9 17l-5-5"/></svg>';
$iconNo = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 6L6 18M6 6l12 12"/></svg>';
$iconUnlink = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18.84 12.25l1.72-1.71a5 5 0 00-7.07-7.07l-1.72 1.71M5.17 11.75l-1.72 1.71a5 5 0 007.07 7.07l1.72-1.71M8 2v3M2 8h3M16 19v3M19 16h3"/></svg>';
?>
<div class="admin-section" id="approvalsRoot">
  <div class="admin-section-header">
    <div class="admin-icon-wrapper">
      <svg class="admin-icon" viewBox="0 0 24 24" aria-hidden="true">
        <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/>
        <path d="M22 4L12 14.01l-3-3"/>
      </svg>
    </div>
    <h2 class="admin-section-title"><?= esc(t('approvals')) ?></h2>
  </div>

  <section data-group="users">
    <h3 class="approval-subtitle">
      <span><?= esc(t('user_approvals')) ?><?php if (!empty($pending)): ?><span class="admin-count" data-count><?= count($pending) ?></span><?php endif; ?></span>
    </h3>

    <?php if (empty($pending)): ?>
      <p class="admin-muted"><?= esc(t('no_pending_approvals')) ?></p>
    <?php else: ?>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th scope="col"><?= esc(t('name')) ?></th>
              <th scope="col"><?= esc(t('role')) ?></th>
              <th scope="col"><?= esc(t('congregation')) ?></th>
              <th scope="col"><span class="admin-sr"><?= esc(t('actions')) ?></span></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($pending as $user):
              $userName = trim(($user['name'] ?? '') . ' ' . ($user['surname'] ?? ''));
            ?>
            <tr id="row-<?= (int)$user['id'] ?>" class="ap-row">
              <td class="ap-person">
                <strong class="ap-name"><?= esc($userName) ?></strong>
                <?php if (!empty($user['email'])): ?><span class="ap-sub"><?= esc($user['email']) ?></span><?php endif; ?>
              </td>
              <td class="ap-meta"><?= esc($user['amp'] ?? '') ?></td>
              <td class="ap-meta"><?= esc($user['congregation'] ?? '') ?></td>
              <td class="ap-actions">
                <div class="approval-actions">
                  <button type="button" class="approve-btn" data-id="<?= (int)$user['id'] ?>" aria-label="<?= esc(t('approve') . ': ' . $userName) ?>"><?= $iconYes ?><span><?= esc(t('approve')) ?></span></button>
                  <button type="button" class="reject-btn" data-id="<?= (int)$user['id'] ?>" aria-label="<?= esc(t('reject') . ': ' . $userName) ?>"><?= $iconNo ?><span><?= esc(t('reject')) ?></span></button>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <?php if (!empty($spouseRequests)): ?>
  <section data-group="spouse">
    <h3 class="approval-subtitle approval-subtitle--gap">
      <span><?= esc(t('spouse_requests')) ?><span class="admin-count" data-count><?= count($spouseRequests) ?></span></span>
    </h3>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th scope="col"><?= esc(t('requester')) ?></th>
            <th scope="col"><?= esc(t('receiver')) ?></th>
            <th scope="col"><span class="admin-sr"><?= esc(t('actions')) ?></span></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($spouseRequests as $req):
            $pair = trim($req['req_name'] . ' ' . $req['req_surname']) . ' & ' . trim($req['rec_name'] . ' ' . $req['rec_surname']);
          ?>
          <tr id="spouse-row-<?= (int)$req['id'] ?>" class="ap-row ap-row--pair">
            <td class="ap-person"><strong class="ap-name"><?= esc(trim($req['req_name'] . ' ' . $req['req_surname'])) ?></strong></td>
            <td class="ap-person ap-partner"><strong class="ap-name"><?= esc(trim($req['rec_name'] . ' ' . $req['rec_surname'])) ?></strong></td>
            <td class="ap-actions">
              <div class="approval-actions">
                <button type="button" class="spouse-approve-btn" data-id="<?= (int)$req['id'] ?>" aria-label="<?= esc(t('approve') . ': ' . $pair) ?>"><?= $iconYes ?><span><?= esc(t('approve')) ?></span></button>
                <button type="button" class="spouse-reject-btn" data-id="<?= (int)$req['id'] ?>" aria-label="<?= esc(t('reject') . ': ' . $pair) ?>"><?= $iconNo ?><span><?= esc(t('reject')) ?></span></button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="admin-hint"><?= esc(t('spouse_auto_approve_note')) ?></p>
  </section>
  <?php endif; ?>

  <?php if (!empty($linkedCouples)): ?>
  <section data-group="couples">
    <h3 class="approval-subtitle approval-subtitle--gap">
      <span><?= esc(t('linked_spouses')) ?><span class="admin-count" data-count><?= count($linkedCouples) ?></span></span>
    </h3>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th scope="col"><?= esc(t('name')) ?></th>
            <th scope="col"><?= esc(t('spouse')) ?></th>
            <th scope="col"><span class="admin-sr"><?= esc(t('actions')) ?></span></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($linkedCouples as $couple):
            $pair = trim($couple['a_name'] . ' ' . $couple['a_surname']) . ' & ' . trim($couple['b_name'] . ' ' . $couple['b_surname']);
          ?>
          <tr id="couple-row-<?= (int)$couple['a_id'] ?>" class="ap-row ap-row--pair">
            <td class="ap-person"><strong class="ap-name"><?= esc(trim($couple['a_name'] . ' ' . $couple['a_surname'])) ?></strong></td>
            <td class="ap-person ap-partner"><strong class="ap-name"><?= esc(trim($couple['b_name'] . ' ' . $couple['b_surname'])) ?></strong></td>
            <td class="ap-actions">
              <div class="approval-actions">
                <button type="button" class="spouse-unlink-btn" data-id="<?= (int)$couple['a_id'] ?>" aria-label="<?= esc(t('unlink') . ': ' . $pair) ?>"><?= $iconUnlink ?><span><?= esc(t('unlink')) ?></span></button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
  <?php endif; ?>
</div>
