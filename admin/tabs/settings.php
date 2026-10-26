<?php /** Settings tab — change your password (admin.js sends it to api/users/change_password.php). */ ?>
<div class="admin-section">
  <div class="admin-section-header">
    <div class="admin-icon-wrapper">
      <svg class="admin-icon" viewBox="0 0 24 24" aria-hidden="true">
        <rect x="4" y="11" width="16" height="10" rx="2"/>
        <path d="M8 11V7a4 4 0 018 0v4"/>
      </svg>
    </div>
    <h2 class="admin-section-title"><?= esc(t('settings')) ?></h2>
  </div>

  <form id="password-form" class="admin-form" novalidate>
    <div class="admin-field">
      <label for="current_password" class="admin-label"><?= esc(t('current_password')) ?></label>
      <input type="password" id="current_password" name="current_password" class="admin-input" autocomplete="current-password" required>
    </div>
    <div class="admin-form-row">
      <div class="admin-field">
        <label for="new_password" class="admin-label"><?= esc(t('new_password')) ?></label>
        <input type="password" id="new_password" name="new_password" class="admin-input" autocomplete="new-password" minlength="8" maxlength="72" required>
        <span class="admin-hint"><?= esc(t('adm_pw_min')) ?></span>
      </div>
      <div class="admin-field">
        <label for="confirm_password" class="admin-label"><?= esc(t('confirm_password')) ?></label>
        <input type="password" id="confirm_password" name="confirm_password" class="admin-input" autocomplete="new-password" maxlength="72" required>
      </div>
    </div>
    <div class="admin-right">
      <button type="submit" class="admin-btn admin-btn-primary"><?= esc(t('change_password')) ?></button>
    </div>
  </form>
</div>
