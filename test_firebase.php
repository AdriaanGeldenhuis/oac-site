<?php
// Get your server key from Firebase Console
$serverKey = 'YOUR_FIREBASE_SERVER_KEY';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fcmToken = $_POST['token'] ?? '';
    $userId = $_POST['user_id'] ?? 1;
    $title = $_POST['title'] ?? 'Test Notification';
    $body = $_POST['body'] ?? 'This is a test';
    
    $data = [
        'to' => $fcmToken,
        'notification' => [
            'title' => $title,
            'body' => $body,
            'sound' => 'default',
            'badge' => '1'
        ],
        'data' => [
            'user_id' => (string)$userId,
            'type' => 'info',
            'link' => '/notifications/notifications.php'
        ],
        'priority' => 'high'
    ];
    
    $headers = [
        'Authorization: key=' . $serverKey,
        'Content-Type: application/json'
    ];
    
    $ch = curl_init('https://fcm.googleapis.com/fcm/send');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "<pre>";
    echo "HTTP Code: $httpCode\n";
    echo "Response: " . print_r(json_decode($result, true), true);
    echo "</pre>";
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Firebase Notification</title>
    <style>
        body { font-family: Arial; padding: 20px; max-width: 600px; margin: 0 auto; }
        input, textarea { width: 100%; padding: 10px; margin: 10px 0; }
        button { background: #4CAF50; color: white; padding: 15px; border: none; cursor: pointer; width: 100%; }
    </style>
</head>
<body>
    <h1>🔔 Test Firebase Notification</h1>
    <form method="POST">
        <label>FCM Token:</label>
        <textarea name="token" rows="3" required></textarea>
        
        <label>User ID:</label>
        <input type="number" name="user_id" value="1" required>
        
        <label>Title:</label>
        <input type="text" name="title" value="Test van OAC" required>
        
        <label>Body:</label>
        <textarea name="body" rows="3" required>Hierdie is 'n test notification!</textarea>
        
        <button type="submit">📤 Stuur Notification</button>
    </form>
    
    <hr>
    <h3>Steps:</h3>
    <ol>
        <li>Get FCM Token from logs: <code>adb logcat -s flutter:I | findstr "FCM Token"</code></li>
        <li>Paste it above</li>
        <li>Enter the correct User ID (check your database)</li>
        <li>Click "Stuur Notification"</li>
    </ol>
</body>
</html>