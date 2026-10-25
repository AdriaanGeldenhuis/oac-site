<?php
declare(strict_types=1);
/**
 * POST /diary/api/create.php   (JSON body, X-CSRF-Token header)
 * {date, time, title, body, mood, weather, tags[]} → {success, entry}
 *
 * The entry shows in my own calendar on its day (the calendar reads
 * `diaries` itself), so nothing is copied to calendar_events.
 */

require_once dirname(__DIR__, 2) . '/security/auth_gate.php';
require_once dirname(__DIR__) . '/lib/diary.php';

dy_require_post();

if (!isset($pdo) || !($pdo instanceof PDO)) {
    dy_fail('db_unavailable', 500);
}

$userId = dy_user_id();
if ($userId <= 0) {
    dy_fail('session', 401);
}

[$fields, $error] = dy_entry_from_input(dy_input());
if ($error !== null) {
    dy_fail($error);
}

// A runaway client (or script) cannot fill the table: 120 new entries an hour is plenty
if (!dy_rate_ok('diary_create_' . $userId, 120)) {
    dy_fail('rate_limited', 429);
}

try {
    // reminder_minutes is kept for older rows; nothing sends diary reminders
    $st = $pdo->prepare('INSERT INTO diaries (user_id, date, time, title, body, mood, weather, tags, reminder_minutes, created_at, updated_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, NOW(), NOW())');
    $st->execute([
        $userId, $fields['date'], $fields['time'], $fields['title'], $fields['body'],
        $fields['mood'], $fields['weather'], $fields['tags'],
    ]);
    $id = (int)$pdo->lastInsertId();

    $row = dy_find($pdo, $userId, $id);
    dy_json(['success' => true, 'entry' => $row ? dy_entry_out($row, true) : ['id' => $id]]);
} catch (Throwable $e) {
    error_log('Diary create error: ' . $e->getMessage());
    dy_fail('server_error', 500);
}
