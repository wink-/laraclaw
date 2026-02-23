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

### Phase 9: Enhanced Dashboard & UX (In Progress)
*   [x] Add real-time chat with Livewire/Volt.
*   [ ] Create conversation export (PDF, Markdown).
*   [ ] Add user authentication for web dashboard.
*   [ ] Create conversation sharing feature.
*   [x] Add dark/light theme toggle.
*   [x] Create mobile-responsive chat UI.
*   [x] Add conversation search functionality.
*   [x] Create keyboard shortcuts for power users.

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
