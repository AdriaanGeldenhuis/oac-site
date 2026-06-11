<link rel="stylesheet" href="/admin/css/gedagte.css?v=<?= $VER ?>">

<div class="admin-section admin-stack">
  <div class="admin-section-header">
    <div class="admin-icon-wrapper">
      <svg class="admin-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M9 21c0 .55.45 1 1 1h4c.55 0 1-.45 1-1v-1H9v1zm3-19C8.14 2 5 5.14 5 9c0 2.38 1.19 4.47 3 5.74V17c0 .55.45 1 1 1h6c.55 0 1-.45 1-1v-2.26c1.81-1.27 3-3.36 3-5.74 0-3.86-3.14-7-7-7z" fill="currentColor"/>
      </svg>
    </div>
    <h2 class="admin-section-title"><?= t('manage_thoughts') ?></h2>
  </div>

  <!-- Add new thought form -->
  <form id="gedagteForm" class="gedagte-form">
    <div class="gedagte-field">
      <label for="thoughtContent"><?= t('thought_content') ?></label>
      <textarea id="thoughtContent" name="content" rows="10" required placeholder="<?= esc(t('thought_content')) ?>"></textarea>
    </div>

    <div class="gedagte-row">
      <div class="gedagte-field">
        <label for="thoughtDate"><?= t('thought_date') ?></label>
        <input type="date" id="thoughtDate" name="display_date" required>
      </div>
      <div class="gedagte-field">
        <label for="thoughtTime"><?= t('thought_time') ?></label>
        <input type="time" id="thoughtTime" name="display_time" value="07:00">
      </div>
    </div>

    <div class="gedagte-actions">
      <button type="submit" class="admin-btn admin-btn-primary"><?= t('save_thought') ?></button>
    </div>
  </form>

  <!-- Upcoming thoughts list -->
  <div class="gedagte-list-section">
    <h3 class="gedagte-list-title"><?= t('upcoming_thoughts') ?></h3>
    <div id="gedagteList" class="gedagte-list">
      <p class="gedagte-empty"><?= t('no_upcoming') ?></p>
    </div>
  </div>
</div>

<script>
  window.GEDAGTE_LANG = {
    saved: <?= json_encode(t('thought_saved')) ?>,
    deleted: <?= json_encode(t('thought_deleted')) ?>,
    confirmDelete: <?= json_encode(t('confirm_delete_thought')) ?>,
    noUpcoming: <?= json_encode(t('no_upcoming')) ?>,
    deleteLabel: <?= json_encode(t('delete')) ?>,
    notifSent: <?= json_encode(t('notif_sent')) ?>,
    notifPending: <?= json_encode(t('notif_pending')) ?>
  };
</script>
<script src="/admin/js/gedagte.js?v=<?= $VER ?>"></script>
