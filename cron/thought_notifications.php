<?php
/**
 * Daily Thought Push Notification Cron Job
 *
 * Run this script every 15 minutes via cron:
 *   0,15,30,45 * * * * php /path/to/oac-site/cron/thought_notifications.php
 *
 * Or via URL (with cron service like cron-job.org):
 *   https://oacapp.co.za/cron/thought_notifications.php?key=YOUR_SECRET_KEY
 *
 * Checks if today's thought has reached its display_time (South African
 * time) and sends it to the members in the same town (opsienerskap) as the
 * thought creator, each in their own language. The work is dt_send_due() in
 * includes/daily_thoughts.php, shared with the auto-cron, so the two can
 * never send the same thought twice.
 */

declare(strict_types=1);

$isCliMode = (php_sapi_name() === 'cli');
$secretKey = 'oac_thought_cron_2024_secure';

if (!$isCliMode) {
    if (!hash_equals($secretKey, (string)($_GET['key'] ?? ''))) {
        http_response_code(403);
        die('Forbidden');
    }
    header('Content-Type: application/json');
}

// Translations and a large fan-out can take a while
@set_time_limit(300);

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../includes/daily_thoughts.php';

$pdo = db();
$results = ['sent' => 0, 'skipped' => false];

try {
    $sent = dt_send_due($pdo);
    if ($sent === null) {
        $results['skipped'] = true;
        $results['message'] = 'No thought ready for notification';
    } else {
        $results['sent'] = $sent['members'];
        $results['thought_id'] = $sent['id'];
        $results['message'] = 'Thought notifications sent successfully';
    }
} catch (Throwable $e) {
    $results['error'] = $e->getMessage();
    error_log('Thought notification cron error: ' . $e->getMessage());
}

if (!$isCliMode) {
    echo json_encode($results, JSON_PRETTY_PRINT);
} else {
    echo ($results['message'] ?? ('Error: ' . ($results['error'] ?? 'unknown'))) . "\n";
    echo "Members: {$results['sent']}\n";
}
