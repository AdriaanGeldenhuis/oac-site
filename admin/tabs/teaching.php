<?php /** Teaching tab — the way to the teaching editor (elders, amp 1-5). */ ?>
<div class="admin-section">
  <div class="admin-section-header">
    <div class="admin-icon-wrapper">
      <svg class="admin-icon" viewBox="0 0 24 24" aria-hidden="true">
        <path d="M4 19.5A2.5 2.5 0 016.5 17H20M4 19.5A2.5 2.5 0 006.5 22H20V2H6.5A2.5 2.5 0 004 4.5v15z"/>
      </svg>
    </div>
    <h2 class="admin-section-title"><?= esc(t('teaching_management')) ?></h2>
  </div>

  <p class="admin-lead"><?= esc(t('edit_teaching_desc')) ?></p>

  <div class="admin-right">
    <a href="/admin/elders.php" class="admin-btn admin-btn-primary"><?= esc(t('edit_teaching')) ?></a>
  </div>
</div>
