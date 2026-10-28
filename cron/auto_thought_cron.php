<?php
/**
 * Auto-cron for daily thought notifications.
 *
 * Include this file from a frequently-called endpoint (e.g. notification count).
 * A lock file throttles general checks to once every 5 minutes, but it also
 * remembers the next scheduled display_time so the throttle is bypassed the
 * moment a thought becomes due — the 07:00 push fires on the first poll at or
 * after 07:00 instead of up to 5 minutes late.
 *
 * The sending itself (claim, translations, in-app + push in every member's
 * language) is dt_send_due() in includes/daily_thoughts.php.
 *
 * Requires: $pdo (MySQL PDO) to be available in the calling scope.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/daily_thoughts.php';

function runThoughtAutoCron(PDO $pdo): void
{
    $lockFile = __DIR__ . '/../data/.thought_cron_last';
    $now = time();

    // Read lock state: {"last": <ts of last check>, "next_due": <ts|null>}
    $lock = ['last' => 0, 'next_due' => null];
    if (file_exists($lockFile)) {
        $raw = trim((string)@file_get_contents($lockFile));
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $lock['last'] = (int)($decoded['last'] ?? 0);
            $lock['next_due'] = isset($decoded['next_due']) && $decoded['next_due'] !== null
                ? (int)$decoded['next_due'] : null;
        } elseif (ctype_digit($raw)) {
            // Legacy format: plain unix timestamp
            $lock['last'] = (int)$raw;
        }
    }

    // Skip unless a scheduled thought is due now, or the 5-minute throttle elapsed
    $scheduledDue = ($lock['next_due'] !== null && $now >= $lock['next_due']);
    if (!$scheduledDue && ($now - $lock['last']) < 300) {
        return;
    }

    // Update lock immediately to prevent concurrent runs (next_due recomputed below)
    @file_put_contents($lockFile, json_encode(['last' => $now, 'next_due' => $lock['next_due']]), LOCK_EX);

    try {
        dt_send_due($pdo);

        // Remember when the next scheduled thought becomes due so the throttle
        // can be bypassed at exactly that moment (South African time, like the UI).
        $sast = dt_now();
        $today = $sast->format('Y-m-d');
        $nextTime = dt_run($pdo, "
            SELECT MIN(display_time) FROM daily_thoughts
            WHERE display_date = ? AND notification_sent = 0
              AND display_time IS NOT NULL AND display_time > ?
        ", [$today, $sast->format('H:i:s')])->fetchColumn();

        $nextDue = null;
        if ($nextTime) {
            $nextDt = DateTime::createFromFormat('Y-m-d H:i:s', $today . ' ' . $nextTime, new DateTimeZone(DT_TZ));
            if ($nextDt) {
                $nextDue = $nextDt->getTimestamp();
            }
        }

        @file_put_contents($lockFile, json_encode(['last' => $now, 'next_due' => $nextDue]), LOCK_EX);

    } catch (Throwable $e) {
        error_log('Auto-cron thought error: ' . $e->getMessage());
    }
}
