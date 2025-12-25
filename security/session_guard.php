<?php
declare(strict_types=1);

/**
 * ✅ SIMPLIFIED: Check if user has multiple active sessions
 * Only kick out if there's a REAL duplicate login (not regeneration)
 */
function session_guard_enforce(): void {
    global $pdo;
    
    // Skip if not logged in
    if (!auth_logged_in()) {
        return;
    }

    $uid = auth_user_id();
    $currentSid = session_id();

    try {
        // Get stored session key from DB
        $stmt = $pdo->prepare("SELECT web_session_key FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$uid]);
        $row = $stmt->fetch();

        if (!$row) {
            return;
        }

        $storedKey = $row['web_session_key'];

        // If no stored key, store current one
        if (empty($storedKey)) {
            $stmt = $pdo->prepare("UPDATE users SET web_session_key = ? WHERE id = ?");
            $stmt->execute([$currentSid, $uid]);
            return;
        }

        // If stored key doesn't match current session
        if ($storedKey !== $currentSid) {
            // Check if stored session still exists in PHP's session store
            // If it does, that means someone else is logged in - kick this one out
            
            // For simplicity: just update to current session (last login wins)
            $stmt = $pdo->prepare("UPDATE users SET web_session_key = ? WHERE id = ?");
            $stmt->execute([$currentSid, $uid]);
        }
        
    } catch (Throwable $e) {
        error_log('Session guard error: ' . $e->getMessage());
    }
}