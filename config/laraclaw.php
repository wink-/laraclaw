<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Provider Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the default AI provider and model for Laraclaw.
    |
    */

    'ai' => [
        'provider' => env('AI_PROVIDER', 'openai'),
        'model' => env('AI_MODEL', 'gpt-4o-mini'),
        'max_tokens' => env('AI_MAX_TOKENS', 4096),
        'temperature' => env('AI_TEMPERATURE', 0.7),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | Configure security settings including autonomy levels, user allowlists,
    | and filesystem scoping.
    |
    */

    'security' => [
        // Autonomy level: readonly, supervised, full
        'autonomy' => env('LARACLAW_AUTONOMY', 'supervised'),

        // Enable user allowlist (only allowed users can interact)
        'allowlist_enabled' => env('LARACLAW_ALLOWLIST_ENABLED', false),

        // List of allowed user IDs (can be gateway-specific: "telegram:123456")
        'allowed_users' => array_filter(
            explode(',', env('LARACLAW_ALLOWED_USERS', ''))
        ),

        // List of blocked user IDs
        'blocked_users' => array_filter(
            explode(',', env('LARACLAW_BLOCKED_USERS', ''))
        ),

        // List of allowed channel IDs
        'allowed_channels' => array_filter(
            explode(',', env('LARACLAW_ALLOWED_CHANNELS', ''))
        ),

        // Filesystem scope for file operations
        'filesystem_scope' => env('LARACLAW_FILESYSTEM_SCOPE', storage_path('laraclaw')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Identity Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the assistant's identity and personality files.
    |
    */

    'identity' => [
        'path' => env('LARACLAW_IDENTITY_PATH', storage_path('laraclaw')),
        'identity_file' => env('LARACLAW_IDENTITY_FILE', 'IDENTITY.md'),
        'soul_file' => env('LARACLAW_SOUL_FILE', 'SOUL.md'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Memory Configuration
    |--------------------------------------------------------------------------
    |
    | Configure memory settings including conversation history limits
    | and long-term memory settings.
    |
    */

    'memory' => [
        'conversation_limit' => env('LARACLAW_MEMORY_LIMIT', 50),
        'search_limit' => env('LARACLAW_SEARCH_LIMIT', 10),
        'fts_enabled' => env('LARACLAW_FTS_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Gateway Configuration
    |--------------------------------------------------------------------------
    |
    | Gateway-specific settings for each supported platform.
    |
    */

    'gateways' => [
        'telegram' => [
            'enabled' => env('TELEGRAM_ENABLED', false),
            'token' => env('TELEGRAM_BOT_TOKEN'),
            'secret' => env('TELEGRAM_SECRET_TOKEN'),
        ],

        'discord' => [
            'enabled' => env('DISCORD_ENABLED', false),
            'token' => env('DISCORD_BOT_TOKEN'),
            'application_id' => env('DISCORD_APPLICATION_ID'),
            'public_key' => env('DISCORD_PUBLIC_KEY'),
        ],

        'cli' => [
            'enabled' => true,
        ],
    ],

];
