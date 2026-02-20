<?php
declare(strict_types=1);
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
require_once __DIR__ . '/../security/auth_gate.php';
require_once __DIR__ . '/../includes/languages.php';

// Language detection
$lang = $_SESSION['language'] ?? null;
if (!$lang || !in_array($lang, SUPPORTED_LANGS, true)) {
    $lang = 'af';
}

function t(string $key): string {
    global $lang;
    return __t($key, $lang);
}

$VER = time();
?><!doctype html>
<html lang="<?= $lang ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= t('thought_history') ?></title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Parisienne&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="/welcome/css/welcome.css?v=<?= $VER ?>">
  <link rel="stylesheet" href="/gedagtes/css/gedagtes.css?v=<?= $VER ?>">
</head>
<body class="welcome-body gedagtes-body">
  <?php require_once __DIR__ . '/../header_footer/header.php'; ?>

  <main class="gedagtes-main">

    <a href="/welcome.php" class="gedagtes-back">
      <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
      <?= t('welcome') ?>
    </a>

    <div class="gedagtes-header">
      <h2 class="gedagtes-title"><?= t('thought_history_title') ?></h2>
      <p class="gedagtes-subtitle"><?= t('thought_of_day') ?></p>
    </div>

    <div id="gedagtesList" class="gedagtes-list">
      <div class="gedagtes-loading"><?= t('loading') ?>...</div>
    </div>

  </main>

  <script>
  (function() {
    'use strict';
    var listEl = document.getElementById('gedagtesList');
    var noThoughts = <?= json_encode(t('no_thoughts_yet')) ?>;

    fetch('/gedagtes/api/list.php')
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (!data.success || !data.thoughts || data.thoughts.length === 0) {
          listEl.innerHTML = '<div class="gedagtes-empty">' +
            '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 21c0 .55.45 1 1 1h4c.55 0 1-.45 1-1v-1H9v1zm3-19C8.14 2 5 5.14 5 9c0 2.38 1.19 4.47 3 5.74V17c0 .55.45 1 1 1h6c.55 0 1-.45 1-1v-2.26c1.81-1.27 3-3.36 3-5.74 0-3.86-3.14-7-7-7z"/></svg>' +
            '<p>' + noThoughts + '</p></div>';
          return;
        }

        var today = new Date().toISOString().split('T')[0];
        var html = '';

        data.thoughts.forEach(function(t) {
          var isToday = t.display_date === today;
          var cls = isToday ? ' gedagtes-card--today' : '';

          html += '<div class="gedagtes-card' + cls + '">';
          html += '  <div class="gedagtes-card-date">' + formatDate(t.display_date) + '</div>';
          html += '  <div class="gedagtes-card-content">' + escapeHtml(t.content) + '</div>';
          if (t.author) {
            html += '  <div class="gedagtes-card-author">&mdash; ' + escapeHtml(t.author) + '</div>';
          }
          html += '</div>';
        });

        listEl.innerHTML = html;
      })
      .catch(function() {
        listEl.innerHTML = '<div class="gedagtes-empty"><p>Error loading thoughts</p></div>';
      });

    function formatDate(dateStr) {
      var d = new Date(dateStr + 'T00:00:00');
      return d.toLocaleDateString(undefined, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    }

    function escapeHtml(str) {
      var div = document.createElement('div');
      div.textContent = str;
      return div.innerHTML;
    }
  })();
  </script>

  <?php require_once __DIR__ . '/../header_footer/footer.php'; ?>
</body>
</html>
