<?php
/**
 * Teaching tab (elders, amp 1-5): which languages of this town's monthly
 * teaching have words in them, when it was last saved, and the way into
 * the editor (/admin/elders.php, which decides on its own who may save).
 */
$teach = admin_teaching_status((string)($currentUser['province_name'] ?? ''), (string)($currentUser['town_name'] ?? ''));
$place = trim(implode(', ', array_filter([(string)($currentUser['town_name'] ?? ''), (string)($currentUser['province_name'] ?? '')])));
$pct = $teach['total'] ? (int)round($teach['done'] / $teach['total'] * 100) : 0;
?>
<div class="adm-card adm-card--pad adm-teach">
  <div class="adm-teach-top">
    <div>
      <?php if ($place !== ''): ?>
      <p class="adm-teach-place"><?= admin_icon('pin') ?><?= esc($place) ?></p>
      <?php endif; ?>
      <p class="adm-teach-progress"><?= esc(str_replace(['{n}', '{total}'], [(string)$teach['done'], (string)$teach['total']], t('adm_teach_progress'))) ?></p>
      <p class="adm-teach-updated">
        <?php if ($teach['updated'] > 0): ?>
        <?= admin_fill_html(t('adm_teach_updated'), ['ago' => admin_ago_html(date('Y-m-d', $teach['updated']), $lang)]) ?>
        <?php else: ?>
        <?= esc(t('adm_teach_none_yet')) ?>
        <?php endif; ?>
      </p>
    </div>
    <a href="/admin/elders.php" class="admin-btn admin-btn-primary"><?= admin_icon('edit') ?><span><?= esc(t('edit_teaching')) ?></span></a>
  </div>

  <span class="adm-meter adm-meter--lg" role="progressbar" aria-valuemin="0" aria-valuemax="<?= (int)$teach['total'] ?>" aria-valuenow="<?= (int)$teach['done'] ?>" aria-label="<?= esc(t('teaching')) ?>"><i style="width: <?= $pct ?>%"></i></span>

  <ul class="adm-langs">
    <?php foreach ($teach['langs'] as $code => $has): ?>
    <li class="<?= $has ? 'is-done' : 'is-empty' ?>">
      <span class="adm-langs-mark" aria-hidden="true"><?= admin_icon($has ? 'check' : 'x') ?></span>
      <span class="adm-langs-name"><?= esc(LANG_NAMES[$code] ?? $code) ?></span>
      <span class="adm-langs-state"><?= $has ? '' : esc(t('adm_teach_empty')) ?></span>
    </li>
    <?php endforeach; ?>
  </ul>
</div>
