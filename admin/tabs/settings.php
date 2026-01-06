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

<style>
.admin-form {
  display: flex;
  flex-direction: column;
  gap: 25px;
}

.admin-form-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 20px;
}

.admin-field {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.admin-label {
  font-size: 14px;
  font-weight: 500;
  color: var(--color-rosegold);
  letter-spacing: 0.5px;
}

.admin-input {
  padding: 12px 16px;
  background: rgba(10, 10, 10, 0.8);
  border: 1px solid var(--color-silver-dark);
  border-radius: 10px;
  color: var(--color-peach);
  font-family: var(--font-body);
  font-size: 15px;
  transition: var(--transition);
}

.admin-input:focus {
  outline: none;
  border-color: var(--color-rosegold);
  box-shadow: 0 0 0 3px rgba(183, 110, 121, 0.2);
}

@media (max-width: 768px) {
  .admin-form-row {
    grid-template-columns: 1fr;
  }
}
</style>

<script>
document.getElementById('password-form').addEventListener('submit', async function(e) {
  e.preventDefault();

  const current = document.getElementById('current_password').value;
  const newPw = document.getElementById('new_password').value;
  const confirm = document.getElementById('confirm_password').value;

  if (newPw !== confirm) {
    alert('<?= t('passwords_no_match') ?>');
    return;
  }

  if (newPw.length < 6) {
    alert('<?= t('password_min_length') ?>');
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
      alert('<?= t('password_changed') ?>');
      this.reset();
    } else {
      alert(data.error || '<?= t('password_change_failed') ?>');
    }
  } catch (error) {
    alert('<?= t('network_error') ?>: ' + error.message);
  }
});
</script>
