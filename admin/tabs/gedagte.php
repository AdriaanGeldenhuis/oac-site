<?php
/** Daily thought tab (elders, amp 1-5) — gedagte.js talks to api/gedagte/. */
require_once dirname(__DIR__, 2) . '/includes/daily_thoughts.php';
$gdAi = dt_ai_ready();
?>
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
    <input type="hidden" id="thoughtId" name="id" value="">

    <div class="admin-field">
      <label for="thoughtContent" class="admin-label"><?= esc(t('thought_content')) ?></label>
      <!-- No maxlength: a pasted thought is never cut off silently, the counter says how much is too much -->
      <textarea id="thoughtContent" name="content" rows="8" data-max="<?= (int)DT_MAX_CHARS ?>" required class="admin-textarea gedagte-textarea" aria-describedby="thoughtCount"></textarea>
      <div class="gedagte-field-foot">
        <span class="admin-hint gedagte-count" id="thoughtCount" aria-live="polite"></span>
        <?php if ($gdAi): ?>
        <button type="button" class="gedagte-btn gedagte-btn--check" id="thoughtCheck"><?= esc(t('gd_check_btn')) ?></button>
        <?php endif; ?>
      </div>

      <div class="gedagte-suggest" id="thoughtSuggest" role="region" aria-labelledby="thoughtSuggestTitle" hidden>
        <p class="gedagte-suggest-title" id="thoughtSuggestTitle"><?= esc(t('gd_check_title')) ?></p>
        <p class="gedagte-suggest-text" id="thoughtSuggestText"></p>
        <div class="gedagte-suggest-actions">
          <button type="button" class="gedagte-btn" id="thoughtSuggestKeep"><?= esc(t('gd_check_keep')) ?></button>
          <button type="button" class="gedagte-btn gedagte-btn--accent" id="thoughtSuggestUse"><?= esc(t('gd_check_use')) ?></button>
        </div>
      </div>
    </div>

    <div class="admin-form-row">
      <div class="admin-field">
        <label for="thoughtAuthor" class="admin-label"><?= esc(t('adm_author')) ?></label>
        <input type="text" id="thoughtAuthor" name="author" maxlength="<?= (int)DT_MAX_AUTHOR ?>" class="admin-input" autocomplete="off">
      </div>
      <div class="admin-field">
        <label for="thoughtLang" class="admin-label"><?= esc(t('thought_lang')) ?></label>
        <select id="thoughtLang" name="lang" class="admin-select">
          <?php foreach (SUPPORTED_LANGS as $code): ?>
          <option value="<?= esc($code) ?>"<?= $code === $lang ? ' selected' : '' ?>><?= esc(LANG_NAMES[$code] ?? $code) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
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
      <button type="submit" class="admin-btn admin-btn-primary"><span class="gedagte-submit-label"><?= esc(t('save_thought')) ?></span></button>
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
      'ai' => $gdAi,
      'langNames' => LANG_NAMES,
      'saved' => t('thought_saved'),
      'savedTranslated' => t('gd_saved_translated'),
      'savedMissing' => t('gd_saved_missing'),
      'saving' => t('gd_saving'),
      'saveLabel' => t('save_thought'),
      'deleted' => t('thought_deleted'),
      'confirmDelete' => t('confirm_delete_thought'),
      'noUpcoming' => t('no_upcoming'),
      'deleteLabel' => t('delete'),
      'editLabel' => t('edit'),
      'save' => t('save'),
      'cancel' => t('cancel'),
      'notifSent' => t('notif_sent'),
      'notifPending' => t('notif_pending'),
      'today' => t('today'),
      'loadFailed' => t('adm_load_failed'),
      'charsLeft' => t('adm_chars_left'),
      'charsOver' => t('adm_chars_over'),
      'tooLong' => t('gd_too_long'),
      'checkNone' => t('gd_check_none'),
      'langsLabel' => t('gd_langs_label'),
      'original' => t('gd_original'),
      'notTranslated' => t('gd_not_translated'),
      'byHand' => t('gd_by_hand'),
      'editTranslation' => t('gd_edit_translation'),
      'translationSaved' => t('gd_translation_saved'),
      'translateMissing' => t('gd_translate_missing'),
      'translated' => t('gd_translated'),
      'author' => t('adm_author'),
  ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="<?= admin_asset('/admin/js/gedagte.js') ?>"></script>
