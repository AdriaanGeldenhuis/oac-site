<?php
/** Profile tab — who you are, and the way to the edit page. */
$fullName = trim(($currentUser['name'] ?? '') . ' ' . ($currentUser['surname'] ?? ''));
$photoUrl = admin_photo_url($currentUser['photo'] ?? null);
$ampName = (string)($currentUser['amp_name'] ?? '');

// The spouse, when the link goes both ways
$spouseInfo = null;
if (!empty($currentUser['spouse_user_id'])) {
    $spouseStmt = $pdo->prepare('SELECT name, surname FROM users WHERE id = ? AND spouse_user_id = ? LIMIT 1');
    $spouseStmt->execute([(int)$currentUser['spouse_user_id'], $userId]);
    $spouseInfo = $spouseStmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
?>
<div class="admin-section">
  <div class="admin-section-header">
    <div class="admin-icon-wrapper">
      <svg class="admin-icon" viewBox="0 0 24 24" aria-hidden="true">
        <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/>
        <circle cx="12" cy="7" r="4"/>
      </svg>
    </div>
    <h2 class="admin-section-title"><?= esc(t('my_profile')) ?></h2>
  </div>

  <div class="admin-profile-card">
    <?php if ($photoUrl !== ''): ?>
    <img class="admin-avatar" src="<?= esc($photoUrl) ?>" alt="<?= esc($fullName) ?>" width="84" height="84" decoding="async">
    <?php else: ?>
    <div class="admin-avatar admin-avatar-initials" aria-hidden="true"><?= esc(admin_initials($fullName)) ?></div>
    <?php endif; ?>
    <div class="admin-profile-info">
      <h3><?= esc($fullName) ?></h3>
      <p><?= esc($currentUser['email'] ?? '') ?></p>
      <?php if ($ampName !== ''): ?>
      <p class="admin-role"><?= esc($ampName) ?></p>
      <?php endif; ?>
      <?php if ($spouseInfo): ?>
      <p class="admin-spouse-link">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg>
        <span><?= esc(t('you_are_linked_to')) ?> <strong><?= esc(trim($spouseInfo['name'] . ' ' . $spouseInfo['surname'])) ?></strong></span>
      </p>
      <?php endif; ?>
    </div>
  </div>

  <div class="admin-right">
    <a href="/admin/account.php" class="admin-btn admin-btn-primary"><?= esc(t('edit_profile')) ?></a>
  </div>
</div>
