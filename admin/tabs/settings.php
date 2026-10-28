<?php
/** Settings tab — change your password (admin.js sends it to api/users/change_password.php). */

// The eye on each field shows what was typed (admin.js swaps the icon and the label)
$pwToggle = function (string $for): string {
    return '<button type="button" class="admin-pw-toggle" data-pw-toggle="' . esc($for) . '" aria-controls="' . esc($for) . '" aria-pressed="false" aria-label="' . esc(t('adm_pw_show')) . '">'
        . '<svg class="admin-pw-eye" viewBox="0 0 24 24" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>'
        . '<svg class="admin-pw-eye-off" viewBox="0 0 24 24" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19M14.12 14.12a3 3 0 11-4.24-4.24M1 1l22 22"/></svg>'
        . '</button>';
};
?>
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
      <button type="submit" class="admin-btn admin-btn-primary"><?= esc(t('change_password')) ?></button>
    </div>
  </form>
</div>
