<?php /** Daily thought tab (elders, amp 1-5) — gedagte.js talks to api/gedagte/. */ ?>
<div class="admin-section">
  <div class="admin-section-header">
    <div class="admin-icon-wrapper">
      <svg class="admin-icon" viewBox="0 0 24 24" aria-hidden="true">
        <path d="M9 21h6M10 17h4M12 2a6 6 0 00-3.5 10.9c.6.5 1 1.2 1 2V15h5v-.1c0-.8.4-1.5 1-2A6 6 0 0012 2z"/>
      </svg>
    </div>
    <h2 class="admin-section-title"><?= esc(t('manage_thoughts')) ?></h2>
  </div>

  <form id="gedagteForm" class="gedagte-form admin-form" novalidate>
    <div class="admin-field">
      <label for="thoughtContent" class="admin-label"><?= esc(t('thought_content')) ?></label>
      <textarea id="thoughtContent" name="content" rows="8" maxlength="2000" required class="admin-textarea gedagte-textarea"></textarea>
      <span class="admin-hint" id="thoughtCount" aria-live="polite"></span>
    </div>

    <div class="admin-field">
      <label for="thoughtAuthor" class="admin-label"><?= esc(t('adm_author')) ?></label>
      <input type="text" id="thoughtAuthor" name="author" maxlength="255" class="admin-input" autocomplete="off">
    </div>

    <div class="admin-form-row">
      <div class="admin-field">
        <label for="thoughtDate" class="admin-label"><?= esc(t('thought_date')) ?></label>
        <input type="date" id="thoughtDate" name="display_date" required class="admin-input">
      </div>
      <div class="admin-field">
        <label for="thoughtTime" class="admin-label"><?= esc(t('thought_time')) ?></label>
        <input type="time" id="thoughtTime" name="display_time" value="07:00" class="admin-input">
      </div>
    </div>

    <div class="admin-right">
      <button type="button" class="admin-btn admin-btn-secondary" id="thoughtReset" hidden><?= esc(t('cancel')) ?></button>
      <button type="submit" class="admin-btn admin-btn-primary"><?= esc(t('save_thought')) ?></button>
    </div>
  </form>

  <div class="gedagte-list-section">
    <h3 class="admin-subtitle"><?= esc(t('upcoming_thoughts')) ?></h3>
    <div id="gedagteList" class="gedagte-list" aria-live="polite" aria-busy="true">
      <div class="gedagte-skel" aria-hidden="true"><i></i><i></i></div>
    </div>
  </div>
</div>

<script>
  window.GEDAGTE_LANG = <?= json_encode([
      'lang' => $lang,
      'saved' => t('thought_saved'),
      'deleted' => t('thought_deleted'),
      'confirmDelete' => t('confirm_delete_thought'),
      'noUpcoming' => t('no_upcoming'),
      'deleteLabel' => t('delete'),
      'editLabel' => t('edit'),
      'notifSent' => t('notif_sent'),
      'notifPending' => t('notif_pending'),
      'today' => t('today'),
      'loadFailed' => t('adm_load_failed'),
      'charsLeft' => t('adm_chars_left'),
  ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="<?= admin_asset('/admin/js/gedagte.js') ?>"></script>
