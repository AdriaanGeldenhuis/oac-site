<?php
// =====================================================================
// /admin/config/fcm_config.php — Firebase Cloud Messaging V1 API
// =====================================================================

declare(strict_types=1);

// Firebase Project Config
define('FCM_PROJECT_ID', 'oac-app-5728d');
define('FCM_SERVICE_ACCOUNT_FILE', __DIR__ . '/firebase-service-account.json');
define('FCM_SITE_URL', 'https://oacapp.co.za'); // Base URL for notification deep links

/**
 * Get OAuth2 access token for FCM V1 API
 * Uses service account credentials to generate JWT and exchange for access token
 */
function getFcmAccessToken(): ?string {
    static $cachedToken = null;
    static $tokenExpiry = 0;

    // Return cached token if still valid (with 5 min buffer)
    if ($cachedToken && time() < ($tokenExpiry - 300)) {
        return $cachedToken;
    }

    if (!file_exists(FCM_SERVICE_ACCOUNT_FILE)) {
        error_log('FCM: Service account file not found');
        return null;
    }

    $serviceAccount = json_decode(file_get_contents(FCM_SERVICE_ACCOUNT_FILE), true);

    if (!$serviceAccount || !isset($serviceAccount['private_key'])) {
        error_log('FCM: Invalid service account file');
        return null;
    }

    // Create JWT header
    $header = [
        'alg' => 'RS256',
        'typ' => 'JWT'
    ];

    // Create JWT claims
    $now = time();
    $claims = [
        'iss' => $serviceAccount['client_email'],
        'sub' => $serviceAccount['client_email'],
        'aud' => 'https://oauth2.googleapis.com/token',
        'iat' => $now,
        'exp' => $now + 3600,
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging'
    ];

    // Encode header and claims
    $headerEncoded = rtrim(strtr(base64_encode(json_encode($header)), '+/', '-_'), '=');
    $claimsEncoded = rtrim(strtr(base64_encode(json_encode($claims)), '+/', '-_'), '=');

    // Sign with private key
    $dataToSign = $headerEncoded . '.' . $claimsEncoded;
    $privateKey = openssl_pkey_get_private($serviceAccount['private_key']);

    if (!$privateKey) {
        error_log('FCM: Failed to parse private key');
        return null;
    }

    $signature = '';
    if (!openssl_sign($dataToSign, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
        error_log('FCM: Failed to sign JWT');
        return null;
    }

    $signatureEncoded = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    $jwt = $dataToSign . '.' . $signatureEncoded;

    // Exchange JWT for access token
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($error) {
        error_log('FCM: Token request curl error: ' . $error);
        $GLOBALS['fcm_last_error'] = 'Curl error: ' . $error;
        return null;
    }

    $data = json_decode($response, true);

    if (!isset($data['access_token'])) {
        error_log('FCM: Failed to get access token (HTTP ' . $httpCode . '): ' . $response);
        $GLOBALS['fcm_last_error'] = 'HTTP ' . $httpCode . ': ' . ($data['error_description'] ?? $data['error'] ?? $response);
        return null;
    }

    // Cache the token
    $cachedToken = $data['access_token'];
    $tokenExpiry = $now + ($data['expires_in'] ?? 3600);

    return $cachedToken;
}

/**
 * Send push notification via FCM V1 API
 *
 * Sends DATA-ONLY messages (no notification block) so onMessageReceived is
 * ALWAYS called in the Android app, even in the background. Tokens are sent
 * in parallel batches so large fan-outs (e.g. the daily thought) reach every
 * phone within seconds instead of one-by-one.
 *
 * @param string|array $tokens FCM token(s) to send to
 * @param string $title Notification title
 * @param string $body Notification body
 * @param array $data Additional data payload
 * @return array Result with success status
 */
function sendFcmNotification($tokens, string $title, string $body, array $data = []): array {
    $accessToken = getFcmAccessToken();

    if (!$accessToken) {
        return ['success' => false, 'error' => 'Failed to get FCM access token'];
    }

    $tokens = is_array($tokens) ? array_values(array_unique(array_filter($tokens))) : [$tokens];

    if (empty($tokens)) {
        return ['success' => false, 'error' => 'No tokens provided'];
    }

    $url = 'https://fcm.googleapis.com/v1/projects/' . FCM_PROJECT_ID . '/messages:send';

    $results = [];
    $successCount = 0;
    $failureCount = 0;
    $invalidTokens = [];

    // Build deep link URL if link is provided in data
    $clickUrl = FCM_SITE_URL;
    if (!empty($data['link'])) {
        $clickUrl = FCM_SITE_URL . $data['link'];
    }

    // FCM data payload values must be strings; arrays/objects (e.g. translation
    // params) become JSON instead of the literal "Array".
    $payload = [];
    foreach (array_merge($data, ['title' => $title, 'body' => $body, 'click_url' => $clickUrl]) as $key => $value) {
        $payload[$key] = (is_scalar($value) || $value === null)
            ? (string)$value
            : (string)json_encode($value, JSON_UNESCAPED_UNICODE);
    }

    // V1 API requires one request per token — run them in parallel batches.
    foreach (array_chunk($tokens, 25) as $chunk) {
        $multi = curl_multi_init();
        $handles = [];

        foreach ($chunk as $token) {
            $message = [
                'message' => [
                    'token' => $token,
                    'android' => [
                        'priority' => 'HIGH'
                    ],
                    'data' => $payload
                ]
            ];

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $accessToken,
                    'Content-Type: application/json'
                ],
                CURLOPT_POSTFIELDS => json_encode($message),
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 30
            ]);
            curl_multi_add_handle($multi, $ch);
            $handles[] = ['ch' => $ch, 'token' => $token];
        }

        do {
            $status = curl_multi_exec($multi, $active);
            if ($active && curl_multi_select($multi, 1.0) === -1) {
                usleep(10000); // select not supported: avoid a busy loop
            }
        } while ($active && $status === CURLM_OK);

        foreach ($handles as $h) {
            $ch = $h['ch'];
            $token = $h['token'];
            $response = curl_multi_getcontent($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_multi_remove_handle($multi, $ch);
            curl_close($ch);

            if ($error) {
                error_log('FCM curl error: ' . $error);
                $results[] = ['token' => $token, 'success' => false, 'error' => $error];
                $failureCount++;
                continue;
            }

            $result = json_decode((string)$response, true);

            if ($httpCode === 200) {
                $results[] = ['token' => $token, 'success' => true, 'message_id' => $result['name'] ?? null];
                $successCount++;
            } else {
                $errStatus = $result['error']['status'] ?? '';
                $errMsg = $result['error']['message'] ?? 'Unknown error';
                error_log('FCM error for token ' . substr($token, 0, 20) . '...: ' . $response);
                $results[] = ['token' => $token, 'success' => false, 'error' => $errMsg];
                $failureCount++;

                // Token no longer valid (app uninstalled / token rotated):
                // flag it for removal so future sends stay fast and clean.
                if ($errStatus === 'UNREGISTERED' || $httpCode === 404
                    || ($errStatus === 'INVALID_ARGUMENT' && stripos($errMsg, 'registration token') !== false)) {
                    $invalidTokens[] = $token;
                }
            }
        }

        curl_multi_close($multi);
    }

    if (!empty($invalidTokens)) {
        fcmRemoveDeadTokens($invalidTokens);
    }

    return [
        'success' => $successCount > 0,
        'success_count' => $successCount,
        'failure_count' => $failureCount,
        'removed_tokens' => count($invalidTokens),
        'results' => $results
    ];
}

/**
 * Delete tokens FCM reported as invalid/unregistered from fcm_tokens.
 */
function fcmRemoveDeadTokens(array $tokens): void {
    global $pdo;

    if (!($pdo instanceof PDO)) {
        return;
    }

    try {
        $stmt = $pdo->prepare('DELETE FROM fcm_tokens WHERE token = ?');
        foreach ($tokens as $token) {
            $stmt->execute([$token]);
        }
        error_log('FCM: Removed ' . count($tokens) . ' dead token(s)');
    } catch (Throwable $e) {
        error_log('FCM dead token cleanup error: ' . $e->getMessage());
    }
}

/**
 * Send push notification to a specific user
 *
 * @param int $userId User ID to send to
 * @param string $title Notification title
 * @param string $body Notification body
 * @param array $data Additional data payload
 * @return array Result with success status
 */
function sendPushToUser(int $userId, string $title, string $body, array $data = []): array {
    // Use global $pdo (from auth_gate.php / security/config.php)
    global $pdo;

    try {
        // Get all FCM tokens for this user
        $stmt = $pdo->prepare("SELECT token FROM fcm_tokens WHERE user_id = ?");
        $stmt->execute([$userId]);
        $tokens = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($tokens)) {
            return ['success' => false, 'error' => 'No tokens for user'];
        }

        return sendFcmNotification($tokens, $title, $body, $data);

    } catch (Exception $e) {
        error_log('sendPushToUser error: ' . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
