<?php
/**
 * Test script for OpenAI streaming API
 * Run this to verify API connectivity
 */
declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

echo "=== OpenAI Streaming Test ===\n\n";

// Load AI config
require_once __DIR__ . '/config/ai_config.php';

// Check API key
if (!defined('OPENAI_API_KEY') || OPENAI_API_KEY === '') {
    echo "ERROR: No API key configured!\n";
    echo "Please add your key to admin/config/secrets.php\n";
    exit(1);
}

echo "API Key: " . substr(OPENAI_API_KEY, 0, 10) . "..." . substr(OPENAI_API_KEY, -4) . "\n";
echo "Model: " . OPENAI_MODEL . "\n\n";

// Simple test message
$messages = [
    ['role' => 'system', 'content' => 'You are a helpful assistant. Reply in one short sentence.'],
    ['role' => 'user', 'content' => 'Say hello in Afrikaans.']
];

echo "Sending test request...\n\n";

try {
    $startTime = microtime(true);
    $result = openai_chat($messages);
    $endTime = microtime(true);

    echo "SUCCESS!\n";
    echo "Time: " . round($endTime - $startTime, 2) . " seconds\n";
    echo "Response: " . ($result['choices'][0]['message']['content'] ?? 'No content') . "\n";
    echo "Finish reason: " . ($result['choices'][0]['finish_reason'] ?? 'unknown') . "\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";
