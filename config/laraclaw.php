<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Provider Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the default AI provider and model for Laraclaw.
    | Supported providers: openai, anthropic, gemini, ollama, groq, mistral,
    | deepseek, xai
    |
    | Each provider requires its API key to be set in the environment:
    | - OpenAI: OPENAI_API_KEY
    | - Anthropic: ANTHROPIC_API_KEY
    | - Gemini: GEMINI_API_KEY
    | - Ollama: No API key required (local)
    | - Groq: GROQ_API_KEY
    | - Mistral: MISTRAL_API_KEY
    | - DeepSeek: DEEPSEEK_API_KEY
    | - xAI: XAI_API_KEY
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

        // Allowed commands for execute skill (empty = all safe commands allowed)
        'allowed_commands' => array_filter(
            explode(',', env('LARACLAW_ALLOWED_COMMANDS', ''))
        ),

        // Command execution timeout in seconds
        'command_timeout' => env('LARACLAW_COMMAND_TIMEOUT', 30),
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
        'aieos_file' => env('LARACLAW_AIEOS_FILE', 'aieos.json'),
        'aieos_enabled' => env('LARACLAW_AIEOS_ENABLED', true),
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

        'whatsapp' => [
            'enabled' => env('WHATSAPP_ENABLED', false),
            'token' => env('WHATSAPP_TOKEN'),
            'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
            'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
            'app_secret' => env('WHATSAPP_APP_SECRET'),
            'api_version' => env('WHATSAPP_API_VERSION', 'v18.0'),
        ],

        'cli' => [
            'enabled' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tunnel Configuration
    |--------------------------------------------------------------------------
    |
    | Configure local development tunnels for exposing your local server
    | to the internet. Supports ngrok, Cloudflare Tunnel, and Tailscale.
    |
    */

    'tunnels' => [
        // Default provider to use (ngrok, cloudflare, tailscale)
        'default_provider' => env('LARACLAW_TUNNEL_PROVIDER', 'cloudflare'),

        // Default local port to tunnel
        'default_port' => env('LARACLAW_TUNNEL_PORT', 8000),

        // Provider-specific configuration
        'providers' => [
            'ngrok' => [
                'path' => env('NGROK_PATH', 'ngrok'),
                'auth_token' => env('NGROK_AUTH_TOKEN'),
                'region' => env('NGROK_REGION', 'us'),
            ],

            'cloudflare' => [
                'path' => env('CLOUDFLARED_PATH', 'cloudflared'),
            ],

            'tailscale' => [
                'path' => env('TAILSCALE_PATH', 'tailscale'),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Configure queue settings for asynchronous message processing.
    |
    */

    'queues' => [
        'queue_name' => env('LARACLAW_QUEUE_NAME', 'laraclaw'),
        'processing_timeout' => env('LARACLAW_PROCESSING_TIMEOUT', 120),
        'retry_after' => env('LARACLAW_RETRY_AFTER', 60),
        'max_tries' => env('LARACLAW_MAX_TRIES', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring Configuration
    |--------------------------------------------------------------------------
    |
    | Configure monitoring and observability settings.
    |
    */

    'monitoring' => [
        'metrics_enabled' => env('LARACLAW_METRICS_ENABLED', true),
        'metrics_retention_days' => env('LARACLAW_METRICS_RETENTION_DAYS', 30),
        'health_check_timeout' => env('LARACLAW_HEALTH_CHECK_TIMEOUT', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Voice Configuration
    |--------------------------------------------------------------------------
    |
    | Configure voice/speech settings for TTS and STT.
    |
    */

    'voice' => [
        'enabled' => env('LARACLAW_VOICE_ENABLED', true),
        'path' => env('LARACLAW_VOICE_PATH', storage_path('laraclaw/voice')),
        'tts_provider' => env('LARACLAW_TTS_PROVIDER', 'openai'),
        'stt_provider' => env('LARACLAW_STT_PROVIDER', 'openai'),
        'default_voice' => env('LARACLAW_DEFAULT_VOICE', 'nova'),
    ],

    /*
    |--------------------------------------------------------------------------
    | File Storage Configuration
    |--------------------------------------------------------------------------
    |
    | Configure file storage for document and image analysis.
    |
    */

    'files' => [
        'enabled' => env('LARACLAW_FILES_ENABLED', true),
        'path' => env('LARACLAW_FILES_PATH', storage_path('laraclaw/files')),
        'provider' => env('LARACLAW_FILES_PROVIDER', 'openai'),
        'max_file_size' => env('LARACLAW_MAX_FILE_SIZE', 10485760), // 10MB
    ],

    /*
    |--------------------------------------------------------------------------
    | Vector Store Configuration
    |--------------------------------------------------------------------------
    |
    | Configure vector stores for semantic search and RAG.
    |
    */

    'vectors' => [
        'enabled' => env('LARACLAW_VECTORS_ENABLED', true),
        'dimensions' => env('LARACLAW_VECTOR_DIMENSIONS', 1536),
        'min_similarity' => env('LARACLAW_MIN_SIMILARITY', 0.7),
        'search_limit' => env('LARACLAW_VECTOR_SEARCH_LIMIT', 10),
    ],

    'multi_agent' => [
        'enabled' => env('LARACLAW_MULTI_AGENT_ENABLED', false),
    ],

    'marketplace' => [
        'enabled' => env('LARACLAW_MARKETPLACE_ENABLED', true),
    ],

];
