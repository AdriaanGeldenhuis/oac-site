<?php
/**
 * Bible Module Configuration - EXAMPLE
 * =====================================
 * Copy this file to config.php and fill in your API keys.
 *
 * cp config.example.php config.php
 */
declare(strict_types=1);

// NOTE: Database connection ($pdo) is provided by security/auth_gate.php
// which is required by ai_commentary.php before this file

// =============================================================================
// OPENAI API CONFIGURATION
// =============================================================================

// Your OpenAI API key - get from https://platform.openai.com/api-keys
define('OPENAI_API_KEY', 'sk-your-api-key-here');

// OpenAI API endpoint
define('OPENAI_API_URL', 'https://api.openai.com/v1/chat/completions');

// Model to use (gpt-4o-mini is fast and affordable, gpt-4o for better quality)
define('OPENAI_MODEL', 'gpt-4o-mini');

// Maximum tokens for AI responses (800 for concise but rich context)
define('OPENAI_MAX_TOKENS', 800);

// Temperature (0.0-2.0, lower = more focused, higher = more creative)
define('OPENAI_TEMPERATURE', 0.3);

// =============================================================================
// AI RULES FILE
// =============================================================================

// Path to the AI rules/prompt file
define('AI_RULES_FILE', __DIR__ . '/ai_rules.txt');

// =============================================================================
// BIBLE FILES CONFIGURATION
// =============================================================================

// Directory where Bible JSON files are stored
define('BIBLE_DIR', __DIR__ . '/bibles/');

// Available Bible versions (code => filename)
define('BIBLE_VERSIONS', [
    'af' => 'af_1933_53.json',      // Afrikaans 1933/53
    'en' => 'en_kjv1611.json',      // King James Version
    'zu' => 'zu_dummy.json',        // isiZulu (placeholder)
    'xh' => 'xh_dummy.json',        // isiXhosa (placeholder)
    'pt' => 'pt_dummy.json',        // Portuguese (placeholder)
    'st' => 'st_dummy.json'         // Sesotho (placeholder)
]);

// =============================================================================
// CONTEXT SETTINGS
// =============================================================================

// Number of verses before and after to include for AI context
// 10 verses gives rich story context (uses fewer if near chapter start/end)
define('AI_CONTEXT_VERSES_BEFORE', 10);
define('AI_CONTEXT_VERSES_AFTER', 10);

// =============================================================================
// RATE LIMITING
// =============================================================================

// Maximum AI requests per user per hour
define('AI_RATE_LIMIT_HOUR', 30);

// Maximum AI requests per user per day
define('AI_RATE_LIMIT_DAY', 100);
