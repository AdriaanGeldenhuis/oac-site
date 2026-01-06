<?php
$fullName = trim(($currentUser['name'] ?? '') . ' ' . ($currentUser['surname'] ?? ''));
$avatar = !empty($currentUser['photo']) ? $currentUser['photo'] : '/assets/img/avatar-default.png';
$ampName = $currentUser['amp_name'] ?? '';
?>
<div class="admin-section admin-stack">
  <div class="admin-section-header">
    <div class="admin-icon-wrapper">
      <svg class="admin-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        <circle cx="12" cy="7" r="4" stroke="currentColor" stroke-width="2"/>
      </svg>
    </div>
    <h2 class="admin-section-title"><?= t('my_profile') ?></h2>
  </div>

  <div class="admin-profile-card">
    <img class="admin-avatar" src="<?= htmlspecialchars($avatar) ?>" alt="<?= htmlspecialchars($fullName) ?>">
    <div class="admin-profile-info">
      <h3><?= htmlspecialchars($fullName) ?></h3>
      <p><?= htmlspecialchars($currentUser['email'] ?? '') ?></p>
      <?php if (!empty($ampName)): ?>
      <p class="admin-muted"><?= htmlspecialchars($ampName) ?></p>
      <?php endif; ?>
    </div>
  </div>

  <div class="admin-right">
    <a href="/admin/account.php" class="admin-btn admin-btn-primary">
      <?= t('edit_profile') ?>
    </a>
  </div>
</div>