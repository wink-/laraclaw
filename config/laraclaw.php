<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Provider Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the default AI provider and model for Laraclaw.
    | You may also optionally override provider/model per agent key
    | through the ai.agents map (for example: builder, planner, reviewer).
    | Supported providers: openai, anthropic, gemini, ollama, groq, mistral,
    | deepseek, xai, openrouter
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
    | - OpenRouter: OPENROUTER_API_KEY
    |
    */

    'ai' => [
        'provider' => env('AI_PROVIDER', 'openai'),
        'model' => env('AI_MODEL', 'gpt-4o-mini'),
        'max_tokens' => env('AI_MAX_TOKENS', 4096),
        'temperature' => env('AI_TEMPERATURE', 0.7),
        'agents' => [
            // Optional per-agent overrides. Any key can be used.
            // Unset values fall back to AI_PROVIDER / AI_MODEL.
            'general' => [
                'provider' => env('AGENT_GENERAL_PROVIDER'),
                'model' => env('AGENT_GENERAL_MODEL'),
            ],
            'builder' => [
                'provider' => env('AGENT_BUILDER_PROVIDER'),
                'model' => env('AGENT_BUILDER_MODEL'),
            ],
            'memory' => [
                'provider' => env('AGENT_MEMORY_PROVIDER'),
                'model' => env('AGENT_MEMORY_MODEL'),
            ],
            'entertainment' => [
                'provider' => env('AGENT_ENTERTAINMENT_PROVIDER'),
                'model' => env('AGENT_ENTERTAINMENT_MODEL'),
            ],
            'shopping' => [
                'provider' => env('AGENT_SHOPPING_PROVIDER'),
                'model' => env('AGENT_SHOPPING_MODEL'),
            ],
            'scheduling' => [
                'provider' => env('AGENT_SCHEDULING_PROVIDER'),
                'model' => env('AGENT_SCHEDULING_MODEL'),
            ],
            'planner' => [
                'provider' => env('AGENT_PLANNER_PROVIDER'),
                'model' => env('AGENT_PLANNER_MODEL'),
            ],
            'executor' => [
                'provider' => env('AGENT_EXECUTOR_PROVIDER'),
                'model' => env('AGENT_EXECUTOR_MODEL'),
            ],
            'reviewer' => [
                'provider' => env('AGENT_REVIEWER_PROVIDER'),
                'model' => env('AGENT_REVIEWER_MODEL'),
            ],
        ],
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
        'auto_extract' => env('LARACLAW_MEMORY_AUTO_EXTRACT', true),
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
    | Tailscale Network Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Tailscale-first networking. Use `tailscale serve`
    | to expose Laraclaw privately on your tailnet for multi-device access.
    |
    */

    'tailscale' => [
        'enabled' => env('LARACLAW_TAILSCALE_ENABLED', false),
        'serve_port' => env('LARACLAW_TAILSCALE_SERVE_PORT', 8000),
        'auto_serve' => env('LARACLAW_TAILSCALE_AUTO_SERVE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Heartbeat Engine Configuration
    |--------------------------------------------------------------------------
    |
    | HEARTBEAT.md is a natural-language task file that the AI agent executes
    | on a schedule. Enable/disable and configure the heartbeat path here.
    |
    */

    'heartbeat' => [
        'enabled' => env('LARACLAW_HEARTBEAT_ENABLED', true),
        'path' => env('LARACLAW_HEARTBEAT_PATH', storage_path('laraclaw/HEARTBEAT.md')),
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
        'reply_with_voice_for_voice_notes' => env('LARACLAW_REPLY_WITH_VOICE_FOR_VOICE_NOTES', true),
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

    'intent_routing' => [
        'enabled' => env('LARACLAW_INTENT_ROUTING_ENABLED', true),
        'prompts' => [
            'general' => 'You are Laraclaw, a helpful AI assistant. Be concise and practical.',
            'memory' => 'You are Laraclaw Memory Agent. Prioritize remembering and recalling key details accurately. Always use the memory tool for explicit remember/recall requests, and organize memories by category when possible.',
            'scheduling' => 'You are Laraclaw Scheduling Agent. Prioritize reminders, recurring tasks, and clear time-oriented actions. When a user asks to be reminded, always store it with the memory tool using action="remember".',
            'shopping' => 'You are Laraclaw Shopping Agent. Keep shopping lists structured, deduplicated, and easy to act on.',
            'entertainment' => 'You are Laraclaw Entertainment Agent. Focus on recommendations and recall of shows, movies, and watchlists. When a user mentions a title they want to watch later, store it with the memory tool.',
            'builder' => 'You are Laraclaw Builder Agent. Focus on safe, incremental app-building tasks within this Laravel monolith.',
        ],
    ],

    'marketplace' => [
        'enabled' => env('LARACLAW_MARKETPLACE_ENABLED', true),
        'required_skills' => [
            App\Laraclaw\Skills\TimeSkill::class,
            App\Laraclaw\Skills\CalculatorSkill::class,
            App\Laraclaw\Skills\WebSearchSkill::class,
            App\Laraclaw\Skills\MemorySkill::class,
        ],
    ],

    'modules' => [
        'enabled' => env('LARACLAW_MODULES_ENABLED', true),
        'path' => env('LARACLAW_MODULES_PATH', app_path('Modules')),
        'routes_path' => env('LARACLAW_MODULES_ROUTES_PATH', base_path('routes/modules')),
        'views_path' => env('LARACLAW_MODULES_VIEWS_PATH', resource_path('views/modules')),
        'migrations_path' => env('LARACLAW_MODULES_MIGRATIONS_PATH', database_path('migrations')),
    ],

    'api' => [
        'enabled' => env('LARACLAW_API_ENABLED', true),
    ],

    'rate_limits' => [
        'api_per_minute' => env('LARACLAW_API_RATE_LIMIT', 60),
        'webhooks_per_minute' => env('LARACLAW_WEBHOOK_RATE_LIMIT', 120),
    ],

    'context' => [
        'history_limit' => env('LARACLAW_CONTEXT_HISTORY_LIMIT', 50),
        'budget_tokens' => env('LARACLAW_CONTEXT_BUDGET_TOKENS', 3000),
        'summary_enabled' => env('LARACLAW_CONTEXT_SUMMARY_ENABLED', true),
        'rerank_enabled' => env('LARACLAW_CONTEXT_RERANK_ENABLED', true),
    ],

    'token_usage' => [
        'pricing' => [
            'openai' => [
                'input_per_million' => (float) env('LARACLAW_OPENAI_INPUT_PER_MILLION', 0.15),
                'output_per_million' => (float) env('LARACLAW_OPENAI_OUTPUT_PER_MILLION', 0.60),
            ],
            'anthropic' => [
                'input_per_million' => (float) env('LARACLAW_ANTHROPIC_INPUT_PER_MILLION', 3.00),
                'output_per_million' => (float) env('LARACLAW_ANTHROPIC_OUTPUT_PER_MILLION', 15.00),
            ],
            'gemini' => [
                'input_per_million' => (float) env('LARACLAW_GEMINI_INPUT_PER_MILLION', 0.35),
                'output_per_million' => (float) env('LARACLAW_GEMINI_OUTPUT_PER_MILLION', 1.05),
            ],
        ],
    ],

    'notifications' => [
        'enabled' => env('LARACLAW_NOTIFICATIONS_ENABLED', true),
    ],

];
