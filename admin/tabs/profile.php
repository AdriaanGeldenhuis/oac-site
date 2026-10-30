<?php
/**
 * My account tab — who you are (office first), your spouse or a spouse
 * request that waits, the way to the edit page, and the password form
 * (admin.js sends it to api/users/change_password.php).
 */
$photoUrl = admin_photo_url($currentUser['photo'] ?? null);

// The spouse, when the link goes both ways
$spouseInfo = null;
if (!empty($currentUser['spouse_user_id'])) {
    $spouseStmt = $pdo->prepare('SELECT name, surname, amp_id, gender FROM users WHERE id = ? AND spouse_user_id = ? LIMIT 1');
    $spouseStmt->execute([(int)$currentUser['spouse_user_id'], $userId]);
    $spouseInfo = $spouseStmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

// A spouse request that involves me (either way), as on the edit page
$spouseRequest = null;
try {
    $st = $pdo->prepare("SELECT sr.id, sr.requester_id, u.name, u.surname, u.amp_id, u.gender FROM spouse_requests sr
        JOIN users u ON u.id = CASE WHEN sr.requester_id = ? THEN sr.receiver_id ELSE sr.requester_id END
        WHERE (sr.requester_id = ? OR sr.receiver_id = ?) AND sr.status = 'pending'
        LIMIT 1");
    $st->execute([$userId, $userId, $userId]);
    $spouseRequest = $st->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $e) {
    error_log('Admin profile spouse request: ' . $e->getMessage());
}

// The eye on each field shows what was typed (admin.js swaps the icon and the label)
$pwToggle = function (string $for): string {
    return '<button type="button" class="admin-pw-toggle" data-pw-toggle="' . esc($for) . '" aria-controls="' . esc($for) . '" aria-pressed="false" aria-label="' . esc(t('adm_pw_show')) . '">'
        . admin_icon('eye', 'adm-ico admin-pw-eye') . admin_icon('eye-off', 'adm-ico admin-pw-eye-off')
        . '</button>';
};
?>
<?php if ($spouseRequest): $other = admin_person($spouseRequest, $lang); ?>
<div class="adm-task adm-task--spouse" id="spouseNotice">
  <span class="adm-task-icon"><?= admin_icon('heart') ?></span>
  <div class="adm-task-body">
    <?php if ((int)$spouseRequest['requester_id'] === $userId): ?>
    <p class="adm-task-title"><?= esc(t('you_sent_spouse_request')) ?> <strong><?= esc($other) ?></strong>. <?= esc(t('waiting_approval')) ?></p>
    <div class="adm-task-actions">
      <button type="button" class="admin-btn admin-btn-secondary" data-spouse="cancel" data-id="<?= (int)$spouseRequest['id'] ?>"><?= esc(t('cancel_request')) ?></button>
    </div>
    <?php else: ?>
    <p class="adm-task-title"><strong><?= esc($other) ?></strong> <?= esc(t('wants_to_marry_you')) ?></p>
    <div class="adm-task-actions">
      <button type="button" class="admin-btn admin-btn-ok" data-spouse="accept" data-id="<?= (int)$spouseRequest['id'] ?>"><?= admin_icon('check') ?><span><?= esc(t('accept')) ?></span></button>
      <button type="button" class="admin-btn admin-btn-quiet-danger" data-spouse="reject" data-id="<?= (int)$spouseRequest['id'] ?>"><?= admin_icon('x') ?><span><?= esc(t('reject')) ?></span></button>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<div class="adm-card adm-card--pad adm-profile">
  <?php if ($photoUrl !== ''): ?>
  <img class="adm-avatar adm-avatar--xl" src="<?= esc($photoUrl) ?>" alt="<?= esc($meName) ?>" width="96" height="96" decoding="async">
  <?php else: ?>
  <span class="adm-avatar adm-avatar--xl adm-avatar--initials" aria-hidden="true"><?= esc(admin_initials($meName)) ?></span>
  <?php endif; ?>
  <div class="adm-profile-info">
    <h3 class="adm-profile-name"><?= esc($meLabel) ?></h3>
    <ul class="adm-facts">
      <?php if (!empty($currentUser['email'])): ?><li><?= admin_icon('mail') ?><span><?= esc($currentUser['email']) ?></span></li><?php endif; ?>
      <?php if (!empty($currentUser['phone'])): ?><li><?= admin_icon('phone') ?><span><?= esc($currentUser['phone']) ?></span></li><?php endif; ?>
      <?php if (!empty($currentUser['congregation_name'])): ?><li><?= admin_icon('church') ?><span><?= esc($currentUser['congregation_name']) ?><?= !empty($currentUser['town_name']) ? ' · ' . esc($currentUser['town_name']) : '' ?></span></li><?php endif; ?>
      <?php if ($spouseInfo): ?><li><?= admin_icon('heart') ?><span><?= esc(t('you_are_linked_to')) ?> <strong><?= esc(admin_person($spouseInfo, $lang)) ?></strong></span></li><?php endif; ?>
    </ul>
    <a href="/admin/account.php" class="admin-btn admin-btn-primary"><?= admin_icon('edit') ?><span><?= esc(t('edit_profile')) ?></span></a>
  </div>
</div>

<section class="adm-section" aria-labelledby="pwTitle">
  <div class="adm-section-head">
    <h3 class="adm-section-title" id="pwTitle"><?= esc(t('change_password')) ?></h3>
  </div>
  <div class="adm-card adm-card--pad">
    <form id="password-form" class="admin-form" novalidate>
      <div class="admin-field">
        <label for="current_password" class="admin-label"><?= esc(t('current_password')) ?></label>
        <div class="admin-pw">
          <input type="password" id="current_password" name="current_password" class="admin-input" autocomplete="current-password" required>
          <?= $pwToggle('current_password') ?>
        </div>
      </div>
      <div class="admin-form-row">
        <div class="admin-field">
          <label for="new_password" class="admin-label"><?= esc(t('new_password')) ?></label>
          <div class="admin-pw">
            <input type="password" id="new_password" name="new_password" class="admin-input" autocomplete="new-password" minlength="8" maxlength="72" required aria-describedby="newPwHint">
            <?= $pwToggle('new_password') ?>
          </div>
          <span class="admin-hint" id="newPwHint"><?= esc(t('adm_pw_min')) ?></span>
        </div>
        <div class="admin-field">
          <label for="confirm_password" class="admin-label"><?= esc(t('confirm_password')) ?></label>
          <div class="admin-pw">
            <input type="password" id="confirm_password" name="confirm_password" class="admin-input" autocomplete="new-password" maxlength="72" required>
            <?= $pwToggle('confirm_password') ?>
          </div>
        </div>
      </div>
      <div class="admin-right">
        <button type="submit" class="admin-btn admin-btn-primary"><?= admin_icon('lock') ?><span><?= esc(t('change_password')) ?></span></button>
      </div>
    </form>
  </div>
</section>
