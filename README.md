<p align="center">
    <img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo">
</p>

<p align="center">
    <strong>Laraclaw</strong> — OpenClaw AI Assistant for Laravel
</p>

<p align="center">
    <a href="#installation">Installation</a> •
    <a href="#quick-start">Quick Start</a> •
    <a href="#skills">Skills</a> •
    <a href="#gateways">Gateways</a> •
    <a href="#dashboard">Dashboard</a> •
    <a href="#configuration">Configuration</a>
</p>

---

## What is Laraclaw?

**Laraclaw** is a Laravel-based implementation of OpenClaw, the open-source personal AI assistant platform. It brings powerful, local-first, and highly extensible AI assistant capabilities to the Laravel ecosystem.

### Features

- 🧠 **Intelligent Memory** — SQLite FTS5 full-text search for long-term memory storage
- 🔄 **Streaming-Aware Memory Context** — Streaming chat now uses token-budgeted history, relevant memory retrieval, and intent routing
- 📝 **Automatic Memory Extraction** — Reminder/preference/watch-intent messages can be auto-saved into memory fragments after replies
- 🔧 **12 Built-in Skills** — Time, Calculator, Web Search, App Builder, Memory, Shopping List, File System, Execute, Email, Calendar, Scheduler, Notifications
- 💬 **Multi-Platform Gateways** — CLI, Telegram, Discord, and WhatsApp support
- 🌐 **Web Dashboard** — Monitor conversations, metrics, and chat directly from your browser
- 🤝 **Multi-Agent Mode** — Per-message planner/executor/reviewer orchestration for complex tasks
- 🧩 **Skill Marketplace** — Enable/disable registered skills from the dashboard
- 📄 **Document Ingestion** — Upload and index documents into vector storage for retrieval
- 🔐 **Security First** — User allowlists, autonomy levels, filesystem scoping, webhook verification
- 🤖 **Multi-Provider AI** — OpenAI, Anthropic, Gemini, Ollama, Groq, Mistral, DeepSeek, xAI
- 📋 **AIEOS Support** — AI Entity Object Specification v1.1 for portable AI identities
- 🚇 **Tunnel Support** — ngrok, Cloudflare Tunnel, and Tailscale for local development
- 🧭 **Intent Routing** — Specialist prompt routing for builder, memory, scheduling, shopping, and entertainment intents
- 🧱 **Module App Builder (MVP)** — Generate blog apps inside the same Laravel install using Laravel MVC modules under `app/Modules`

---

## Requirements

- PHP 8.4+
- Laravel 12.x
- SQLite (default) / MySQL / PostgreSQL
- Composer

---

## Installation

### 1. Install via Composer

```bash
composer require laraclaw/laraclaw
```

### 2. Run the Installer

```bash
php artisan laraclaw:install
```

This interactive command will:
- Check system requirements
- Configure your AI provider (OpenAI, Anthropic, Ollama, etc.)
- Set up Telegram/Discord bots (optional)
- Run database migrations
- Create identity files (IDENTITY.md, SOUL.md)

### 3. Configure Environment

Add your AI provider credentials to `.env`:

```env
# AI Provider (openai, anthropic, gemini, ollama, groq, mistral, deepseek, xai)
AI_PROVIDER=openai
AI_MODEL=gpt-4o-mini

# API Keys
OPENAI_API_KEY=sk-...
# ANTHROPIC_API_KEY=sk-ant-...
# GEMINI_API_KEY=...

# Telegram (optional)
TELEGRAM_BOT_TOKEN=123456:ABC-DEF...
TELEGRAM_SECRET_TOKEN=your-webhook-secret

# Discord (optional)
DISCORD_BOT_TOKEN=Bot ...
DISCORD_PUBLIC_KEY=...
DISCORD_APPLICATION_ID=...

# WhatsApp (optional)
WHATSAPP_ENABLED=true
WHATSAPP_TOKEN=...
WHATSAPP_PHONE_NUMBER_ID=...
WHATSAPP_VERIFY_TOKEN=...
WHATSAPP_APP_SECRET=...

# Multi-Agent & Marketplace
LARACLAW_MULTI_AGENT_ENABLED=false
LARACLAW_MARKETPLACE_ENABLED=true

# Memory extraction (auto-save reminders/preferences)
LARACLAW_MEMORY_AUTO_EXTRACT=true
```

---

## Quick Start

### CLI Chat

Start chatting immediately from the command line:

```bash
php artisan laraclaw:chat
```

### Web Dashboard

Visit `/laraclaw` in your browser for:
- 📊 **Dashboard** — System overview and health status
- 💬 **Conversations** — Browse all conversations
- 🧠 **Memories** — View stored memory fragments
- 📈 **Metrics** — Performance statistics
- 💭 **Chat** — Interactive web chat interface

### Programmatic Usage

```php
use App\Laraclaw\Facades\Laraclaw;

// Start a conversation
$conversation = Laraclaw::startConversation(userId: 1);

// Send a message and get a response
$response = Laraclaw::chat($conversation, "What time is it?");

// Optional per-message override for multi-agent mode
$response = Laraclaw::chat($conversation, "Research and summarize this topic", true);

// Quick one-off question
$response = Laraclaw::ask("Calculate 15% of 850");
```

---

## Skills

Laraclaw comes with 12 built-in skills that the AI can use automatically:

| Skill | Description |
|-------|-------------|
| **TimeSkill** | Get current date/time with timezone support |
| **CalculatorSkill** | Safe mathematical expression evaluation |
| **WebSearchSkill** | Search the web via DuckDuckGo API |
| **AppBuilderSkill** | Create/list app modules, draft/publish posts, and set domain bindings |
| **MemorySkill** | Store, recall, and manage long-term memories |
| **ShoppingListSkill** | Add/view/remove/clear shopping list items |
| **FileSystemSkill** | Read, write, and manage files (scoped) |
| **ExecuteSkill** | Execute shell commands (full autonomy only) |
| **EmailSkill** | Read (IMAP) and send emails |
| **CalendarSkill** | Manage events with ICS export |
| **SchedulerSkill** | Register recurring/delayed actions with cron or natural-language schedules |
| **NotificationSkill** | Create/list/cancel/dispatch proactive outbound notifications |

### Module Apps (MVP)

Laraclaw can now generate blog modules in standard Laravel MVC style using `AppBuilderSkill`.

Each generated module includes:
- Module manifest in `app/Modules/{ModuleName}/module.json`
- Eloquent model in `app/Modules/{ModuleName}/Models/*Post.php`
- Controller in `app/Modules/{ModuleName}/Http/Controllers/*PostController.php`
- Route file in `routes/modules/{slug}.php` loaded dynamically by `ModuleServiceProvider`
- Blade views in `resources/views/modules/{slug}/`
- Migration in `database/migrations/` for `{slug}_posts` table

Runtime loading behavior:
- `ModuleServiceProvider` discovers module manifests from `app/Modules`
- Route groups are mounted from each module manifest (`prefix` or optional `domain`)

After generating an app, run:

```bash
php artisan migrate
```

You can manage modules from:
- `AppBuilderSkill` tool actions
- Livewire dashboard “Module App Builder (Laravel MVC)” panel

Current module actions (via tool calls):
- `create_app`
- `list_apps`
- `create_post_draft`
- `publish_post`
- `list_posts`
- `set_domain`

### Creating Custom Skills

```php
<?php

namespace App\Laraclaw\Skills;

use App\Laraclaw\Skills\Contracts\SkillInterface;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class MyCustomSkill implements SkillInterface, Tool
{
    public function name(): string
    {
        return 'my_custom_skill';
    }

    public function description(): string
    {
        return 'Description of what this skill does';
    }

    public function execute(array $parameters): string
    {
        // Your skill logic here
        return "Result: ...";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'param' => $schema->string()->description('Parameter description'),
        ];
    }

    public function toTool(): Tool
    {
        return $this;
    }

    public function handle(Request $request): string
    {
        return $this->execute($request->all());
    }
}
```

Register in `LaraclawServiceProvider`:

```php
$this->app->singleton(MyCustomSkill::class);
$this->app->tag([MyCustomSkill::class], 'laraclaw.skills');
```

---

## Gateways

### CLI Gateway

The built-in CLI gateway for terminal interaction:

```bash
php artisan laraclaw:chat
```

Options:
- `--session=ID` — Resume a specific conversation
- `--new` — Start a new conversation

### Telegram Gateway

1. Create a bot via [@BotFather](https://t.me/botfather)
2. Set your webhook:

```bash
php artisan laraclaw:webhook:telegram
```

The webhook endpoint is: `POST /laraclaw/webhooks/telegram`

### Discord Gateway

1. Create a Discord application at [Discord Developer Portal](https://discord.com/developers/applications)
2. Register slash commands:

```bash
php artisan laraclaw:discord:register-commands
```

The webhook endpoint is: `POST /laraclaw/webhooks/discord`

### WhatsApp Gateway

Meta Cloud API webhook endpoints:

- Verification: `GET /laraclaw/webhooks/whatsapp`
- Incoming messages: `POST /laraclaw/webhooks/whatsapp`

Voice notes are transcribed via STT and processed as normal chat messages.

---

## Dashboard

Access the dashboard at `/laraclaw`:

| Route | Description |
|-------|-------------|
| `/laraclaw` | Main dashboard with stats and health |
| `/laraclaw/conversations` | List all conversations |
| `/laraclaw/conversations/{id}` | View single conversation |
| `/laraclaw/memories` | Browse memory fragments |
| `/laraclaw/metrics` | Performance metrics |
| `/laraclaw/chat` | Interactive web chat |

---

## Tunnels (Local Development)

Expose your local server for Telegram/Discord webhooks:

```bash
# Cloudflare Tunnel (recommended)
php artisan laraclaw:tunnel start --provider=cloudflare

# ngrok
php artisan laraclaw:tunnel start --provider=ngrok

# Tailscale
php artisan laraclaw:tunnel start --provider=tailscale
```

Check tunnel status:

```bash
php artisan laraclaw:tunnel status
```

---

## Security

### Autonomy Levels

Configure in `.env`:

```env
LARACLAW_AUTONOMY=supervised
```

| Level | Description |
|-------|-------------|
| `readonly` | Can only read information |
| `supervised` | Can write with approval (default) |
| `full` | Full autonomy including command execution |

### User Allowlists

Restrict who can interact with Laraclaw:

```env
LARACLAW_ALLOWLIST_ENABLED=true
LARACLAW_ALLOWED_USERS=telegram:123456,discord:789012
```

### Filesystem Scoping

Limit file operations to a specific directory:

```env
LARACLAW_FILESYSTEM_SCOPE=/var/www/storage/laraclaw
```

---

## Configuration

Full configuration in `config/laraclaw.php`:

```php
return [
    // AI Provider
    'ai' => [
        'provider' => env('AI_PROVIDER', 'openai'),
        'model' => env('AI_MODEL', 'gpt-4o-mini'),
        'max_tokens' => env('AI_MAX_TOKENS', 4096),
        'temperature' => env('AI_TEMPERATURE', 0.7),
    ],

    // Security
    'security' => [
        'autonomy' => env('LARACLAW_AUTONOMY', 'supervised'),
        'allowlist_enabled' => env('LARACLAW_ALLOWLIST_ENABLED', false),
        'filesystem_scope' => env('LARACLAW_FILESYSTEM_SCOPE', storage_path('laraclaw')),
    ],

    // Identity
    'identity' => [
        'path' => env('LARACLAW_IDENTITY_PATH', storage_path('laraclaw')),
        'identity_file' => env('LARACLAW_IDENTITY_FILE', 'IDENTITY.md'),
        'soul_file' => env('LARACLAW_SOUL_FILE', 'SOUL.md'),
        'aieos_file' => env('LARACLAW_AIEOS_FILE', 'aieos.json'),
        'aieos_enabled' => env('LARACLAW_AIEOS_ENABLED', true),
    ],

    // Memory
    'memory' => [
        'conversation_limit' => env('LARACLAW_MEMORY_LIMIT', 50),
        'search_limit' => env('LARACLAW_SEARCH_LIMIT', 10),
        'fts_enabled' => env('LARACLAW_FTS_ENABLED', true),
    ],
];
```

---

## AIEOS Support

Laraclaw supports the [AIEOS v1.1](https://github.com/entitai/aieos) specification for portable AI identities.

Create `storage/laraclaw/aieos.json`:

```json
{
  "standard": {
    "protocol": "AIEOS",
    "version": "1.1.0"
  },
  "identity": {
    "names": {
      "first_name": "Laraclaw",
      "nickname": "Claw"
    }
  },
  "psychology": {
    "neural_matrix": {
      "creativity": 0.7,
      "empathy": 0.8,
      "logic": 0.9
    },
    "moral_compass": {
      "core_values": ["helpfulness", "honesty", "privacy"]
    }
  },
  "linguistics": {
    "text_style": {
      "formality_level": 0.4,
      "verbosity_level": 0.5
    }
  }
}
```

---

## Artisan Commands

| Command | Description |
|---------|-------------|
| `laraclaw:install` | Interactive installation wizard |
| `laraclaw:doctor` | System diagnostics |
| `laraclaw:status` | System status and statistics |
| `laraclaw:health` | Health check (JSON output available) |
| `laraclaw:metrics` | Display performance metrics |
| `laraclaw:chat` | Start CLI chat session |
| `laraclaw:tunnel {action}` | Manage development tunnels |
| `laraclaw:channel:bind-telegram` | Bind Telegram channel |
| `laraclaw:channel:bind-discord` | Bind Discord channel |
| `laraclaw:channel:list` | List channel bindings |
| `laraclaw:channel:unbind` | Remove channel binding |

---

## Testing

```bash
# Run all tests
php artisan test --compact

# Run with filter
php artisan test --filter=Laraclaw
```

---

## Architecture

```
app/Laraclaw/
├── Agents/
│   ├── CoreAgent.php          # LLM orchestration
│   ├── IntentRouter.php
│   └── MultiAgentOrchestrator.php
├── Channels/
│   └── ChannelBindingManager.php
├── Events/
│   ├── MessageProcessed.php
│   └── MessageProcessingFailed.php
├── Gateways/
│   ├── CliGateway.php
│   ├── TelegramGateway.php
│   ├── DiscordGateway.php
│   └── WhatsAppGateway.php
├── Identity/
│   ├── IdentityManager.php
│   └── Aieos/
│       ├── AieosEntity.php
│       ├── AieosParser.php
│       └── AieosPromptCompiler.php
├── Jobs/
│   ├── ProcessMessageJob.php
│   └── SendMessageJob.php
├── Memory/
│   └── MemoryManager.php
├── Modules/
│   └── ModuleManager.php
├── Monitoring/
│   └── MetricsCollector.php
├── Security/
│   └── SecurityManager.php
├── Skills/
│   ├── AppBuilderSkill.php
│   ├── CalculatorSkill.php
│   ├── CalendarSkill.php
│   ├── EmailSkill.php
│   ├── ExecuteSkill.php
│   ├── FileSystemSkill.php
│   ├── MemorySkill.php
│   ├── SchedulerSkill.php
│   ├── ShoppingListSkill.php
│   ├── TimeSkill.php
│   └── WebSearchSkill.php
└── Tunnels/
    ├── TunnelManager.php
    ├── NgrokService.php
    ├── CloudflareTunnelService.php
    └── TailscaleService.php

app/Modules/
└── {ModuleName}/
  ├── module.json
  ├── Models/
  └── Http/Controllers/

routes/modules/
resources/views/modules/
```

---

## Contributing

Contributions are welcome! Please read our contributing guidelines before submitting PRs.

---

## License

Laraclaw is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

---

## Credits

- Inspired by [OpenClaw](https://github.com/openclaw) and [NullClaw](https://nullclaw.org)
- Built with [Laravel](https://laravel.com) and [Laravel AI SDK](https://laravel.com/docs/ai-sdk)
- AIEOS specification by [EntitAI](https://github.com/entitai/aieos)
