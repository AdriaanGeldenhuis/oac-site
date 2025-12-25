<?php
declare(strict_types=1);

/**
 * ✅ STANDALONE AI TEST SCRIPT
 * Usage: php /path/to/admin/test_ai.php
 * Or visit: https://oacapp.co.za/admin/test_ai.php
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "<pre>";
echo "=====================================\n";
echo "🔍 AI CONFIG TEST\n";
echo "=====================================\n\n";

// Test 1: Load ai_config.php
echo "TEST 1: Loading ai_config.php\n";
$configPath = __DIR__ . '/config/ai_config.php';
echo "Path: $configPath\n";
echo "Exists: " . (file_exists($configPath) ? 'YES' : 'NO') . "\n";

if (!file_exists($configPath)) {
    die("❌ FAILED: ai_config.php not found\n");
}

require_once $configPath;
echo "✅ PASSED: ai_config.php loaded\n\n";

// Test 2: Check constants
echo "TEST 2: Check constants\n";
$constants = [
    'OPENAI_API_KEY',
    'OPENAI_MODEL',
    'OPENAI_MAX_TOKENS',
    'OPENAI_TEMPERATURE',
    'BIBLE_PATH_AFR',
    'BIBLE_PATH_ENG'
];

foreach ($constants as $const) {
    if (defined($const)) {
        $value = constant($const);
        if ($const === 'OPENAI_API_KEY') {
            $value = substr($value, 0, 20) . '...';
        }
        echo "✅ $const = $value\n";
    } else {
        echo "❌ $const NOT DEFINED\n";
    }
}
echo "\n";

// Test 3: Check functions
echo "TEST 3: Check functions\n";
$functions = [
    'install_json_error_handlers',
    'loadBibleData',
    'openai_chat'
];

foreach ($functions as $func) {
    if (function_exists($func)) {
        echo "✅ $func() exists\n";
    } else {
        echo "❌ $func() NOT FOUND\n";
    }
}
echo "\n";

// Test 4: Load Bible
echo "TEST 4: Load Bible data\n";
try {
    $bibleAf = loadBibleData('af');
    if ($bibleAf) {
        $bookCount = count($bibleAf);
        echo "✅ Afrikaans Bible: $bookCount books\n";
    } else {
        echo "❌ Failed to load Afrikaans Bible\n";
    }
    
    $bibleEn = loadBibleData('en');
    if ($bibleEn) {
        $bookCount = count($bibleEn);
        echo "✅ English Bible: $bookCount books\n";
    } else {
        echo "❌ Failed to load English Bible\n";
    }
} catch (Throwable $e) {
    echo "❌ Bible load error: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 5: OpenAI connection (simple test)
echo "TEST 5: OpenAI API test\n";
try {
    install_json_error_handlers();
    
    $messages = [
        ['role' => 'system', 'content' => 'You are a helpful assistant.'],
        ['role' => 'user', 'content' => 'Say "TEST OK" and nothing else.']
    ];
    
    echo "Calling OpenAI...\n";
    $result = openai_chat($messages, ['max_tokens' => 10]);
    
    if (isset($result['choices'][0]['message']['content'])) {
        $response = $result['choices'][0]['message']['content'];
        echo "✅ OpenAI response: $response\n";
        echo "✅ Tokens used: " . ($result['usage']['total_tokens'] ?? '?') . "\n";
    } else {
        echo "❌ Invalid response structure\n";
    }
} catch (Throwable $e) {
    echo "❌ OpenAI test failed: " . $e->getMessage() . "\n";
}

echo "\n=====================================\n";
echo "🏁 TEST COMPLETE\n";
echo "=====================================\n";
echo "</pre>";