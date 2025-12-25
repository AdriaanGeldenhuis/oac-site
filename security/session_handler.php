<?php
declare(strict_types=1);

/**
 * ✅ DATABASE SESSION HANDLER
 * Stores sessions in database instead of files
 * This ensures sessions survive server restarts and garbage collection
 */

class DatabaseSessionHandler implements SessionHandlerInterface
{
    private PDO $pdo;
    private int $lifetime;

    public function __construct(PDO $pdo, int $lifetime = 2592000)
    {
        $this->pdo = $pdo;
        $this->lifetime = $lifetime;
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT data FROM sessions 
                WHERE id = ? AND last_activity > ? 
                LIMIT 1
            ");
            $stmt->execute([$id, time() - $this->lifetime]);
            
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($row) {
                error_log("✅ SESSION READ: $id");
                return $row['data'];
            }
            
            error_log("⚠️ SESSION READ FAILED: $id (not found or expired)");
            return '';
            
        } catch (Throwable $e) {
            error_log("❌ SESSION READ ERROR: " . $e->getMessage());
            return '';
        }
    }

    public function write(string $id, string $data): bool
    {
        try {
            $now = time();
            $user_id = null;
            
            // Extract user_id from session data if available
            if (!empty($data)) {
                $session_data = @unserialize($data);
                if (isset($session_data['user_id'])) {
                    $user_id = (int)$session_data['user_id'];
                }
            }
            
            $stmt = $this->pdo->prepare("
                INSERT INTO sessions (id, user_id, data, last_activity, created_at) 
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    user_id = VALUES(user_id),
                    data = VALUES(data),
                    last_activity = VALUES(last_activity)
            ");
            
            $result = $stmt->execute([$id, $user_id, $data, $now, $now]);
            
            if ($result) {
                error_log("✅ SESSION WRITE: $id (user: " . ($user_id ?? 'guest') . ")");
            }
            
            return $result;
            
        } catch (Throwable $e) {
            error_log("❌ SESSION WRITE ERROR: " . $e->getMessage());
            return false;
        }
    }

    public function destroy(string $id): bool
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM sessions WHERE id = ?");
            $result = $stmt->execute([$id]);
            
            error_log("✅ SESSION DESTROY: $id");
            return $result;
            
        } catch (Throwable $e) {
            error_log("❌ SESSION DESTROY ERROR: " . $e->getMessage());
            return false;
        }
    }

    public function gc(int $max_lifetime): int|false
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM sessions WHERE last_activity < ?");
            $stmt->execute([time() - $this->lifetime]);
            $deleted = $stmt->rowCount();
            
            error_log("✅ SESSION GC: Deleted $deleted old sessions");
            return $deleted;
            
        } catch (Throwable $e) {
            error_log("❌ SESSION GC ERROR: " . $e->getMessage());
            return 0;
        }
    }
}