<?php
$fullName = trim(($currentUser['name'] ?? '') . ' ' . ($currentUser['surname'] ?? ''));
$hasPhoto = !empty($currentUser['photo']);
$ampName = $currentUser['amp_name'] ?? '';

// Generate initials
$nameParts = preg_split('/\s+/', $fullName);
$profileInitials = '';
if (count($nameParts) >= 2) {
    $profileInitials = strtoupper(mb_substr($nameParts[0], 0, 1) . mb_substr($nameParts[count($nameParts) - 1], 0, 1));
} elseif (count($nameParts) === 1 && !empty($nameParts[0])) {
    $profileInitials = strtoupper(mb_substr($nameParts[0], 0, 2));
}

// Get spouse info if married
$spouseInfo = null;
if (!empty($currentUser['spouse_user_id'])) {
    $spouseStmt = $pdo->prepare("SELECT name, surname, photo FROM users WHERE id = ? LIMIT 1");
    $spouseStmt->execute([$currentUser['spouse_user_id']]);
    $spouseInfo = $spouseStmt->fetch(PDO::FETCH_ASSOC);
}
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
    <?php if ($hasPhoto): ?>
    <img class="admin-avatar" src="<?= htmlspecialchars($currentUser['photo']) ?>" alt="<?= htmlspecialchars($fullName) ?>">
    <?php else: ?>
    <div class="admin-avatar admin-avatar-initials"><?= htmlspecialchars($profileInitials) ?></div>
    <?php endif; ?>
    <div class="admin-profile-info">
      <h3><?= htmlspecialchars($fullName) ?></h3>
      <p><?= htmlspecialchars($currentUser['email'] ?? '') ?></p>
      <?php if (!empty($ampName)): ?>
      <p class="admin-muted"><?= htmlspecialchars($ampName) ?></p>
      <?php endif; ?>
      <?php if ($spouseInfo): ?>
      <p class="admin-spouse-link">
        <svg viewBox="0 0 24 24" fill="none" width="16" height="16" style="display:inline-block;vertical-align:middle;margin-right:6px;">
          <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" stroke="currentColor" stroke-width="2" fill="rgba(183,110,121,0.3)"/>
        </svg>
        <?= t('you_are_linked_to') ?> <strong><?= htmlspecialchars(trim($spouseInfo['name'] . ' ' . $spouseInfo['surname'])) ?></strong>
      </p>
      <?php endif; ?>
    </div>
  </div>

  <div class="admin-right">
    <a href="/admin/account.php" class="admin-btn admin-btn-primary">
      <?= t('edit_profile') ?>
    </a>
  </div>
</div>