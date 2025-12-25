<?php
declare(strict_types=1);
require_once __DIR__ . '/security/headers.php';
require_once __DIR__ . '/security/config.php';
require_once __DIR__ . '/security/session.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== SESSION DEBUG ===\n\n";

// 1. PHP Session Info
echo "1. PHP SESSION INFO:\n";
echo "   Session ID: " . session_id() . "\n";
echo "   Session Name: " . session_name() . "\n";
echo "   Session Status: " . (session_status() === PHP_SESSION_ACTIVE ? 'ACTIVE' : 'INACTIVE') . "\n";
echo "   Session Save Path: " . session_save_path() . "\n\n";

// 2. Cookie Info
echo "2. COOKIE INFO:\n";
$params = session_get_cookie_params();
echo "   Cookie Lifetime: " . $params['lifetime'] . " seconds (" . ($params['lifetime'] / 86400) . " days)\n";
echo "   Cookie Path: " . $params['path'] . "\n";
echo "   Cookie Domain: " . ($params['domain'] ?: '(empty)') . "\n";
echo "   Cookie Secure: " . ($params['secure'] ? 'YES' : 'NO') . "\n";
echo "   Cookie HttpOnly: " . ($params['httponly'] ? 'YES' : 'NO') . "\n";
echo "   Cookie SameSite: " . ($params['samesite'] ?? 'Not set') . "\n\n";

// 3. Current Cookies
echo "3. CURRENT COOKIES:\n";
if (empty($_COOKIE)) {
    echo "   No cookies found!\n\n";
} else {
    foreach ($_COOKIE as $name => $value) {
        if ($name === session_name()) {
            echo "   ✅ $name = $value (SESSION COOKIE)\n";
        } else {
            echo "   $name = $value\n";
        }
    }
    echo "\n";
}

// 4. Session Data
echo "4. SESSION DATA:\n";
if (empty($_SESSION)) {
    echo "   No session data!\n\n";
} else {
    foreach ($_SESSION as $key => $val) {
        echo "   $key = " . (is_scalar($val) ? $val : json_encode($val)) . "\n";
    }
    echo "\n";
}

// 5. Database Check
echo "5. DATABASE CHECK:\n";
try {
    if (!empty($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("SELECT id, email, web_session_key, last_login_at FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if ($user) {
            echo "   User ID: " . $user['id'] . "\n";
            echo "   Email: " . $user['email'] . "\n";
            echo "   DB Session Key: " . ($user['web_session_key'] ?: 'NULL') . "\n";
            echo "   Current Session ID: " . session_id() . "\n";
            echo "   Match: " . ($user['web_session_key'] === session_id() ? '✅ YES' : '❌ NO') . "\n";
            echo "   Last Login: " . ($user['last_login_at'] ?: 'Never') . "\n";
        } else {
            echo "   ❌ User not found in database!\n";
        }
    } else {
        echo "   Not logged in (no user_id in session)\n";
    }
} catch (Throwable $e) {
    echo "   ❌ Database error: " . $e->getMessage() . "\n";
}
echo "\n";

// 6. Server Info
echo "6. SERVER INFO:\n";
echo "   HTTPS: " . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'YES' : 'NO') . "\n";
echo "   Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "\n";
echo "   PHP Version: " . PHP_VERSION . "\n";
echo "   Session Handler: " . ini_get('session.save_handler') . "\n\n";

// 7. PHP.ini Session Settings
echo "7. PHP.INI SESSION SETTINGS:\n";
$settings = [
    'session.gc_maxlifetime',
    'session.cookie_lifetime',
    'session.use_strict_mode',
    'session.use_cookies',
    'session.use_only_cookies',
    'session.cookie_httponly',
    'session.cookie_secure',
    'session.cookie_samesite'
];
foreach ($settings as $setting) {
    echo "   $setting = " . ini_get($setting) . "\n";
}

echo "\n=== END DEBUG ===\n";