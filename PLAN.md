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

### Phase 1: Foundation & Memory
*   [ ] Define the database schema for `users`, `conversations`, and `messages`.
*   [ ] Create Eloquent models and relationships.
*   [ ] Implement the `Memory` service to retrieve and format conversation history for the LLM.
*   [ ] Set up the basic `Laraclaw` facade/service container bindings.

### Phase 2: Agent & Skills System
*   [ ] Create the core `Agent` class that wraps `laravel/ai` calls.
*   [ ] Define a `Skill` interface/base class.
*   [ ] Implement a tool registry to dynamically load available skills.
*   [ ] Build initial core skills:
    *   `WebSearchSkill`
    *   `TimeSkill` (current date/time)
    *   `CalculatorSkill`
*   [ ] Implement the agent's execution loop (handling tool calls and returning results to the LLM).

### Phase 3: Gateways (Integrations)
*   [ ] Create a `Gateway` interface.
*   [ ] Implement `TelegramGateway`:
    *   Webhook controller.
    *   Message parsing.
    *   Sending responses back to Telegram.
*   [ ] Implement `DiscordGateway`.
*   [ ] Set up a local CLI/Tinker gateway for easy testing during development.

### Phase 4: Advanced Features & Polish
*   [ ] Implement long-term memory using vector embeddings.
*   [ ] Add advanced skills (e.g., local script execution, email management).
*   [ ] Create an onboarding console command (`php artisan laraclaw:install` / `laraclaw:onboard`).

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
