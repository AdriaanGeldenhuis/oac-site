<?php
declare(strict_types=1);

require_once __DIR__ . '/session_handler.php';

/**
 * ✅ PERSISTENT DATABASE SESSION (30 DAYS)
 * Sessions stored in database, survive server restarts
 */

// ✅ Session lifetime (30 days)
$session_lifetime = 2592000; // 30 days
$session_name = 'OACSESS';

// ✅ Detect HTTPS
$is_secure = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
    (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) ||
    (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
);

// ✅ Configure session BEFORE session_start()
ini_set('session.gc_maxlifetime', (string)$session_lifetime);
ini_set('session.use_strict_mode', '1');
ini_set('session.use_cookies', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', $is_secure ? '1' : '0');
ini_set('session.cookie_samesite', 'Lax');

// ✅ Set cookie parameters (30 days persistent)
session_set_cookie_params([
    'lifetime' => $session_lifetime,
    'path'     => '/',
    'domain'   => '',
    'secure'   => $is_secure,
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_name($session_name);

// ✅ CRITICAL: Use custom database session handler
global $pdo;
if (isset($pdo) && $pdo instanceof PDO) {
    $handler = new DatabaseSessionHandler($pdo, $session_lifetime);
    session_set_save_handler($handler, true);
    error_log("✅ Database session handler registered");
} else {
    error_log("❌ WARNING: PDO not available, using default file handler");
}

// ✅ Start session
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
    error_log("✅ Session started: " . session_id());
}

// ✅ FORCE cookie to persist (30 days)
$current_sid = session_id();
if ($current_sid) {
    setcookie(
        $session_name,
        $current_sid,
        [
            'expires'  => time() + $session_lifetime,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $is_secure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]
    );
}

// ✅ Initialize session timestamps
if (!isset($_SESSION['created_at'])) {
    $_SESSION['created_at'] = time();
    error_log("✅ New session created: " . session_id());
}

if (!isset($_SESSION['last_activity'])) {
    $_SESSION['last_activity'] = time();
}

if (!isset($_SESSION['last_regenerate'])) {
    $_SESSION['last_regenerate'] = time();
}

// ✅ Update last activity on every request
$_SESSION['last_activity'] = time();

// ✅ Regenerate session ID every 24 hours (security)
if (time() - $_SESSION['last_regenerate'] > 86400) {
    $old_sid = session_id();
    $old_session_data = $_SESSION;
    
    session_regenerate_id(true);
    
    $_SESSION = $old_session_data;
    $_SESSION['last_regenerate'] = time();
    
    $new_sid = session_id();
    
    // Update cookie with new ID
    setcookie(
        $session_name,
        $new_sid,
        [
            'expires'  => time() + $session_lifetime,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $is_secure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]
    );
    
    // Update DB user record if logged in
    if (!empty($_SESSION['user_id'])) {
        try {
            if (isset($pdo) && $pdo instanceof PDO) {
                $stmt = $pdo->prepare("UPDATE users SET web_session_key = ? WHERE id = ?");
                $stmt->execute([$new_sid, (int)$_SESSION['user_id']]);
                error_log("✅ Session regenerated: $old_sid -> $new_sid (user: {$_SESSION['user_id']})");
            }
        } catch (Throwable $e) {
            error_log('Session regeneration DB update failed: ' . $e->getMessage());
        }
    }
}

// ✅ AUTO-RESTORE SESSION (if session data lost but cookie exists)
if (empty($_SESSION['user_id']) && isset($_COOKIE[$session_name])) {
    try {
        if (isset($pdo) && $pdo instanceof PDO) {
            $sid = $_COOKIE[$session_name];
            
            // Try to restore from users table
            $stmt = $pdo->prepare("
                SELECT id, email, name, surname, web_session_key
                FROM users 
                WHERE web_session_key = ? AND status = 'approved' 
                LIMIT 1
            ");
            $stmt->execute([$sid]);
            $user = $stmt->fetch();
            
            if ($user && $user['web_session_key'] === $sid) {
                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['created_at'] = time();
                $_SESSION['last_regenerate'] = time();
                $_SESSION['last_activity'] = time();
                
                error_log("✅ AUTO-RESTORE: Session restored from users table for: {$user['email']} (ID: {$user['id']})");
            } else {
                error_log("⚠️ AUTO-RESTORE: No matching user found for session: $sid");
            }
        }
    } catch (Throwable $e) {
        error_log('Auto-restore session failed: ' . $e->getMessage());
    }
}