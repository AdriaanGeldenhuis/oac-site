<?php
// =====================================================================
// /admin/fcm-debug.php — FCM Debug Page
// =====================================================================

require_once __DIR__ . '/../security/auth_gate.php';
require_once __DIR__ . '/config/fcm_config.php';

// Office bearers only (amp 1-6) - this page exposes FCM config details
$gateStmt = $pdo->prepare('SELECT amp_id FROM users WHERE id = ? LIMIT 1');
$gateStmt->execute([(int)($_SESSION['user_id'] ?? 0)]);
$gateAmp = (int)($gateStmt->fetchColumn() ?: 0);
if ($gateAmp < 1 || $gateAmp > 6) {
    header('Location: /admin/index.php');
    exit;
}

require_once __DIR__ . '/../includes/languages.php';
require_once __DIR__ . '/../includes/admin_page.php';
$lang = validate_language((string)($_SESSION['language'] ?? 'af'));
$pageTitle = 'FCM Debug';

// Get user tokens
global $pdo;
$userTokens = [];
$tokenError = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM fcm_tokens WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $userTokens = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('FCM debug tokens: ' . $e->getMessage());
    $tokenError = 'unavailable';
}

// Test access token with detailed debugging
$accessToken = null;
$accessTokenError = null;
$debugInfo = [];

try {
    // Check JSON validity
    $jsonContent = file_get_contents(FCM_SERVICE_ACCOUNT_FILE);
    $serviceAccount = json_decode($jsonContent, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        $debugInfo['json_error'] = json_last_error_msg();
    } else {
        $debugInfo['json_valid'] = true;
        $debugInfo['has_private_key'] = isset($serviceAccount['private_key']);
        $debugInfo['has_client_email'] = isset($serviceAccount['client_email']);
        $debugInfo['client_email'] = $serviceAccount['client_email'] ?? 'N/A';

        // Test private key parsing
        if (isset($serviceAccount['private_key'])) {
            $privateKey = openssl_pkey_get_private($serviceAccount['private_key']);
            if ($privateKey) {
                $debugInfo['private_key_valid'] = true;
            } else {
                $debugInfo['private_key_valid'] = false;
                $debugInfo['openssl_error'] = openssl_error_string();
            }
        }
    }

    $accessToken = getFcmAccessToken();
    if (!$accessToken) {
        $accessTokenError = $GLOBALS['fcm_last_error'] ?? 'Could not get access token';
    }
} catch (Exception $e) {
    $accessTokenError = $e->getMessage();
}

?><!doctype html>
<html lang="<?= admin_e($lang) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>FCM Debug</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;1,500;1,600&family=Parisienne&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= admin_asset('/admin/css/admin.css') ?>">
    <link rel="stylesheet" href="<?= admin_asset('/admin/css/fcm-debug.css') ?>">
    <link rel="stylesheet" href="<?= admin_asset('/admin/css/ui.css') ?>">
</head>
<body class="admin-body" data-tab="settings">
<?php include __DIR__ . '/../header_footer/header.php'; ?>

<div class="admin-aura" aria-hidden="true"><div class="admin-rays"></div><div class="admin-halo"></div></div>

<main class="admin-main">
<section class="admin-hero" aria-labelledby="fcmTitle">
    <p class="admin-eyebrow">
        <svg class="admin-eyebrow-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.89 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/></svg>
        <span>Push notifications</span>
    </p>
    <h2 class="admin-title" id="fcmTitle"><span>FCM Debug</span></h2>
    <p class="admin-sub">Is this phone ready to be told things?</p>
</section>

<div class="debug-container">

    <!-- Server Status -->
    <div class="debug-section">
        <h3>1. Server Status</h3>
        <p><strong>Service Account File:</strong>
            <?php if (file_exists(FCM_SERVICE_ACCOUNT_FILE)): ?>
                <span class="status-ok">✓ Found</span>
            <?php else: ?>
                <span class="status-error">✗ Not found</span>
            <?php endif; ?>
        </p>
        <p><strong>OAuth Access Token:</strong>
            <?php if ($accessToken): ?>
                <span class="status-ok">✓ Valid</span>
                
            <?php else: ?>
                <span class="status-error">✗ Failed: <?= htmlspecialchars($accessTokenError ?? 'Unknown error') ?></span>
            <?php endif; ?>
        </p>

        <?php if (!empty($debugInfo)): ?>
        <div class="debug-details">
            <strong>Debug Details:</strong><br>
            <?php foreach ($debugInfo as $key => $value): ?>
                <span class="<?= $value === true ? 'status-ok' : ($value === false ? 'status-error' : '') ?>">
                    <?= htmlspecialchars($key) ?>: <?= is_bool($value) ? ($value ? '✓ Yes' : '✗ No') : htmlspecialchars((string)$value) ?>
                </span><br>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- User Tokens -->
    <div class="debug-section">
        <h3>2. Your Registered FCM Tokens</h3>
        <?php if ($tokenError): ?>
            <p class="status-error">Could not read the tokens (see the server log).</p>
        <?php elseif (empty($userTokens)): ?>
            <p class="status-warning">⚠️ No tokens registered for your account.</p>
            <p>This means the native app hasn't sent its FCM token to the server yet.</p>
        <?php else: ?>
            <p class="status-ok">✓ <?= count($userTokens) ?> token(s) registered</p>
            <?php foreach ($userTokens as $t): ?>
                <div class="debug-details">
                    <strong>Token:</strong> <?= htmlspecialchars(substr((string)$t['token'], 0, 30)) ?>...<br>
                    <strong>Device:</strong> <?= htmlspecialchars($t['device_info'] ?? 'Unknown') ?><br>
                    <strong>Updated:</strong> <?= htmlspecialchars((string)$t['updated_at']) ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- JavaScript Bridge Status -->
    <div class="debug-section">
        <h3>3. JavaScript Bridge Status</h3>
        <p><strong>NativeApp object:</strong> <span id="native-status">Checking...</span></p>
        <p><strong>FCM Token from native:</strong> <span id="token-status">Checking...</span></p>
        <p><strong>LocalStorage token:</strong> <span id="storage-status">Checking...</span></p>

        <button class="admin-btn admin-btn-primary" onclick="manualRegister()">🔄 Manual Token Registration</button>
    </div>

    <!-- Test Push -->
    <div class="debug-section">
        <h3>4. Send Test Push (Direct FCM)</h3>
        <?php if (empty($userTokens)): ?>
            <p class="status-warning">⚠️ Cannot test - no tokens registered</p>
        <?php else: ?>
            <button class="admin-btn admin-btn-primary" onclick="sendTestPush()">📤 Direct FCM Test</button>
            <p id="push-result" class="debug-result"></p>
        <?php endif; ?>
    </div>

    <!-- Test via createAdminNotification -->
    <div class="debug-section">
        <h3>5. Test via Notification Helper</h3>
        <p>This tests the full notification flow (createAdminNotification → FCM)</p>
        <button class="admin-btn admin-btn-primary" onclick="testNotificationFlow()">📨 Test Notification Flow</button>
        <p id="notif-result" class="debug-result"></p>
    </div>

    <!-- Console Log -->
    <div class="debug-section">
        <h3>6. Console Log</h3>
        <div id="log-output" class="code-block">Logs will appear here...\n</div>
    </div>
</div>

<script>
// Override console.log to show in our debug area
const logOutput = document.getElementById('log-output');
const originalLog = console.log;
const originalError = console.error;

function addLog(type, ...args) {
    const time = new Date().toLocaleTimeString();
    const msg = args.map(a => typeof a === 'object' ? JSON.stringify(a, null, 2) : a).join(' ');
    logOutput.textContent += `[${time}] ${type}: ${msg}\n`;
    logOutput.scrollTop = logOutput.scrollHeight;
}

console.log = function(...args) {
    addLog('LOG', ...args);
    originalLog.apply(console, args);
};

console.error = function(...args) {
    addLog('ERROR', ...args);
    originalError.apply(console, args);
};

// Status lines are built with textContent, never innerHTML: some of the text comes from the server or the device
function setStatus(el, cls, text) {
    const span = document.createElement('span');
    span.className = cls;
    span.textContent = text;
    el.replaceChildren(span);
}

// Check native app status
document.addEventListener('DOMContentLoaded', function() {
    // Check NativeApp
    const nativeStatus = document.getElementById('native-status');
    if (typeof window.NativeApp !== 'undefined') {
        setStatus(nativeStatus, 'status-ok', '✓ Available');
        console.log('NativeApp object is available');

        // List available methods
        const methods = [];
        for (let key in window.NativeApp) {
            methods.push(key);
        }
        console.log('NativeApp methods:', methods);
    } else {
        setStatus(nativeStatus, 'status-error', '✗ Not available (not running in native app)');
        console.log('NativeApp not available - are you running in the native Android app?');
    }

    // Check FCM token
    const tokenStatus = document.getElementById('token-status');
    if (typeof window.NativeApp !== 'undefined' && typeof window.NativeApp.getFcmToken === 'function') {
        try {
            const token = window.NativeApp.getFcmToken();
            if (token) {
                setStatus(tokenStatus, 'status-ok', '✓ ' + token.substring(0, 30) + '...');
                console.log('FCM Token:', token);
            } else {
                setStatus(tokenStatus, 'status-warning', '⚠️ Empty token returned');
                console.log('getFcmToken returned empty');
            }
        } catch (e) {
            setStatus(tokenStatus, 'status-error', '✗ Error: ' + e.message);
            console.error('Error calling getFcmToken:', e);
        }
    } else {
        setStatus(tokenStatus, 'status-error', '✗ getFcmToken not available');
    }

    // Check localStorage
    const storageStatus = document.getElementById('storage-status');
    const storedToken = localStorage.getItem('fcm_token');
    const registered = localStorage.getItem('fcm_token_registered');
    if (storedToken) {
        setStatus(storageStatus, 'status-ok', '✓ Stored (registered: ' + registered + ')');
        console.log('Stored token:', storedToken.substring(0, 30) + '...');
    } else {
        setStatus(storageStatus, 'status-warning', '⚠️ No token in localStorage');
    }
});

// Manual registration
async function manualRegister() {
    console.log('Starting manual registration...');

    if (typeof window.NativeApp === 'undefined') {
        console.error('NativeApp not available');
        OACUI.toast('Not running in native app!', 'error');
        return;
    }

    try {
        const token = window.NativeApp.getFcmToken();
        console.log('Got token:', token ? token.substring(0, 30) + '...' : 'empty');

        if (!token) {
            OACUI.toast('No FCM token available from native app', 'error');
            return;
        }

        console.log('Sending to server...');
        const response = await fetch('/admin/api/fcm/register_token.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                token: token,
                device_info: navigator.userAgent
            })
        });

        const data = await response.json();
        console.log('Server response:', data);

        if (data.success) {
            OACUI.toast('Token registered! Refresh the page to see it.', 'success');
            localStorage.setItem('fcm_token', token);
            localStorage.setItem('fcm_token_registered', 'true');
        } else {
            OACUI.toast('Failed: ' + (data.error || ''), 'error');
        }
    } catch (e) {
        console.error('Registration error:', e);
        OACUI.toast('Error: ' + e.message, 'error');
    }
}

// Send test push
async function sendTestPush() {
    const resultEl = document.getElementById('push-result');
    resultEl.textContent = 'Sending...';

    try {
        const response = await fetch('/admin/api/fcm/test.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ send_test: true })
        });

        const data = await response.json();
        console.log('Push test result:', data);

        if (data.test_result && data.test_result.success) {
            setStatus(resultEl, 'status-ok', '✓ Push sent! Check your phone.');
        } else {
            setStatus(resultEl, 'status-error', '✗ Failed: ' + (data.test_result?.error || 'Unknown error'));
        }
    } catch (e) {
        console.error('Test push error:', e);
        setStatus(resultEl, 'status-error', '✗ Error: ' + e.message);
    }
}

// Test notification flow (via createAdminNotification)
async function testNotificationFlow() {
    const resultEl = document.getElementById('notif-result');
    resultEl.textContent = 'Testing notification flow...';

    try {
        const response = await fetch('/admin/api/fcm/test_notification.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ test: true })
        });

        const data = await response.json();
        console.log('Notification flow test result:', data);

        if (data.success) {
            setStatus(resultEl, 'status-ok', '✓ ' + data.message);
        } else {
            setStatus(resultEl, 'status-error', '✗ Failed: ' + (data.error || 'Unknown error'));
        }
    } catch (e) {
        console.error('Notification flow test error:', e);
        setStatus(resultEl, 'status-error', '✗ Error: ' + e.message);
    }
}
</script>

</main>

<script src="<?= admin_asset('/admin/js/ui.js') ?>"></script>
<?php include __DIR__ . '/../header_footer/footer.php'; ?>
</body>
</html>
