# Product Requirements Document (PRD)
**Project Name:** Open Brain (AI Memory System) for Laravel
**Version:** 1.0
**Target Date:** [Insert Date]
**Status:** Phase 16 Implemented (V1)

_Last updated: 2026-03-03_

## 1. Executive Summary
**Objective:** Integrate a centralized, tool-agnostic "Open Brain" memory system into our existing Laravel application. This system will serve as a persistent, vector-backed knowledge base hosted on Supabase, which any AI agent (Claude, ChatGPT, Cursor, etc.) can read from via the Model Context Protocol (MCP).

**Goal:** Eliminate the "Memory Silo Problem" where context is lost when switching between different AI tools, reducing context-engineering time from minutes to zero. It will act as a universal memory hub for all the apps you use, connecting to channels like Slack, Telegram, Discord, and WhatsApp.

## 2. Problem Statement
Currently, AI memory is siloed. Context shared in ChatGPT does not transfer to Claude, and project details in Cursor are blind to both. Re-explaining context, project constraints, and past decisions to AI agents is a bottleneck that burns time, interrupts flow, and leads to sub-optimal AI outputs. We need an infrastructure built for the "Agent Web" rather than the "Human Web."

## 3. Proposed Solution
Instead of relying on third-party SaaS "second brains," we will build a sovereign memory infrastructure within our Laravel app, utilizing Supabase as our memory storage layer.

**The workflow:**
1. **Capture:** The user types a thought, meeting note, or decision into a connected app (Slack, Telegram, Discord, WhatsApp, etc.).
2. **Process:** A Laravel queued job triggers, sending the text to an LLM to generate a vector embedding and extract metadata (people, topics, action items).
3. **Store:** The thought and its vector are saved into a Supabase PostgreSQL database using `pgvector`.
4. **Retrieve:** An MCP Server connects to this database, exposing tools to all desktop AI clients to search and read this memory natively.

---

## 4. Feature Requirements (In-Scope)

### 4.1. Omni-Channel Capture Mechanism (Input)
*   **Requirement:** Webhook endpoints in Laravel that receive real-time messages from integrated apps (Slack, Telegram, Discord, WhatsApp, custom frontend UI, etc.). Leverages the existing `GatewayInterface` architecture already implemented for Telegram, Discord, and WhatsApp.
*   **Response:** The system must reply to the original message/thread in the respective app (e.g., within 5-10 seconds) confirming the memory was embedded and classified.

### 4.2. Asynchronous Processing Pipeline
*   **Requirement:** A robust background processing pipeline using Laravel Queues. 
*   **Steps:**
    1. Receive raw text from the source platform.
    2. Call an LLM (e.g., OpenAI API) to generate a mathematical vector embedding of the text.
    3. Call an LLM to extract structured metadata (Topics, People mentioned, Action Items, Type of entry).

### 4.3. Supabase Vector Database Storage
*   **Requirement:** Utilize Supabase (PostgreSQL with the `pgvector` extension) as the remote memory store. The Supabase connection is already configured in `config/database.php` (`supabase` and `supabase_direct`).
*   **Schema Needs:** 
    *   `id` (UUID or BigInt)
    *   `content` (Text - the raw thought/note)
    *   `embedding` (Vector column — 1536 dimensions for OpenAI `text-embedding-3-small`)
    *   `platform_source` (String — e.g. 'slack', 'telegram', 'discord', 'whatsapp')
    *   `metadata` (JSONB column for extracted tags, people, action items, etc.)
    *   `created_at` / `updated_at`

### 4.4. Retrieval Layer (MCP Server Integration)
*   **Requirement:** Expose the memory to AI agents via the Model Context Protocol (MCP).
*   **Implementation:** The Laravel app exposes MCP tools via the `laravel/mcp` package, which is already a project dependency. These tools query the Supabase-backed memory store directly.
*   **Exposed Tools:**
    1.  `semantic_search`: Find thoughts by meaning (vector similarity search via Supabase pgvector).
    2.  `list_recent`: Browse what was captured in the last X days.
    3.  `stats`: See patterns and high-level statistics of the memory base.

---

## 5. Technical Architecture (Laravel Specifics)

### Stack Components
*   **Backend:** Laravel 12 (existing Laraclaw application)
*   **Database:** Supabase (Remote PostgreSQL) with `pgvector` extension enabled. Already configured as `supabase` / `supabase_direct` connections.
*   **Queue Driver:** Redis or Database (for processing embeddings asynchronously).
*   **AI Integration:** `laravel/ai` (Prism) — already used for embeddings and LLM calls across the application.
*   **MCP Server:** `laravel/mcp` — already a project dependency. Exposes memory tools natively to Claude Desktop, Cursor, etc.
*   **Gateways:** Existing `GatewayInterface` implementations for Telegram, Discord, WhatsApp. New `SlackGateway` to be added.

### Laravel Implementation Steps
1. **Database Setup (Supabase):** 
   * Execute `CREATE EXTENSION IF NOT EXISTS vector;` via Supabase SQL Editor or `supabase_direct` connection.
   * Create a `memories` table on Supabase with a vector(1536) column, `platform_source`, and JSONB `metadata`.
2. **API Routes:**
   * `POST /api/webhooks/{platform}`: Unified webhook endpoint for Slack, Telegram, Discord, WhatsApp.
   * `POST /api/mcp/search`: Accepts a search string, generates an embedding on the fly, does a cosine similarity search in Supabase Postgres (`<=>`), and returns the top 5 results.
   * `GET /api/mcp/recent`: Returns the latest entries across all platforms.
3. **Jobs (`ProcessNewMemory`):**
   * Dispatched immediately when a webhook is received.
   * Generates vector embedding via `laravel/ai` Embeddings.
   * Extracts structured metadata (topics, people, action items) via LLM.
   * Writes to the Supabase `memories` table.
   * Fires a confirmation reply back to the originating platform via the appropriate gateway.

### Implemented in Laraclaw (Current)
1. **Memory Model + Store**
    * Added `app/Models/Memory.php` and `app/Laraclaw/Memory/MemoryStore.php`.
    * Added `platform_source`, metadata extraction, pgvector query support, and lexical fallback.
2. **Webhook Ingestion**
    * Added unified ingest endpoint: `POST /api/webhooks/{platform}`.
    * Added dedicated Slack parity endpoint: `POST /laraclaw/webhooks/slack`.
3. **Async Processing**
    * Added `ProcessNewMemoryJob` for embedding generation, metadata extraction, persistence, and confirmation replies.
4. **MCP Retrieval Endpoints**
    * Added `POST /api/mcp/search`, `GET /api/mcp/recent`, and `GET /api/mcp/stats`.
5. **Slack Integration**
    * Added `SlackGateway` and Slack dispatch paths in notification and send-message pipelines.

---

## 6. User Flows

**Flow 1: Capturing a Memory**
1. User types in a designated Telegram/Discord/Slack/WhatsApp channel: *"Had a meeting with Sarah. We decided to use Stripe for the payments API moving forward."*
2. The platform sends a payload to the Laravel webhook endpoint (`POST /api/webhooks/{platform}`).
3. Controller resolves the appropriate `Gateway`, dispatches `ProcessNewMemoryJob`, and returns `202 queued`.
4. Job generates an embedding via `laravel/ai` Embeddings (`text-embedding-3-small`).
5. Job extracts metadata via LLM: `{"topics": ["Payments", "Stripe"], "people": ["Sarah"]}`.
6. Job writes the vector, content, platform_source, and metadata to Supabase.
7. Job uses the originating gateway to reply in thread: *"✅ Saved and embedded. Tags: Payments, Stripe."*

**Flow 2: AI Retrieval (Using Cursor/Claude Desktop)**
1. User opens Cursor and asks, *"What did we decide about the payments API?"*
2. Cursor recognizes it needs context and calls the `semantic_search` MCP tool (served by `laravel/mcp`).
3. Laravel vectorizes the query, runs cosine similarity search against Supabase pgvector, and returns the Sarah/Stripe note.
4. Cursor reads the response and perfectly answers the user.

---

## 7. Metrics for Success / KPIs
*   **Speed of Capture:** Time from sending a message to receiving the confirmation reply (Target: < 5 seconds).
*   **Search Latency:** Time taken for the `semantic_search` MCP tool to return results (Target: < 500ms).
*   **Operating Cost:** Cost of embeddings + metadata extraction per note (Target: < $0.01 per note).
*   **Agent Success Rate:** Frequency of the AI agent correctly invoking the `semantic_search` tool and finding relevant context.

## 8. Out of Scope (For V1)
*   A fully custom frontend UI for browsing notes (relying on messaging apps for input and MCP/AI for output in V1).
*   Complex auth systems for multiple users (V1 will be a single-tenant "Open Brain" to prove out the architecture, or strictly scoped by user ID if adding to an existing multi-tenant SaaS).

## 9. Future Enhancements (V2+)
*   **Prompt Kits (Memory Migration):** Scripts/Commands in Laravel to parse old Evernote/Apple Notes exports and batch-process them into the Supabase database.
*   **Weekly Review Automation:** A scheduled Laravel command (Task Scheduler) that runs every Friday, clusters topics using an LLM, and sends a synthesis report to your preferred messaging app.
*   **Web Scraper Integration:** Allowing the user to drop a URL into any connected messaging app, having Laravel scrape the text, embed it, and store it as a related memory in Supabase.
*   **Cross-Platform Memory Linking:** Automatically link related memories across platforms (e.g., a Slack decision referencing a Telegram note).