<?php
declare(strict_types=1);
/**
 * Lering — the Teaching of the Month and the Year theme card, one tab each.
 * "Light Through Glass", emerald edition (the colour of its welcome tile).
 *
 * Both tabs are drawn here by PHP: the app keeps this page on the phone
 * (sw.js, includes/offline.php), so both work without internet and
 * switching between them never asks the server for anything.
 *
 * ?lang=xx  (the header's language switch) is remembered, then the page
 * reloads without it. #jaartema / #lering open that tab.
 */

header('Cache-Control: no-cache, must-revalidate');
require_once dirname(__DIR__) . '/security/auth_gate.php';
require_once __DIR__ . '/lib/lering.php';
require_once dirname(__DIR__) . '/includes/offline.php';

if (isset($_GET['lang']) && in_array($_GET['lang'], SUPPORTED_LANGS, true)) {
    $_SESSION['language'] = $_GET['lang'];
    header('Location: /lering/lering.php');
    exit;
}

// The reader's language and town (the teaching belongs to the town)
$lang = $_SESSION['language'] ?? null;
$town = '';
$province = '';
try {
    $stmt = $pdo->prepare('
        SELECT u.language, t.name AS town, p.name AS province
        FROM users u
        LEFT JOIN towns t ON t.id = u.town_id
        LEFT JOIN provinces p ON p.id = t.province_id
        WHERE u.id = ?
        LIMIT 1
    ');
    $stmt->execute([(int)$_SESSION['user_id']]);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $town = (string)($row['town'] ?? '');
        $province = (string)($row['province'] ?? '');
        if (!is_string($lang) || !in_array($lang, SUPPORTED_LANGS, true)) {
            $lang = validate_language((string)($row['language'] ?? 'en'));
        }
    }
} catch (Throwable $e) {
    error_log('Lering page - DB error: ' . $e->getMessage());
}
if (!is_string($lang) || !in_array($lang, SUPPORTED_LANGS, true)) {
    $lang = 'en';
}
$_SESSION['language'] = $lang;

function t(string $key): string {
    global $lang;
    return __t($key, $lang);
}

// Teaching of the Month: the town's own in the reader's language, with the
// usual fallbacks (includes/teaching.php)
$teach = teaching_reader(teaching_dir($province, $town), $lang);
$hasTeach = $teach['html'] !== '';

// Year theme card
$year = lr_year_theme($lang);
$card = $year['card'] ?? null;

// Which tab opens when the reader has no choice saved: the teaching, unless
// there is none yet
$defaultTab = $hasTeach || !$year ? 'month' : 'year';

$yearLabel = t('year_theme');
$yearNum = (string)($year['year'] ?? '');

$icons = [
    'teach' => '<path d="M12 11.55C9.64 9.35 6.48 8 3 8v11c3.48 0 6.64 1.35 9 3.55 2.36-2.19 5.52-3.55 9-3.55V8c-3.48 0-6.64 1.35-9 3.55zM12 8c1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3 1.34 3 3 3z"/>',
    'year'  => '<path d="M17 3H7c-1.1 0-1.99.9-1.99 2L5 21l7-3 7 3V5c0-1.1-.9-2-2-2z"/>',
    'book'  => '<path d="M18 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM6 4h5v8l-2.5-1.5L6 12V4z"/>',
    'clock' => '<path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/>',
    'info'  => '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/>',
];

$chevron = '<svg class="lr-chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg>';

/** "Not yet in your language. Shown in: English" */
function lr_other_lang_note(string $shownIn): string {
    return str_replace('{lang}', LANG_NAMES[$shownIn] ?? $shownIn, t('lr_other_lang'));
}
?><!doctype html>
<html lang="<?= lr_e($lang) ?>" data-lr-default="<?= $defaultTab ?>"<?= $hasTeach ? '' : ' data-lr-month-empty="1"' ?>>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= lr_e(t('teaching')) ?> - OAC APP</title>

  <script>
    // Open the right tab before the first paint (no jump): #jaartema /
    // #lering, else the reader's last tab, else the server's choice. Should
    // lering.js never run, lr-js goes after 4 s and both tabs show, one
    // under the other.
    (function (root) {
      var tab = root.getAttribute('data-lr-default') || 'month';
      try {
        var saved = window.localStorage.getItem('lr_tab');
        if (saved === 'month' && root.getAttribute('data-lr-month-empty') === '1') { saved = null; }
        if (saved === 'month' || saved === 'year') { tab = saved; }
        var scale = parseFloat(window.localStorage.getItem('wc_read_scale'));
        if (scale >= 0.8 && scale <= 1.6) { root.style.setProperty('--lr-scale', String(scale)); }
      } catch (e) { /* storage blocked: defaults */ }
      var hash = (window.location.hash || '').slice(1);
      if (hash === 'jaartema' || hash.indexOf('lr-y-') === 0) { tab = 'year'; }
      else if (hash === 'lering') { tab = 'month'; }
      root.setAttribute('data-lr-tab', tab);
      root.classList.add('lr-js');
      setTimeout(function () { if (!window.lrReady) { root.classList.remove('lr-js'); } }, 4000);
    })(document.documentElement);
  </script>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;1,500;1,600&family=Parisienne&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="<?= lr_e(oac_asset('/lering/css/lering.css')) ?>">
</head>
<body class="lr-body">
  <?php require_once dirname(__DIR__) . '/header_footer/header.php'; ?>

  <svg class="lr-sprite" width="0" height="0" aria-hidden="true" focusable="false"><defs>
    <?php foreach ($icons as $id => $path): ?>
    <symbol id="lr-i-<?= $id ?>" viewBox="0 0 24 24" fill="currentColor"><?= $path ?></symbol>
    <?php endforeach; ?>
  </defs></svg>

  <div class="lr-aura" aria-hidden="true"><div class="lr-rays"></div><div class="lr-halo"></div></div>

  <main class="lr-main" id="lrMain">

    <section class="lr-hero" aria-labelledby="lrTitle">
      <p class="lr-eyebrow">
        <svg class="lr-eyebrow-icon" viewBox="0 0 24 24" aria-hidden="true"><use href="#lr-i-teach"/></svg>
        <span><?= lr_e(t('grow_faith')) ?></span>
      </p>
      <h2 class="lr-title" id="lrTitle"><span><?= lr_e(t('teaching')) ?></span></h2>
    </section>

    <!-- Tabs: stay in view while reading; the line along the bottom fills as you read -->
    <div class="lr-bar" id="lrBar">
      <div class="lr-tabs" role="tablist" aria-label="<?= lr_e(t('lr_tabs_label')) ?>">
        <button type="button" class="lr-tab" role="tab" id="lrTabMonth" data-tab="month" aria-controls="lrPanelMonth" aria-selected="<?= $defaultTab === 'month' ? 'true' : 'false' ?>" tabindex="<?= $defaultTab === 'month' ? '0' : '-1' ?>">
          <svg class="lr-tab-icon" viewBox="0 0 24 24" aria-hidden="true"><use href="#lr-i-teach"/></svg>
          <span class="lr-tab-text"><span class="lr-tab-name"><?= lr_e(t('teaching_of_month')) ?></span></span>
        </button>
        <button type="button" class="lr-tab" role="tab" id="lrTabYear" data-tab="year" aria-controls="lrPanelYear" aria-selected="<?= $defaultTab === 'year' ? 'true' : 'false' ?>" tabindex="<?= $defaultTab === 'year' ? '0' : '-1' ?>">
          <svg class="lr-tab-icon" viewBox="0 0 24 24" aria-hidden="true"><use href="#lr-i-year"/></svg>
          <span class="lr-tab-text">
            <span class="lr-tab-name"><?= lr_e($yearLabel) ?></span>
            <?php if ($yearNum !== ''): ?><span class="lr-tab-sub"><?= lr_e($yearNum) ?></span><?php endif; ?>
          </span>
        </button>
      </div>
      <div class="lr-progress" aria-hidden="true"><i id="lrProgress"></i></div>
    </div>

    <!-- Text size, for both tabs -->
    <div class="lr-tools" id="lrTools" role="group" aria-label="<?= lr_e(t('size')) ?>" hidden>
      <button type="button" class="lr-size-btn lr-size-btn--sm" id="lrSizeDown" aria-label="<?= lr_e(t('decrease')) ?>" title="<?= lr_e(t('decrease')) ?>">A</button>
      <button type="button" class="lr-size-btn lr-size-btn--lg" id="lrSizeUp" aria-label="<?= lr_e(t('increase')) ?>" title="<?= lr_e(t('increase')) ?>">A</button>
    </div>

    <!-- ===== Teaching of the Month ===== -->
    <section class="lr-panel lr-panel--month" id="lrPanelMonth" role="tabpanel" aria-labelledby="lrTabMonth" tabindex="0">
      <h3 class="lr-panel-title"><?= lr_e(t('teaching_of_month')) ?></h3>
      <article class="lr-card lr-teach">
        <div class="lr-card-edge" aria-hidden="true"></div>
        <?php if ($hasTeach): ?>
          <p class="lr-meta">
            <?php if ($teach['updated'] > 0): ?>
            <span class="lr-meta-item">
              <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#lr-i-clock"/></svg>
              <?= lr_e(str_replace('{date}', lr_date($teach['updated'], $lang), t('lr_updated'))) ?>
            </span>
            <?php endif; ?>
            <?php if ($teach['lang'] !== $lang): ?>
            <span class="lr-meta-item lr-meta-item--note">
              <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#lr-i-info"/></svg>
              <?= lr_e(lr_other_lang_note($teach['lang'])) ?>
            </span>
            <?php endif; ?>
          </p>
          <!-- Sanitized on the way out (includes/teaching.php), so older files are just as safe -->
          <div class="lr-content" lang="<?= lr_e($teach['lang']) ?>"><?= $teach['html'] ?></div>
        <?php else: ?>
          <div class="lr-empty">
            <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#lr-i-teach"/></svg>
            <p><?= lr_e(t('no_content')) ?></p>
          </div>
        <?php endif; ?>
      </article>
    </section>

    <!-- ===== Year theme card ===== -->
    <section class="lr-panel lr-panel--year" id="lrPanelYear" role="tabpanel" aria-labelledby="lrTabYear" tabindex="0">
      <h3 class="lr-panel-title"><?= lr_e(trim($yearLabel . ' ' . $yearNum)) ?></h3>
      <?php if ($card): ?>
      <article class="lr-card lr-year" lang="<?= lr_e($year['lang']) ?>">
        <header class="lr-year-head">
          <?php if ($card['title'] !== ''): ?><p class="lr-year-kicker"><?= lr_e($card['title']) ?></p><?php endif; ?>
          <?php if ($yearNum !== ''): ?><p class="lr-year-num"><?= lr_e($yearNum) ?></p><?php endif; ?>
          <?php if ($year['fallback']): ?>
          <p class="lr-meta"><span class="lr-meta-item lr-meta-item--note" lang="<?= lr_e($lang) ?>">
            <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#lr-i-info"/></svg>
            <?= lr_e(lr_other_lang_note($year['lang'])) ?>
          </span></p>
          <?php endif; ?>
        </header>

        <?php
          // Jump links to each part of the card that has words in it
          $parts = [];
          if ($card['background']) { $parts['background'] = $card['background_label']; }
          if ($card['theme'] !== '') { $parts['theme'] = $card['theme_label']; }
          if ($card['text'] !== '') { $parts['text'] = $card['text_label']; }
          if ($card['objectives']) { $parts['objectives'] = $card['objectives_label']; }
        ?>
        <?php if (count($parts) > 1): ?>
        <nav class="lr-jump" aria-label="<?= lr_e(t('lr_sections')) ?>">
          <?php foreach ($parts as $id => $label): ?>
          <a class="lr-jump-link" href="#lr-y-<?= $id ?>"><?= lr_e($label) ?></a>
          <?php endforeach; ?>
        </nav>
        <?php endif; ?>

        <?php if ($card['background']): ?>
        <section class="lr-y-sec" id="lr-y-background" aria-labelledby="lr-y-background-h">
          <h4 class="lr-label" id="lr-y-background-h"><?= lr_e($card['background_label']) ?></h4>
          <div class="lr-prose">
            <?php foreach ($card['background'] as $p): ?>
            <p><?= lr_e($p) ?></p>
            <?php endforeach; ?>
          </div>
        </section>
        <?php endif; ?>

        <?php if ($card['theme'] !== ''): ?>
        <section class="lr-y-sec" id="lr-y-theme" aria-labelledby="lr-y-theme-h">
          <h4 class="lr-label" id="lr-y-theme-h"><?= lr_e($card['theme_label']) ?></h4>
          <figure class="lr-pane">
            <blockquote class="lr-pane-text"><?= lr_e($card['theme']) ?></blockquote>
          </figure>
        </section>
        <?php endif; ?>

        <?php if ($card['text'] !== ''): ?>
        <section class="lr-y-sec" id="lr-y-text" aria-labelledby="lr-y-text-h">
          <h4 class="lr-label" id="lr-y-text-h"><?= lr_e($card['text_label']) ?></h4>
          <?php if ($year['bible']): ?>
          <a class="lr-scripture" href="<?= lr_e(lr_bible_href($year['bible']['book'], $year['bible']['chapter'], $year['bible']['from'])) ?>">
            <span class="lr-scripture-orb" aria-hidden="true"><svg viewBox="0 0 24 24"><use href="#lr-i-book"/></svg></span>
            <span class="lr-scripture-body">
              <span class="lr-scripture-ref"><?= lr_e($card['text']) ?></span>
              <span class="lr-scripture-hint" lang="<?= lr_e($lang) ?>"><?= lr_e(t('lr_read_bible')) ?></span>
            </span>
            <?= $chevron ?>
          </a>
          <?php else: ?>
          <p class="lr-scripture lr-scripture--plain"><span class="lr-scripture-ref"><?= lr_e($card['text']) ?></span></p>
          <?php endif; ?>
        </section>
        <?php endif; ?>

        <?php if ($card['objectives']): ?>
        <section class="lr-y-sec" id="lr-y-objectives" aria-labelledby="lr-y-objectives-h">
          <h4 class="lr-label" id="lr-y-objectives-h"><?= lr_e($card['objectives_label']) ?></h4>
          <?php if ($card['objectives_intro'] !== ''): ?>
          <p class="lr-obj-intro"><?= lr_e($card['objectives_intro']) ?></p>
          <?php endif; ?>
          <ol class="lr-obj">
            <?php foreach ($card['objectives'] as $i => $o): ?>
            <li class="lr-obj-item">
              <span class="lr-obj-mark"><?= lr_letter($i) ?></span>
              <p class="lr-obj-text">
                <?= lr_e($o['text']) ?>
                <?php if ($o['ref'] !== '' && $year['bible'] && $o['from'] > 0): ?>
                <a class="lr-verse" href="<?= lr_e(lr_bible_href($year['bible']['book'], $year['bible']['chapter'], $o['from'])) ?>"
                   aria-label="<?= lr_e($card['text'] !== '' ? preg_replace('/:.*$/u', '', $card['text']) . ', ' . $o['ref'] : $o['ref']) ?>"><?= lr_e($o['ref']) ?></a><?= lr_e($o['end']) ?>
                <?php elseif ($o['ref'] !== ''): ?>
                <span class="lr-verse lr-verse--plain"><?= lr_e($o['ref']) ?></span><?= lr_e($o['end']) ?>
                <?php else: ?><?= lr_e($o['end']) ?><?php endif; ?>
              </p>
            </li>
            <?php endforeach; ?>
          </ol>
        </section>
        <?php endif; ?>
      </article>
      <?php else: ?>
      <article class="lr-card">
        <div class="lr-empty">
          <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#lr-i-year"/></svg>
          <p><?= lr_e(t('no_content')) ?></p>
        </div>
      </article>
      <?php endif; ?>
    </section>

  </main>

  <script src="<?= lr_e(oac_asset('/lering/js/lering.js')) ?>" defer></script>
  <?php require_once dirname(__DIR__) . '/header_footer/footer.php'; ?>
</body>
</html>
