<?php
declare(strict_types=1);
require_once __DIR__ . '/../../security/auth_gate.php';

$meId = (int)($_SESSION['user_id'] ?? 0);
$targetId = isset($_POST['user_id']) && is_numeric($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$view = trim($_POST['view'] ?? 'month');
$date = trim($_POST['date'] ?? date('Y-m-d'));

if (!$targetId) {
    http_response_code(400);
    die('Invalid user_id');
}

// Check permissions (amp 1-9 can export)
$stmt = $pdo->prepare("SELECT amp_id FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$meId]);
$me = $stmt->fetch(PDO::FETCH_ASSOC);

$myAmpId = (int)($me['amp_id'] ?? 10);

if ($myAmpId >= 10) {
    http_response_code(403);
    die('Insufficient permissions');
}

// Get user info
$stmt = $pdo->prepare("SELECT name, surname FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$targetId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    http_response_code(404);
    die('User not found');
}

$fullName = trim($user['name'] . ' ' . $user['surname']);

// Fetch events
$stmt = $pdo->prepare("
    SELECT 
        ce.title,
        ce.start_at AS datetime,
        ce.description AS notes,
        'event' AS type
    FROM calendar_events ce
    WHERE ce.user_id = ?
    UNION ALL
    SELECT 
        d.title,
        CONCAT(d.date, ' ', COALESCE(d.time, '00:00:00')) AS datetime,
        d.body AS notes,
        'diary' AS type
    FROM diaries d
    WHERE d.user_id = ?
    UNION ALL
    SELECT 
        cv.title,
        CONCAT(cv.visit_date, ' ', COALESCE(cv.visit_time, '00:00:00')) AS datetime,
        cv.notes,
        'visit' AS type
    FROM calendar_visits cv
    WHERE cv.user_id = ?
    ORDER BY datetime ASC
");
$stmt->execute([$targetId, $targetId, $targetId]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Generate simple HTML for PDF
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="calendar_' . $targetId . '_' . time() . '.pdf"');

// Use simple HTML to PDF conversion (you'd normally use a library like TCPDF or Dompdf)
// For now, let's create a simple HTML that browser can print to PDF

echo "<!DOCTYPE html>
<html>
<head>
<meta charset='utf-8'>
<title>Calendar - $fullName</title>
<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h1 { color: #b76e79; }
table { width: 100%; border-collapse: collapse; margin-top: 20px; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background-color: #b76e79; color: white; }
tr:nth-child(even) { background-color: #f2f2f2; }
</style>
</head>
<body>
<h1>Calendar: $fullName</h1>
<p>View: " . ucfirst($view) . " | Date: $date</p>
<table>
<thead>
<tr>
<th>Date/Time</th>
<th>Title</th>
<th>Type</th>
<th>Notes</th>
</tr>
</thead>
<tbody>";

foreach ($events as $event) {
    $datetime = date('Y-m-d H:i', strtotime($event['datetime']));
    $title = htmlspecialchars($event['title']);
    $type = ucfirst($event['type']);
    $notes = htmlspecialchars(substr($event['notes'] ?? '', 0, 100));
    
    echo "<tr>
    <td>$datetime</td>
    <td>$title</td>
    <td>$type</td>
    <td>$notes</td>
    </tr>";
}

echo "</tbody>
</table>
<script>window.print();</script>
</body>
</html>";