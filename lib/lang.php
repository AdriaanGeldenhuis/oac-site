<?php
function set_lang(string $lang): void {
  $_SESSION['lang'] = in_array($lang,['af','en','zu','xh','pt','st'], true) ? $lang : 'af';
}
function get_lang(): string { return $_SESSION['lang'] ?? 'af'; }
function lang_bootstrap(): void {
  $q = $_GET['lang'] ?? $_POST['lang'] ?? null;
  if ($q) set_lang($q);
  elseif (empty($_SESSION['lang']) && !empty($_SESSION['uid'])) {
    require_once __DIR__.'/db.php';
    $st = db()->prepare('SELECT language FROM users WHERE id=:i');
    $st->execute([':i'=>(int)$_SESSION['uid']]);
    $row = $st->fetch();
    set_lang($row['language'] ?? 'af');
  } elseif (empty($_SESSION['lang'])) {
    set_lang('af');
  }
}
function t(string $key): string {
  static $strings = [
    'af' => ['sign_in'=>'Teken in','email'=>'E-pos','password'=>'Wagwoord','remember'=>'Onthou my'],
    'en' => ['sign_in'=>'Sign in','email'=>'Email','password'=>'Password','remember'=>'Remember me'],
    'zu' => ['sign_in'=>'Ngena','email'=>'I-imeyili','password'=>'Iphasiwedi','remember'=>'Ngikhumbule'],
    'xh' => ['sign_in'=>'Ngena','email'=>'I-imeyile','password'=>'Iphasiwedi','remember'=>'Ndikhumbule'],
    'pt' => ['sign_in'=>'Entrar','email'=>'E-mail','password'=>'Senha','remember'=>'Lembrar-me'],
    'st' => ['sign_in'=>'Kena','email'=>'Imeile','password'=>'Phasewete','remember'=>'Nkgopole']
  ];
  $L = $strings[get_lang()] ?? $strings['en'];
  return $L[$key] ?? $key;
}
