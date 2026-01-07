<?php
// =====================================================================
// /admin/fcm-debug.php — FCM Debug Page
// =====================================================================

require_once __DIR__ . '/../security/auth_gate.php';
require_once __DIR__ . '/config/fcm_config.php';

$lang = $_SESSION['lang'] ?? 'af';
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
    $tokenError = $e->getMessage();
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
        $accessTokenError = 'Could not get access token';
    }
} catch (Exception $e) {
    $accessTokenError = $e->getMessage();
}

include __DIR__ . '/../header_footer/header.php';
?>

<style>
.debug-container {
    max-width: 800px;
    margin: 20px auto;
    padding: 20px;
}
.debug-section {
    background: var(--color-card-bg, #fff);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.debug-section h3 {
    margin-top: 0;
    color: var(--color-primary, #4A90A4);
    border-bottom: 2px solid var(--color-primary, #4A90A4);
    padding-bottom: 10px;
}
.status-ok { color: #28a745; font-weight: bold; }
.status-error { color: #dc3545; font-weight: bold; }
.status-warning { color: #ffc107; font-weight: bold; }
.code-block {
    background: #1a1a2e;
    color: #0f0;
    padding: 15px;
    border-radius: 8px;
    font-family: monospace;
    font-size: 12px;
    overflow-x: auto;
    white-space: pre-wrap;
    word-break: break-all;
}
.btn-test {
    background: var(--color-primary, #4A90A4);
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 16px;
}
.btn-test:hover {
    opacity: 0.9;
}
#log-output {
    min-height: 200px;
}
</style>

<div class="debug-container">
    <h2>🔧 FCM Push Notification Debug</h2>

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
                <br><small style="color:#666;">Token: <?= substr($accessToken, 0, 50) ?>...</small>
            <?php else: ?>
                <span class="status-error">✗ Failed: <?= htmlspecialchars($accessTokenError ?? 'Unknown error') ?></span>
            <?php endif; ?>
        </p>

        <?php if (!empty($debugInfo)): ?>
        <div style="background:#f5f5f5; padding:15px; margin-top:15px; border-radius:8px; font-size:13px;">
            <strong>Debug Details:</strong><br>
            <?php foreach ($debugInfo as $key => $value): ?>
                <span style="color:<?= $value === true ? 'green' : ($value === false ? 'red' : '#333') ?>;">
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
            <p class="status-error">Error: <?= htmlspecialchars($tokenError) ?></p>
        <?php elseif (empty($userTokens)): ?>
            <p class="status-warning">⚠️ No tokens registered for your account.</p>
            <p>This means the native app hasn't sent its FCM token to the server yet.</p>
        <?php else: ?>
            <p class="status-ok">✓ <?= count($userTokens) ?> token(s) registered</p>
            <?php foreach ($userTokens as $t): ?>
                <div style="background:#f5f5f5; padding:10px; margin:10px 0; border-radius:5px;">
                    <strong>Token:</strong> <?= substr($t['token'], 0, 30) ?>...<br>
                    <strong>Device:</strong> <?= htmlspecialchars($t['device_info'] ?? 'Unknown') ?><br>
                    <strong>Updated:</strong> <?= $t['updated_at'] ?>
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

        <button class="btn-test" onclick="manualRegister()">🔄 Manual Token Registration</button>
    </div>

    <!-- Test Push -->
    <div class="debug-section">
        <h3>4. Send Test Push</h3>
        <?php if (empty($userTokens)): ?>
            <p class="status-warning">⚠️ Cannot test - no tokens registered</p>
        <?php else: ?>
            <button class="btn-test" onclick="sendTestPush()">📤 Send Test Push to My Phone</button>
            <p id="push-result" style="margin-top:10px;"></p>
        <?php endif; ?>
    </div>

    <!-- Console Log -->
    <div class="debug-section">
        <h3>5. Console Log</h3>
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

// Check native app status
document.addEventListener('DOMContentLoaded', function() {
    // Check NativeApp
    const nativeStatus = document.getElementById('native-status');
    if (typeof window.NativeApp !== 'undefined') {
        nativeStatus.innerHTML = '<span class="status-ok">✓ Available</span>';
        console.log('NativeApp object is available');

        // List available methods
        const methods = [];
        for (let key in window.NativeApp) {
            methods.push(key);
        }
        console.log('NativeApp methods:', methods);
    } else {
        nativeStatus.innerHTML = '<span class="status-error">✗ Not available (not running in native app)</span>';
        console.log('NativeApp not available - are you running in the native Android app?');
    }

    // Check FCM token
    const tokenStatus = document.getElementById('token-status');
    if (typeof window.NativeApp !== 'undefined' && typeof window.NativeApp.getFcmToken === 'function') {
        try {
            const token = window.NativeApp.getFcmToken();
            if (token) {
                tokenStatus.innerHTML = '<span class="status-ok">✓ ' + token.substring(0, 30) + '...</span>';
                console.log('FCM Token:', token);
            } else {
                tokenStatus.innerHTML = '<span class="status-warning">⚠️ Empty token returned</span>';
                console.log('getFcmToken returned empty');
            }
        } catch (e) {
            tokenStatus.innerHTML = '<span class="status-error">✗ Error: ' + e.message + '</span>';
            console.error('Error calling getFcmToken:', e);
        }
    } else {
        tokenStatus.innerHTML = '<span class="status-error">✗ getFcmToken not available</span>';
    }

    // Check localStorage
    const storageStatus = document.getElementById('storage-status');
    const storedToken = localStorage.getItem('fcm_token');
    const registered = localStorage.getItem('fcm_token_registered');
    if (storedToken) {
        storageStatus.innerHTML = '<span class="status-ok">✓ Stored (registered: ' + registered + ')</span>';
        console.log('Stored token:', storedToken.substring(0, 30) + '...');
    } else {
        storageStatus.innerHTML = '<span class="status-warning">⚠️ No token in localStorage</span>';
    }
});

// Manual registration
async function manualRegister() {
    console.log('Starting manual registration...');

    if (typeof window.NativeApp === 'undefined') {
        console.error('NativeApp not available');
        alert('Not running in native app!');
        return;
    }

    try {
        const token = window.NativeApp.getFcmToken();
        console.log('Got token:', token ? token.substring(0, 30) + '...' : 'empty');

        if (!token) {
            alert('No FCM token available from native app');
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
            alert('Token registered! Refresh page to see it.');
            localStorage.setItem('fcm_token', token);
            localStorage.setItem('fcm_token_registered', 'true');
        } else {
            alert('Failed: ' + data.error);
        }
    } catch (e) {
        console.error('Registration error:', e);
        alert('Error: ' + e.message);
    }
}

// Send test push
async function sendTestPush() {
    const resultEl = document.getElementById('push-result');
    resultEl.innerHTML = 'Sending...';

    try {
        const response = await fetch('/admin/api/fcm/test.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ send_test: true })
        });

        const data = await response.json();
        console.log('Push test result:', data);

        if (data.test_result && data.test_result.success) {
            resultEl.innerHTML = '<span class="status-ok">✓ Push sent! Check your phone.</span>';
        } else {
            resultEl.innerHTML = '<span class="status-error">✗ Failed: ' + (data.test_result?.error || 'Unknown error') + '</span>';
        }
    } catch (e) {
        console.error('Test push error:', e);
        resultEl.innerHTML = '<span class="status-error">✗ Error: ' + e.message + '</span>';
    }
}
</script>

<?php include __DIR__ . '/../header_footer/footer.php'; ?>
