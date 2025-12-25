<?php
declare(strict_types=1);

/**
 * Simple rate limiter (prevent brute force)
 */
function rate_limit_check(string $identifier, int $maxAttempts = 5, int $blockMinutes = 15): bool {
    global $pdo;
    
    try {
        // Clean old entries (1 hour)
        $pdo->exec("DELETE FROM rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        
        // Check current
        $stmt = $pdo->prepare("SELECT attempts, blocked_until FROM rate_limits WHERE identifier = ? LIMIT 1");
        $stmt->execute([$identifier]);
        $row = $stmt->fetch();
        
        if ($row) {
            // Still blocked?
            if ($row['blocked_until'] && strtotime($row['blocked_until']) > time()) {
                return false; // BLOCKED
            }
            
            // Too many attempts?
            if ((int)$row['attempts'] >= $maxAttempts) {
                $blockUntil = date('Y-m-d H:i:s', time() + ($blockMinutes * 60));
                $pdo->prepare("UPDATE rate_limits SET blocked_until = ?, attempts = attempts + 1 WHERE identifier = ?")
                    ->execute([$blockUntil, $identifier]);
                return false; // BLOCKED
            }
            
            // Increment
            $pdo->prepare("UPDATE rate_limits SET attempts = attempts + 1 WHERE identifier = ?")
                ->execute([$identifier]);
        } else {
            // First attempt
            $pdo->prepare("INSERT INTO rate_limits (identifier, attempts) VALUES (?, 1)")
                ->execute([$identifier]);
        }
        
        return true; // ALLOWED
        
    } catch (Throwable $e) {
        error_log('Rate limit error: ' . $e->getMessage());
        return true; // Fail open (don't block on error)
    }
}

function rate_limit_reset(string $identifier): void {
    global $pdo;
    try {
        $pdo->prepare("DELETE FROM rate_limits WHERE identifier = ?")->execute([$identifier]);
    } catch (Throwable $e) {
        error_log('Rate limit reset error: ' . $e->getMessage());
    }
}