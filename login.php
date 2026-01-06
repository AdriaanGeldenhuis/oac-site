<?php
declare(strict_types=1);
require_once __DIR__ . '/security/headers.php';
require_once __DIR__ . '/security/config.php';
require_once __DIR__ . '/security/session.php';
require_once __DIR__ . '/security/csrf.php';
require_once __DIR__ . '/security/rate_limit.php';
require_once __DIR__ . '/includes/languages.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!rate_limit_check('login:' . $ip, 5, 15)) {
        $errors[] = 'Too many attempts. Please try again in 15 minutes.';
    }

    if (!csrf_verify($_POST['csrf'] ?? '')) {
        $errors[] = 'Invalid session. Please try again.';
    }

    $email = trim($_POST['email'] ?? '');
    $pass = $_POST['password'] ?? '';

    if (empty($email) || empty($pass)) {
        $errors[] = 'Email and password are required.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare(
            "SELECT id, email, password_hash, name, surname, status, language
             FROM users WHERE email = ? LIMIT 1"
        );
        $stmt->execute([$email]);
        $u = $stmt->fetch();

        if (!$u || !password_verify($pass, $u['password_hash'] ?? '')) {
            $errors[] = 'Invalid credentials.';
        } elseif ($u['status'] !== 'approved') {
            $errors[] = 'Account not yet approved.';
        } else {
            // ✅ SUCCESS - Login
            rate_limit_reset('login:' . $ip);
            
            // ✅ Regenerate session
            session_regenerate_id(true);
            
            // Set session data
            $_SESSION['user_id'] = (int)$u['id'];
            $_SESSION['user_email'] = $u['email'];
            $_SESSION['user_name'] = $u['name'];
            $_SESSION['language'] = validate_language($u['language'] ?? 'en');
            $_SESSION['created_at'] = time();
            $_SESSION['last_regenerate'] = time();
            $_SESSION['last_activity'] = time();

            // ✅ Store session ID in database
            $newSid = session_id();
            $stmt = $pdo->prepare("UPDATE users SET web_session_key = ?, last_login_at = NOW() WHERE id = ?");
            $stmt->execute([$newSid, (int)$u['id']]);

            // ✅ FORCE persistent cookie (30 days) - TRIPLE SET
            $is_secure = (
                (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
            );
            
            // Method 1: setcookie
            setcookie(
                session_name(),
                $newSid,
                [
                    'expires'  => time() + 2592000, // 30 days
                    'path'     => '/',
                    'domain'   => '',
                    'secure'   => $is_secure,
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]
            );
            
            // Method 2: setrawcookie (backup)
            setrawcookie(
                session_name(),
                $newSid,
                time() + 2592000,
                '/',
                '',
                $is_secure,
                true
            );
            
            // Method 3: Header (last resort)
            $cookie_header = session_name() . '=' . $newSid . '; ';
            $cookie_header .= 'expires=' . gmdate('D, d M Y H:i:s T', time() + 2592000) . '; ';
            $cookie_header .= 'Max-Age=2592000; ';
            $cookie_header .= 'path=/; ';
            if ($is_secure) $cookie_header .= 'secure; ';
            $cookie_header .= 'HttpOnly; ';
            $cookie_header .= 'SameSite=Lax';
            header('Set-Cookie: ' . $cookie_header, false);
            
            error_log("✅ LOGIN SUCCESS: User {$u['id']} logged in with session: $newSid");

            header('Location: /welcome.php');
            exit;
        }
    }
}

$csrf = csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login</title>
<link rel="stylesheet" href="/assets/css/login.css">
</head>
<body>
<div class="login-container">
  <div class="logo-container">
    <img src="/assets/logo/bible.webp" alt="Logo">
  </div>
  
  <h1>Login</h1>
  
  <?php if ($errors): ?>
    <div class="errors">
      <?php foreach ($errors as $e): ?>
        <p><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></p>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  
  <form method="post" autocomplete="off">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
    
    <label>Email<br><input type="email" name="email" required></label>

    <div class="password-wrapper">
      <label>
        Password
        <span class="toggle-pw" onclick="togglePw('pw')">👁️</span>
      </label>
      <input type="password" id="pw" name="password" required>
    </div>

    <button type="submit">Sign in</button>
  </form>

  <p><a href="/register.php">Create account</a> | <a href="/forgot.php">Forgot password?</a></p>
</div>

<script>
function togglePw(id) {
  const pw = document.getElementById(id);
  pw.type = pw.type === 'password' ? 'text' : 'password';
}
</script>
</body>
</html>