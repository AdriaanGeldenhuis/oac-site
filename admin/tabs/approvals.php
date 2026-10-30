<?php
/**
 * Approvals tab — members waiting for approval, spouse requests, linked
 * couples. Who sees whom comes from admin_approval_scope() /
 * admin_spouse_scope() (includes/admin_api.php), the rule the counts and
 * the approve / reject endpoints use too; the box at the top only explains
 * that rule, it decides nothing. admin.js does the clicking.
 * Every person is one card: office first ("Oudste Kobus Nel"), where they
 * belong, when they registered, then one clear main button.
 */
$scope = admin_approval_scope($currentUser);

$pending = [];
if ($scope !== null) {
    try {
        [$where, $params] = admin_approval_where($scope, 'u');
        $stmt = $pdo->prepare("SELECT u.id, u.name, u.surname, u.email, u.phone, u.photo, u.amp_id, u.gender, u.created_at,
                c.name AS congregation
                FROM users u
                LEFT JOIN congregations c ON c.id = u.congregation_id
                WHERE {$where}
                ORDER BY u.surname, u.name");
        $stmt->execute($params);
        $pending = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('Admin approvals list: ' . $e->getMessage());
    }
}

// The offices and the area this member approves (the same scope as above, in words)
$scopeOffices = [];
$scopeArea = '';
if ($scope !== null) {
    foreach ($scope['roles'] as $roleId) {
        $scopeOffices[] = admin_office_group($roleId, $lang);
    }
    if ($scope['col'] === null) {
        $scopeArea = t('adm_scope_everywhere');
    } elseif ($scope['col'] === 'congregation_id') {
        $scopeArea = (string)($currentUser['congregation_name'] ?? '');
    } else {
        $scopeArea = (string)($currentUser['town_name'] ?? '');
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
        $stmt = $pdo->prepare("SELECT sr.id, sr.created_at,
            u1.name AS a_name, u1.surname AS a_surname, u1.amp_id AS a_amp_id, u1.gender AS a_gender,
            u2.name AS b_name, u2.surname AS b_surname, u2.amp_id AS b_amp_id, u2.gender AS b_gender,
            c.name AS congregation
            FROM spouse_requests sr
            JOIN users u1 ON u1.id = sr.requester_id
            JOIN users u2 ON u2.id = sr.receiver_id
            LEFT JOIN congregations c ON c.id = u1.congregation_id
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
            u1.id AS a_id, u1.name AS a_name, u1.surname AS a_surname, u1.amp_id AS a_amp_id, u1.gender AS a_gender,
            u2.name AS b_name, u2.surname AS b_surname, u2.amp_id AS b_amp_id, u2.gender AS b_gender
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
?>
<div id="approvalsRoot">

  <?php if ($scope === null): ?>
  <div class="adm-note adm-note--warn" role="note">
    <?= admin_icon('shield') ?>
    <p><?= esc(t('adm_scope_none')) ?> <a href="/admin/account.php"><?= esc(t('edit_profile')) ?></a></p>
  </div>
  <?php else: ?>
  <dl class="adm-scope">
    <div>
      <dt><?= esc(t('adm_scope_offices')) ?></dt>
      <dd><?php foreach ($scopeOffices as $office): ?><span class="adm-chip"><?= esc($office) ?></span><?php endforeach; ?></dd>
    </div>
    <?php if ($scopeArea !== ''): ?>
    <div>
      <dt><?= esc(t('adm_scope_area')) ?></dt>
      <dd><span class="adm-chip adm-chip--place"><?= admin_icon($scope['col'] === null ? 'globe' : 'pin') ?><?= esc($scopeArea) ?></span></dd>
    </div>
    <?php endif; ?>
  </dl>
  <?php endif; ?>

  <section class="adm-section" id="users" data-group="users" aria-labelledby="apUsers">
    <div class="adm-section-head">
      <h3 class="adm-section-title" id="apUsers"><?= esc(t('user_approvals')) ?></h3>
      <?php if ($pending): ?><span class="adm-badge adm-badge--static" data-count><?= count($pending) ?></span><?php endif; ?>
    </div>

    <?php if (!$pending): ?>
    <div class="adm-empty" data-empty>
      <span class="adm-empty-icon"><?= admin_icon('check') ?></span>
      <p><?= esc(t('no_pending_approvals')) ?></p>
    </div>
    <?php else: ?>
    <div class="adm-cards" data-list>
      <?php foreach ($pending as $user):
        $label = admin_person($user, $lang);
        $ago = admin_ago_html($user['created_at'] ?? null, $lang);
      ?>
      <article class="adm-card adm-person" id="row-<?= (int)$user['id'] ?>" data-row>
        <div class="adm-person-main">
          <?= admin_avatar($user['photo'] ?? null, admin_full_name($user)) ?>
          <div class="adm-person-text">
            <h4 class="adm-person-name"><?= esc($label) ?></h4>
            <p class="adm-person-meta">
              <?php if (!empty($user['congregation'])): ?><span><?= admin_icon('church') ?><?= esc($user['congregation']) ?></span><?php endif; ?>
              <?php if ($ago !== ''): ?><span><?= admin_icon('clock') ?><?= admin_fill_html(t('adm_registered'), ['ago' => $ago]) ?></span><?php endif; ?>
            </p>
            <p class="adm-person-meta adm-person-contact">
              <?php if (!empty($user['email'])): ?><a href="mailto:<?= esc($user['email']) ?>"><?= admin_icon('mail') ?><?= esc($user['email']) ?></a><?php endif; ?>
              <?php if (!empty($user['phone'])): ?><a href="tel:<?= esc(preg_replace('/[^\d+]/', '', (string)$user['phone'])) ?>"><?= admin_icon('phone') ?><?= esc($user['phone']) ?></a><?php endif; ?>
            </p>
          </div>
        </div>
        <div class="adm-person-actions">
          <button type="button" class="admin-btn admin-btn-ok approve-btn" data-id="<?= (int)$user['id'] ?>" aria-label="<?= esc(t('approve') . ': ' . $label) ?>"><?= admin_icon('check') ?><span><?= esc(t('approve')) ?></span></button>
          <button type="button" class="admin-btn admin-btn-quiet-danger reject-btn" data-id="<?= (int)$user['id'] ?>" aria-label="<?= esc(t('reject') . ': ' . $label) ?>"><?= admin_icon('x') ?><span><?= esc(t('reject')) ?></span></button>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </section>

  <?php if ($spouseRequests): ?>
  <section class="adm-section" id="spouse" data-group="spouse" aria-labelledby="apSpouse">
    <div class="adm-section-head">
      <h3 class="adm-section-title" id="apSpouse"><?= esc(t('spouse_requests')) ?></h3>
      <span class="adm-badge adm-badge--static" data-count><?= count($spouseRequests) ?></span>
    </div>
    <p class="adm-section-hint"><?= esc(t('spouse_auto_approve_note')) ?></p>
    <div class="adm-cards" data-list>
      <?php foreach ($spouseRequests as $req):
        $a = admin_person($req, $lang, 'a_');
        $b = admin_person($req, $lang, 'b_');
        $ago = admin_ago_html($req['created_at'] ?? null, $lang);
      ?>
      <article class="adm-card adm-person" id="spouse-row-<?= (int)$req['id'] ?>" data-row>
        <div class="adm-person-main">
          <span class="adm-avatar adm-avatar--icon" aria-hidden="true"><?= admin_icon('heart') ?></span>
          <div class="adm-person-text">
            <h4 class="adm-person-name adm-person-name--line"><?= admin_fill_html(t('adm_spouse_asks'), ['a' => '<strong>' . esc($a) . '</strong>', 'b' => '<strong>' . esc($b) . '</strong>']) ?></h4>
            <p class="adm-person-meta">
              <?php if (!empty($req['congregation'])): ?><span><?= admin_icon('church') ?><?= esc($req['congregation']) ?></span><?php endif; ?>
              <?php if ($ago !== ''): ?><span><?= admin_icon('clock') ?><?= $ago ?></span><?php endif; ?>
            </p>
          </div>
        </div>
        <div class="adm-person-actions">
          <button type="button" class="admin-btn admin-btn-ok spouse-approve-btn" data-id="<?= (int)$req['id'] ?>" aria-label="<?= esc(t('adm_link') . ': ' . $a . ' & ' . $b) ?>"><?= admin_icon('heart') ?><span><?= esc(t('adm_link')) ?></span></button>
          <button type="button" class="admin-btn admin-btn-quiet-danger spouse-reject-btn" data-id="<?= (int)$req['id'] ?>" aria-label="<?= esc(t('reject') . ': ' . $a . ' & ' . $b) ?>"><?= admin_icon('x') ?><span><?= esc(t('reject')) ?></span></button>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($linkedCouples): ?>
  <section class="adm-section" data-group="couples">
    <details class="adm-disclosure">
      <summary>
        <?= admin_icon('heart') ?>
        <span class="adm-disclosure-title"><?= esc(t('linked_spouses')) ?></span>
        <span class="adm-count" data-count><?= count($linkedCouples) ?></span>
        <?= admin_icon('chevron', 'adm-ico adm-disclosure-caret') ?>
      </summary>
      <div class="adm-disclosure-body">
        <?php if (count($linkedCouples) > 6): ?>
        <label class="adm-search">
          <?= admin_icon('search') ?>
          <span class="admin-sr"><?= esc(t('search')) ?></span>
          <input type="search" class="admin-input" data-filter="#couplesList" placeholder="<?= esc(t('adm_search_name')) ?>" autocomplete="off">
        </label>
        <?php endif; ?>
        <ul class="adm-list adm-list--plain" id="couplesList" data-list>
          <?php foreach ($linkedCouples as $couple):
            $pair = admin_person($couple, $lang, 'a_') . ' & ' . admin_person($couple, $lang, 'b_');
          ?>
          <li class="adm-couple" id="couple-row-<?= (int)$couple['a_id'] ?>" data-row data-search="<?= esc(mb_strtolower($pair)) ?>">
            <span class="adm-couple-names"><?= esc($pair) ?></span>
            <button type="button" class="admin-btn admin-btn-sm admin-btn-quiet-danger spouse-unlink-btn" data-id="<?= (int)$couple['a_id'] ?>" aria-label="<?= esc(t('unlink') . ': ' . $pair) ?>"><?= admin_icon('unlink') ?><span><?= esc(t('unlink')) ?></span></button>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </details>
  </section>
  <?php endif; ?>
</div>
