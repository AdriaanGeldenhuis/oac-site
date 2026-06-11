<div class="admin-section admin-stack">
  <div class="admin-section-header">
    <div class="admin-icon-wrapper">
      <svg class="admin-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/>
        <path d="M12 1v6m0 6v6M23 12h-6m-6 0H1" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      </svg>
    </div>
    <h2 class="admin-section-title"><?= t('settings') ?></h2>
  </div>

  <form id="password-form" class="admin-form">
    <div class="admin-form-row">
      <div class="admin-field">
        <label for="current_password" class="admin-label"><?= t('current_password') ?></label>
        <input type="password" id="current_password" name="current_password" required class="admin-input">
      </div>
    </div>
    <div class="admin-form-row">
      <div class="admin-field">
        <label for="new_password" class="admin-label"><?= t('new_password') ?></label>
        <input type="password" id="new_password" name="new_password" required class="admin-input">
      </div>
      <div class="admin-field">
        <label for="confirm_password" class="admin-label"><?= t('confirm_password') ?></label>
        <input type="password" id="confirm_password" name="confirm_password" required class="admin-input">
      </div>
    </div>
    <div class="admin-right">
      <button type="submit" class="admin-btn admin-btn-primary">
        <?= t('change_password') ?>
      </button>
    </div>
  </form>
</div>

<script>
document.getElementById('password-form').addEventListener('submit', async function(e) {
  e.preventDefault();

  const current = document.getElementById('current_password').value;
  const newPw = document.getElementById('new_password').value;
  const confirm = document.getElementById('confirm_password').value;

  if (newPw !== confirm) {
    OACUI.toast(<?= json_encode(t('passwords_no_match')) ?>, 'error');
    return;
  }

  if (newPw.length < 6) {
    OACUI.toast(<?= json_encode(t('password_min_length')) ?>, 'error');
    return;
  }

  try {
    const response = await fetch('/api/users/change_password.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        current_password: current,
        new_password: newPw
      })
    });

    const data = await response.json();
    if (data.success) {
      OACUI.toast(<?= json_encode(t('password_changed')) ?>, 'success');
      this.reset();
    } else {
      OACUI.toast(data.error || <?= json_encode(t('password_change_failed')) ?>, 'error');
    }
  } catch (error) {
    OACUI.toast(<?= json_encode(t('network_error')) ?> + ': ' + error.message, 'error');
  }
});
</script>
