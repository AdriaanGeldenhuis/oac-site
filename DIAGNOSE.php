<?php
// ============================================================================
// FULL SYSTEM DIAGNOSTIC - Run this first before anything else
// ============================================================================

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

?><!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>System Diagnostic</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1a1a1a; color: #0f0; }
        .pass { color: #0f0; }
        .fail { color: #f00; font-weight: bold; }
        .warn { color: #ff0; }
        pre { background: #000; padding: 10px; border-left: 3px solid #0f0; margin: 10px 0; }
        h2 { color: #0ff; margin-top: 30px; }
        hr { border: 1px solid #333; margin: 20px 0; }
    </style>
</head>
<body>
<h1>🔍 SYSTEM DIAGNOSTIC</h1>

<?php

function test_step($name, $callback) {
    echo "<h2>$name</h2>";
    try {
        $result = $callback();
        if ($result === true) {
            echo "<div class='pass'>✓ PASS</div>";
        } else {
            echo "<div class='warn'>⚠ $result</div>";
        }
    } catch (Throwable $e) {
        echo "<div class='fail'>✗ FAIL</div>";
        echo "<pre>";
        echo "ERROR: " . $e->getMessage() . "\n";
        echo "FILE: " . $e->getFile() . "\n";
        echo "LINE: " . $e->getLine() . "\n\n";
        echo "TRACE:\n" . $e->getTraceAsString();
        echo "</pre>";
        return false;
    }
    return true;
}

// TEST 1: PHP Basic Info
test_step("1. PHP Environment", function() {
    echo "<pre>";
    echo "PHP Version: " . phpversion() . "\n";
    echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
    echo "Current File: " . __FILE__ . "\n";
    echo "Current Dir: " . __DIR__ . "\n";
    echo "</pre>";
    return true;
});

// TEST 2: File Structure
test_step("2. File Structure", function() {
    $required = [
        '/security/config.php',
        '/security/session.php',
        '/security/auth_gate.php',
        '/welcome.php',
        '/logout.php',
        '/aislimbybel/aislimbybel.php',
        '/header_footer/header.php',
        '/header_footer/footer.php',
    ];
    
    $all_exist = true;
    foreach ($required as $file) {
        $path = __DIR__ . $file;
        $exists = file_exists($path);
        $color = $exists ? 'pass' : 'fail';
        echo "<div class='$color'>" . ($exists ? '✓' : '✗') . " $file</div>";
        if (!$exists) $all_exist = false;
    }
    
    return $all_exist ? true : "Some files missing";
});

// TEST 3: Load config.php
$config_loaded = test_step("3. Load security/config.php", function() {
    require_once __DIR__ . '/security/config.php';
    echo "<pre>";
    echo "OAC_DEBUG: " . (defined('OAC_DEBUG') ? (OAC_DEBUG ? 'true' : 'false') : 'NOT DEFINED') . "\n";
    echo "SESSION_LIFETIME: " . (defined('SESSION_LIFETIME') ? SESSION_LIFETIME : 'NOT DEFINED') . "\n";
    echo "SESSION_COOKIE_NAME: " . (defined('SESSION_COOKIE_NAME') ? SESSION_COOKIE_NAME : 'NOT DEFINED') . "\n";
    echo "</pre>";
    return true;
});

if (!$config_loaded) {
    echo "<h2 class='fail'>⚠ STOPPED: Cannot proceed without config.php</h2>";
    exit;
}

// TEST 4: Database Connection
test_step("4. Database Connection", function() {
    global $pdo;
    if (!isset($pdo)) {
        throw new Exception('$pdo not defined after config.php load');
    }
    
    $stmt = $pdo->query("SELECT 1 as test");
    $result = $stmt->fetch();
    
    echo "<pre>";
    echo "PDO Object: " . get_class($pdo) . "\n";
    echo "Test Query: " . ($result['test'] === 1 ? 'SUCCESS' : 'FAILED') . "\n";
    echo "</pre>";
    
    return true;
});

// TEST 5: Users table
test_step("5. Users Table Structure", function() {
    global $pdo;
    
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $required = ['id', 'email', 'name', 'surname', 'web_session_key'];
    $missing = array_diff($required, $columns);
    
    echo "<pre>";
    echo "Columns found: " . implode(', ', $columns) . "\n";
    if (!empty($missing)) {
        echo "\nMISSING: " . implode(', ', $missing) . "\n";
    }
    echo "</pre>";
    
    return empty($missing) ? true : "Missing columns: " . implode(', ', $missing);
});

// TEST 6: Load session.php
test_step("6. Load security/session.php", function() {
    require_once __DIR__ . '/security/session.php';
    
    echo "<pre>";
    echo "Session Status: " . (session_status() === PHP_SESSION_ACTIVE ? 'ACTIVE' : 'INACTIVE') . "\n";
    echo "Session Name: " . session_name() . "\n";
    echo "Session ID: " . (session_id() ?: 'NOT SET') . "\n";
    echo "Session Data:\n";
    print_r($_SESSION);
    echo "</pre>";
    
    return true;
});

// TEST 7: Load auth_gate.php
test_step("7. Load security/auth_gate.php", function() {
    require_once __DIR__ . '/security/auth_gate.php';
    
    echo "<pre>";
    echo "Functions defined:\n";
    echo "- auth_user_id: " . (function_exists('auth_user_id') ? '✓' : '✗') . "\n";
    echo "- auth_user_name: " . (function_exists('auth_user_name') ? '✓' : '✗') . "\n";
    echo "- auth_user_email: " . (function_exists('auth_user_email') ? '✓' : '✗') . "\n";
    echo "</pre>";
    
    return true;
});

// TEST 8: Auth State
test_step("8. Authentication State", function() {
    if (!function_exists('auth_user_id')) {
        return "auth_user_id() not available";
    }
    
    $user_id = auth_user_id();
    
    echo "<pre>";
    if ($user_id) {
        echo "Status: LOGGED IN\n";
        echo "User ID: " . $user_id . "\n";
        echo "User Name: " . (function_exists('auth_user_name') ? auth_user_name() : 'N/A') . "\n";
        echo "User Email: " . (function_exists('auth_user_email') ? auth_user_email() : 'N/A') . "\n";
    } else {
        echo "Status: NOT LOGGED IN\n";
        echo "Session: " . print_r($_SESSION, true) . "\n";
    }
    echo "</pre>";
    
    return $user_id ? true : "User not logged in (expected if testing from different session)";
});

// TEST 9: Test welcome.php include
test_step("9. Test welcome.php simulation", function() {
    // Don't actually include it, just test if we can
    $welcome = __DIR__ . '/welcome.php';
    if (!file_exists($welcome)) {
        return "welcome.php not found";
    }
    
    echo "<pre>";
    echo "File exists: ✓\n";
    echo "Readable: " . (is_readable($welcome) ? '✓' : '✗') . "\n";
    echo "Size: " . filesize($welcome) . " bytes\n";
    echo "</pre>";
    
    return true;
});

// TEST 10: Test aislimbybel.php paths
test_step("10. Test aislimbybel.php paths", function() {
    $aislimbybel_dir = __DIR__ . '/aislimbybel';
    
    echo "<pre>";
    echo "From /aislimbybel/aislimbybel.php perspective:\n\n";
    
    $tests = [
        'auth_gate.php' => $aislimbybel_dir . '/../security/auth_gate.php',
        'config.php (OpenAI)' => $aislimbybel_dir . '/config.php',
        'header.php' => $aislimbybel_dir . '/../header_footer/header.php',
        'footer.php' => $aislimbybel_dir . '/../header_footer/footer.php',
        'CSS' => $aislimbybel_dir . '/css/aislimbybel.css',
    ];
    
    $all_exist = true;
    foreach ($tests as $name => $path) {
        $exists = file_exists($path);
        echo ($exists ? '✓' : '✗') . " $name\n";
        echo "   Path: $path\n";
        if (!$exists) $all_exist = false;
    }
    echo "</pre>";
    
    return $all_exist ? true : "Some aislimbybel files missing";
});

?>

<hr>
<h2>🎯 CONCLUSION</h2>
<p>Review the results above. If all tests pass, the system should work.</p>
<p>If welcome.php works but aislimbybel.php doesn't, the issue is likely in the aislimbybel-specific files.</p>

<hr>
<h2>📋 NEXT STEPS</h2>
<ol style="color: #fff;">
    <li>If auth_gate functions are missing → Send me /security/auth_gate.php contents</li>
    <li>If aislimbybel paths fail → Check file locations</li>
    <li>If you're not logged in → Go to /welcome.php first, then retry</li>
    <li>If database fails → Check DB credentials in config.php</li>
</ol>

</body>
</html>