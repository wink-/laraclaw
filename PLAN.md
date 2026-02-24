# Laraclaw Development Plan

## 1. Project Overview
**Laraclaw** is a Laravel-based implementation of OpenClaw, the open-source personal AI assistant platform. It aims to bring the powerful, local-first, and highly extensible AI assistant capabilities of OpenClaw to the Laravel ecosystem, leveraging Laravel's robust queue system, Eloquent ORM, and the new `laravel/ai` (Prism) integration.

## 2. Core Architecture
Laraclaw will mirror the four core pillars of the OpenClaw architecture, adapted for Laravel:

*   **Gateway (Input/Output Layer):** 
    *   Handles incoming webhooks from messaging platforms (WhatsApp, Telegram, Discord).
    *   Implemented as Laravel Controllers and Event Listeners.
    *   Normalizes incoming messages into a standard `LaraclawMessage` object.
*   **Agent (The Brain):**
    *   The core LLM orchestration layer.
    *   Utilizes `laravel/ai` and `prism-php/prism` to interact with LLMs (OpenAI, Anthropic, or local models via Ollama).
    *   Manages the reasoning loop (ReAct pattern or similar) to decide which skills to invoke.
*   **Skills (Tools & Actions):**
    *   The capabilities the agent can execute (e.g., managing emails, browsing the web, executing scripts).
    *   Implemented as Invokable Laravel classes or dedicated Action classes that map to LLM tool definitions.
*   **Memory (Context & Storage):**
    *   Stores conversation history, user preferences, and long-term context.
    *   Implemented using Eloquent models (`Conversation`, `Message`, `MemoryFragment`).
    *   Future enhancement: Vector database integration for semantic search and RAG (Retrieval-Augmented Generation).

## 3. Tech Stack
*   **Framework:** Laravel 12.x
*   **AI Integration:** `laravel/ai` (Prism)
*   **Database:** SQLite (default for easy local setup) / PostgreSQL / MySQL
*   **Background Processing:** Laravel Queues (Redis/Horizon recommended for production) to handle long-running agent reasoning without blocking webhooks.

## 4. Implementation Phases

### Phase 1: Foundation & Memory ✅ COMPLETE
*   [x] Define the database schema for `users`, `conversations`, and `messages`.
*   [x] Create Eloquent models and relationships.
*   [x] Implement the `Memory` service to retrieve and format conversation history for the LLM.
*   [x] Set up the basic `Laraclaw` facade/service container bindings.

### Phase 2: Agent & Skills System ✅ COMPLETE
*   [x] Create the core `Agent` class that wraps `laravel/ai` calls.
*   [x] Define a `Skill` interface/base class.
*   [x] Implement a tool registry to dynamically load available skills.
*   [x] Build initial core skills:
    *   `WebSearchSkill`
    *   `TimeSkill` (current date/time)
    *   `CalculatorSkill`
*   [x] Implement the agent's execution loop (handling tool calls and returning results to the LLM).

### Phase 3: Gateways (Integrations) ✅ COMPLETE
*   [x] Create a `Gateway` interface.
*   [x] Implement `TelegramGateway`:
    *   Webhook controller.
    *   Message parsing.
    *   Sending responses back to Telegram.
*   [x] Implement `DiscordGateway`.
*   [x] Set up a local CLI/Tinker gateway for easy testing during development.

### Phase 4: Advanced Features & Polish ✅ COMPLETE
*   [x] Implement long-term memory using SQLite with FTS5 + vector embeddings (hybrid search).
*   [x] Create an onboarding console command (`php artisan laraclaw:install`).
*   [x] Add `laraclaw:doctor` command for diagnostics.
*   [x] Add `laraclaw:status` command for system health check.
*   [x] Add advanced skills (FileSystemSkill, ExecuteSkill).

### Phase 5: Security & Identity (from Zeroclaw) ✅ COMPLETE
*   [x] Implement gateway pairing/verification for secure webhook handling.
*   [x] Add user allowlists for controlling who can interact with the assistant.
*   [x] Implement filesystem scoping for safe file operations.
*   [x] Add autonomy levels: readonly, supervised, full.
*   [x] Create identity system with IDENTITY.md and SOUL.md support.
*   [x] Implement AIEOS (AI Entity Object Specification) protocol support.

### Phase 6: Production Features (from Zeroclaw) ✅ COMPLETE
*   [x] Add tunnel support for local development (Cloudflare, Tailscale, ngrok).
*   [x] Implement channel binding commands (`laraclaw:channel:bind-telegram`).
*   [x] Add queue-based message processing for scalability.
*   [x] Create monitoring and observability features.

### Phase 7: Advanced Skills & Dashboard ✅ COMPLETE
*   [x] Create EmailSkill for email management (IMAP/SMTP).
*   [x] Create CalendarSkill for calendar/event management.
*   [x] Create web dashboard for monitoring and administration.
*   [x] Add support for more AI providers (Anthropic Claude, Ollama local).
*   [x] Implement web UI for chat interface.

### Phase 8: AI SDK Advanced Features ✅ COMPLETE
*   [x] Implement streaming responses for real-time chat.
*   [x] Add vector embeddings for semantic memory search.
*   [ ] Create reranking service for improved search relevance.
*   [x] Add Text-to-Speech (TTS) skill for voice responses.
*   [x] Add Speech-to-Text (STT) skill for voice input.
*   [x] Implement structured output for skill results.
*   [x] Add file storage with AI providers for document analysis.
*   [x] Create vector store integration for knowledge base.

### Phase 9: Enhanced Dashboard & UX ✅ COMPLETE
*   [x] Add real-time chat with Livewire/Volt.
*   [x] Create conversation export (Markdown, JSON).
*   [x] Add user authentication for web dashboard.
*   [ ] Create conversation sharing feature.
*   [x] Add dark/light theme toggle.
*   [x] Create mobile-responsive chat UI.
*   [x] Add conversation search functionality.
*   [x] Create keyboard shortcuts for power users.

### Phase 10: OpenClaw Parity & Advanced Automation ✅ COMPLETE
*   [x] **WhatsApp Gateway**: Implemented `WhatsAppGateway` and webhook controller with verification + signature handling.
*   [x] **Agentic Scheduling (Cron)**: Implemented `SchedulerSkill`, scheduled tasks table, and `laraclaw:run-scheduled-tasks` command wired into Laravel scheduler.
*   [x] **Voice Note Integration**: Implemented inbound voice-note transcription and outbound TTS voice replies for Telegram/WhatsApp.
*   [x] **Document Ingestion UI**: Added dashboard upload/index flow and document tracking model/table for vector-store ingestion.
*   [x] **Multi-Agent Collaboration**: Implemented planner/executor/reviewer orchestration and collaboration persistence with per-message chat toggle.
*   [x] **Skill Marketplace/Plugin System**: Implemented DB-backed plugin manager with dashboard enable/disable controls.

### Phase 11: Voice Reply Parity & Operations Hardening ✅ COMPLETE
*   [x] **Outbound Voice Replies**: Send TTS audio replies back to Telegram/WhatsApp when users send voice notes.
*   [x] **Scheduler Operations UI**: Add dashboard controls to list/pause/remove scheduled tasks.
*   [x] **Marketplace Safety Rails**: Prevent disabling required core skills that break base flows.
*   [x] **Phase 10 Regression Tests**: Added focused tests for scheduler dashboard controls and marketplace safety toggles.
*   [x] **Ops Dashboard Signals**: Added dashboard panel showing failed scheduled jobs, webhook failures, and collaboration activity stats.

### Phase 12: API Layer, Observability & Platform Intelligence (Current)
*   [ ] **REST API with Token Auth**: Create a versioned JSON API (`/api/v1/`) using Laravel Sanctum for token authentication. Expose endpoints for conversations, messages, skills, and memories. Include request/response resource classes and OpenAPI-compatible route documentation. Enables third-party apps, mobile clients, and automation pipelines to interact with Laraclaw programmatically.
*   [ ] **Rate Limiting & Abuse Prevention**: Apply Laravel's built-in `RateLimiter` to API endpoints, webhook routes, and chat actions. Implement per-user and per-IP throttling with configurable limits in `config/laraclaw.php`. Return proper `429 Too Many Requests` responses with `Retry-After` headers. Add a rate-limit dashboard indicator.
*   [ ] **Token Usage Tracking & Cost Analytics**: Intercept LLM responses to capture token counts (prompt + completion tokens). Persist per-message token usage in a `token_usage` table. Add a dashboard analytics panel showing daily/weekly token burn, estimated cost by provider, and per-conversation breakdowns. Provides visibility into AI spend.
*   [ ] **Proactive Notification Engine**: Allow the assistant to initiate outbound messages to users via gateways (Telegram, Discord, WhatsApp) on scheduled triggers or event-driven conditions. Supports daily briefings, task-completion alerts, and scheduled reminder delivery. Extends the existing `SchedulerSkill` infrastructure with a notification dispatch layer.
*   [ ] **Smart Context Window Management**: Replace the flat `LIMIT 50` history retrieval with token-budget-aware context assembly. Implement automatic conversation summarisation—compress older messages into a summary block when approaching the model's context limit. Configurable via `context_budget` and `summarisation_model` in config. Fold in semantic reranking (Phase 8 debt) to prioritise the most relevant memory fragments.
*   [ ] **Configuration Export / Import**: Add `laraclaw:config:export` and `laraclaw:config:import` Artisan commands that serialise/deserialise the full assistant configuration (identity files, AIEOS spec, skill toggles, scheduled tasks, gateway bindings, and settings) as a portable JSON bundle. Enables backup, migration between environments, and sharing of assistant setups.

### Phase 13: Multi-Device Access & Autonomous Intelligence
*   [x] **Tailscale-First Networking**: Enhanced Tailscale integration beyond basic tunnelling. Auto-detect tailnet membership, expose dashboard and API on Tailscale IP via `tailscale serve`, device discovery showing all peers on the tailnet, MagicDNS hostname resolution, HTTPS via Tailscale certs, network health monitoring, and a dashboard panel showing connected devices. Enables seamless access from phone, work PC, and home PC without exposing anything to the public internet.
*   [x] **PWA Mobile Web App (Livewire)**: Progressive Web App using Livewire — no JavaScript frontend framework. Web app manifest for home-screen installation on phones, service worker for offline shell caching, responsive mobile-first chat UI, viewport-optimised layout with collapsible sidebar, touch-friendly controls, and a standalone display mode. Makes Laraclaw feel like a native app on any device over the tailnet.
*   [x] **HEARTBEAT.md Engine**: Natural-language periodic task runner inspired by ZeroClaw. The agent reads a `HEARTBEAT.md` file containing loose instructions (e.g. "check my email every morning", "summarise Hacker News at 6pm") and autonomously runs them on a configurable schedule. Persists run history in a `heartbeat_runs` table, supports enabling/disabling individual heartbeat items, and surfaces status on the dashboard. Different from cron — heartbeats are natural-language AI-driven awareness tasks.

## 5. Proposed Directory Structure
```text
app/
  Laraclaw/
    Agents/
      CoreAgent.php
    Gateways/
      Contracts/GatewayInterface.php
      TelegramGateway.php
      DiscordGateway.php
    Memory/
      MemoryManager.php
    Skills/
      Contracts/SkillInterface.php
      WebSearchSkill.php
      CalculatorSkill.php
```
