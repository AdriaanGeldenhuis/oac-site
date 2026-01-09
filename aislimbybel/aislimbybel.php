<?php
// =====================================================================
// /aislimbybel/aislimbybel.php — AI Slimbybel
// =====================================================================

require_once __DIR__ . '/../security/auth_gate.php';
require_once __DIR__ . '/../includes/languages.php';

// -------------------------- Taal --------------------------------------
$pageLang = $_SESSION['language'] ?? 'af';
if (isset($_GET['lang']) && in_array($_GET['lang'], SUPPORTED_LANGS, true)) {
  $_SESSION['language'] = $_GET['lang'];
  $pageLang = $_GET['lang'];
  header('Location: /aislimbybel/aislimbybel.php');
  exit;
}
// Translation helper using central 5-language system
function t(string $key): string { global $pageLang; return __t($key, $pageLang); }

// --------------------- Laai ou konfig/riglyne -------------------------
$OLD_CONFIG = __DIR__ . '/config.php';
$INSTR_FILE = __DIR__ . '/Slimbybel_Instruksies.txt';
if (is_file($OLD_CONFIG)) {
  @require_once $OLD_CONFIG;
}
$OPENAI_API_KEY = defined('OPENAI_API_KEY') ? constant('OPENAI_API_KEY') : getenv('OPENAI_API_KEY');

$SYSTEM_PROMPT = '';
if (defined('SLIMBYBEL_SYSTEM_PROMPT')) {
  $SYSTEM_PROMPT = constant('SLIMBYBEL_SYSTEM_PROMPT');
} elseif (is_file($INSTR_FILE)) {
  $SYSTEM_PROMPT = file_get_contents($INSTR_FILE) ?: '';
} else {
  $SYSTEM_PROMPT = 'Interpret the Scriptures spiritually. Use Afrikaans 1933/1953 or KJV only.';
}

// ------------------------- SSE ROUTE ----------------------------------
if (isset($_GET['stream']) && $_GET['stream'] === '1') {
  $q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

  header('Content-Type: text/event-stream');
  header('Cache-Control: no-cache');
  header('Connection: keep-alive');
  header('X-Accel-Buffering: no');

  @ini_set('output_buffering', 'off');
  @ini_set('zlib.output_compression', '0');
  @ini_set('implicit_flush', '1');
  while (ob_get_level() > 0) { @ob_end_flush(); }
  @ob_implicit_flush(1);

  $sse = function(string $event, string $data) {
    if ($event !== 'message') { echo "event: {$event}\n"; }
    foreach (preg_split("/\r?\n/", $data) as $ln) { echo 'data: ' . $ln . "\n"; }
    echo "\n";
    @flush();
  };

  if ($q === '') { $sse('error', t('empty_question')); $sse('done', 'end'); exit; }
  if (!$OPENAI_API_KEY) { $sse('error', t('missing_api_key')); $sse('done', 'end'); exit; }

  // Language-specific instructions - STRICT enforcement
  $langInstructions = [
    'af' => 'BELANGRIK: Jy MOET alle antwoorde in SUIWER AFRIKAANS skryf. Gebruik SLEGS die 1933/1953 Afrikaanse Bybel vertaling. MOENIE ooit woorde of frases in Engels of enige ander taal gebruik nie. Elke woord moet Afrikaans wees.',
    'en' => 'IMPORTANT: You MUST write all answers in PURE ENGLISH only. Use ONLY the King James Version (KJV) Bible. NEVER use words or phrases in any other language. Every single word must be English.',
    'zu' => 'OKUBALULEKILE: KUMELE ubhale zonke izimpendulo ngesi-ZULU KUPHELA. Sebenzisa i-King James Version (KJV) Bible. UNGASEBENZISI amagama noma izingxenye ngesiNgisi noma ngolunye ulimi. Igama ngalinye kumele libe ngesiZulu.',
    'xh' => 'OKUBALULEKILEYO: KUFUNEKA ubhale zonke iimpendulo ngesi-XHOSA KUPHELA. Sebenzisa i-King James Version (KJV) Bible. UNGAZE usebenzise amagama okanye amabinzana ngesiNgesi okanye naluphi na olunye ulwimi. Igama ngalinye kufuneka libe sesiXhosa.',
    'pt' => 'IMPORTANTE: Você DEVE escrever todas as respostas em PORTUGUÊS PURO apenas. Use SOMENTE a versão King James (KJV) da Bíblia. NUNCA use palavras ou frases em inglês ou qualquer outro idioma. Cada palavra deve ser em português.'
  ];

  $langInstruction = $langInstructions[$pageLang] ?? $langInstructions['en'];

  $messages = [
    ['role' => 'system', 'content' => $SYSTEM_PROMPT],
    ['role' => 'system', 'content' => $langInstruction],
    ['role' => 'user',   'content' => $q],
  ];

  $payload = json_encode([
    'model'      => 'gpt-4o-mini',
    'messages'   => $messages,
    'temperature'=> 0.2,
    'stream'     => true,
  ], JSON_UNESCAPED_SLASHES);

  $ch = curl_init('https://api.openai.com/v1/chat/completions');
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $OPENAI_API_KEY,
    'Content-Type: application/json',
  ]);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
  curl_setopt($ch, CURLOPT_TIMEOUT, 0);
  curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $chunk) use ($sse) {
    $lines = preg_split("/\r?\n/", $chunk);
    foreach ($lines as $line) {
      if (strpos($line, 'data: ') === 0) {
        $json = trim(substr($line, 6));
        if ($json === '[DONE]') { $sse('done', 'end'); continue; }
        $obj = json_decode($json, true);
        if (isset($obj['choices'][0]['delta']['content'])) {
          $token = (string)$obj['choices'][0]['delta']['content'];
          if ($token !== '') { $sse('message', $token); }
        }
      }
    }
    return strlen($chunk);
  });

  $ok = curl_exec($ch);
  if ($ok === false) { $sse('error', 'Stream error: ' . curl_error($ch)); $sse('done', 'end'); }
  curl_close($ch);
  exit;
}

// -------------------------- HTML UI -----------------------------------
function esc($s) { return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?><!doctype html>
<html lang="<?= $pageLang ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= esc(t('ai_slimbybel')) ?></title>
  
  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <style>
    @font-face {
      font-family: 'Parisienne';
      src: url('/assets/fonts/Parisienne-Regular.ttf') format('truetype');
      font-weight: normal;
      font-style: normal;
      font-display: swap;
    }
  </style>
  
  <link rel="stylesheet" href="/aislimbybel/css/aislimbybel.css?v=<?= time() ?>">
</head>
<body class="aislimbybel-body">
  <?php require_once __DIR__ . '/../header_footer/header.php'; ?>

  <!-- Hero Section -->
  <div class="sb-hero">
    <div class="sb-hero-glow"></div>
    <div class="sb-hero-content">
      <h1 class="sb-hero-title"><?= esc(t('ai_slimbybel')) ?></h1>
      <p class="sb-hero-subtitle"><?= esc(t('spiritual_wisdom_ai')) ?></p>
    </div>
    <div class="sb-sparkles">
      <span class="sb-sparkle" style="--delay: 0s; --x: 10%; --y: 20%;"></span>
      <span class="sb-sparkle" style="--delay: 0.5s; --x: 85%; --y: 30%;"></span>
      <span class="sb-sparkle" style="--delay: 1s; --x: 50%; --y: 60%;"></span>
      <span class="sb-sparkle" style="--delay: 1.5s; --x: 20%; --y: 80%;"></span>
      <span class="sb-sparkle" style="--delay: 2s; --x: 90%; --y: 70%;"></span>
    </div>
  </div>

  <main class="sb-main">
    <!-- Search Section -->
    <section class="sb-section sb-search-section">
      <div class="sb-section-header">
        <div class="sb-icon-wrapper">
          <svg class="sb-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="2"/>
            <path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </div>
        <h2 class="sb-section-title"><?= esc(t('ask_scripture')) ?></h2>
      </div>

      <form id="sbForm" class="sb-form" action="#" method="get">
        <div class="sb-input-group">
          <input 
            type="text" 
            id="q_input" 
            name="q" 
            class="sb-input" 
            autocomplete="off" 
            placeholder="<?= esc(t('ask_placeholder')) ?>"
          >
          <button class="sb-btn sb-btn-primary" id="askBtn" type="submit">
            <span class="sb-btn-shine"></span>
            <svg class="sb-btn-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <span class="sb-btn-text"><?= esc(t('explain')) ?></span>
          </button>
        </div>
      </form>

      <div class="sb-help">
        <svg class="sb-help-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.5"/>
          <path d="M12 16v.01M12 8v5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
        </svg>
        <p class="sb-help-text"><?= esc(t('spiritual_interpretation_help')) ?></p>
      </div>
    </section>

    <!-- Answer Section -->
    <section class="sb-section sb-answer-section">
      <div class="sb-section-header">
        <div class="sb-icon-wrapper">
          <svg class="sb-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M12 2L2 7v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V7l-8-5z" stroke="currentColor" stroke-width="1.5" fill="none"/>
            <path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>
        <h2 class="sb-section-title"><?= esc(t('answer')) ?></h2>
        <div class="sb-loading" id="loadingIndicator" hidden>
          <div class="sb-spinner"></div>
        </div>
      </div>

      <div class="sb-answer-panel">
        <div class="sb-answer-scroll" id="answerBox">
          <div class="sb-placeholder" id="placeholder">
            <svg class="sb-placeholder-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z" fill="currentColor" opacity="0.3"/>
            </svg>
            <p><?= esc(t('answer_placeholder')) ?></p>
          </div>
          <div class="sb-answer-content" id="answerContent" hidden></div>
        </div>
      </div>
    </section>

    <!-- Examples Section -->
    <section class="sb-section sb-examples-section">
      <h3 class="sb-examples-title"><?= esc(t('example_questions')) ?></h3>
      <div class="sb-examples-grid">
        <button class="sb-example-card" data-question="<?= esc(t('water_question_full')) ?>">
          <div class="sb-example-icon">💧</div>
          <p class="sb-example-text"><?= esc(t('water_question_short')) ?></p>
          <div class="sb-card-shine"></div>
        </button>
        <button class="sb-example-card" data-question="<?= esc(t('sower_question_full')) ?>">
          <div class="sb-example-icon">🌱</div>
          <p class="sb-example-text"><?= esc(t('sower_question_short')) ?></p>
          <div class="sb-card-shine"></div>
        </button>
        <button class="sb-example-card" data-question="<?= esc(t('spirit_question_full')) ?>">
          <div class="sb-example-icon">🍇</div>
          <p class="sb-example-text"><?= esc(t('spirit_question_short')) ?></p>
          <div class="sb-card-shine"></div>
        </button>
        <button class="sb-example-card" data-question="<?= esc(t('kingdom_question_full')) ?>">
          <div class="sb-example-icon">👑</div>
          <p class="sb-example-text"><?= esc(t('kingdom_question_short')) ?></p>
          <div class="sb-card-shine"></div>
        </button>
      </div>
    </section>
  </main>

  <script src="/aislimbybel/js/aislimbybel.js?v=<?= time() ?>"></script>

  <?php require_once __DIR__ . '/../header_footer/footer.php'; ?>

</body>
</html>