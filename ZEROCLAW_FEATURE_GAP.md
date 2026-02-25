# ZeroClaw → Laraclaw Feature Gap Analysis

> Features from [zeroclaw-labs/zeroclaw](https://github.com/zeroclaw-labs/zeroclaw) that are **NOT yet in Laraclaw** (phases 1-12), prioritised for a multi-device personal AI assistant accessible via Tailscale.

---

## Priority 1 — High-Impact, Directly Portable

### 1. HEARTBEAT.md — Autonomous Periodic Task Engine
**ZeroClaw:** `src/heartbeat/engine.rs` (10 KB)  
Reads a `HEARTBEAT.md` file from the workspace. Lines prefixed with `- ` are treated as natural-language tasks. The engine runs them on a configurable interval (min 5 min) through the agent, creating an autonomous background pulse.  
**Config:** `HeartbeatConfig { enabled: bool, interval_minutes: u64 }`  
**Why it matters:** This is *not* the same as the Laraclaw `SchedulerSkill`. Scheduled tasks are explicit cron entries; heartbeat tasks are loose reminders ("Check my email", "Review my calendar") the agent interprets each tick. It makes the assistant feel genuinely proactive.  
**Laravel port:** A `HeartbeatService` reading `storage/app/HEARTBEAT.md`, a scheduled artisan command running every N minutes, dispatching each line through the agent as a fresh conversation.

### 2. SOP Engine — Multi-Step Workflow Execution
**ZeroClaw:** `src/sop/` — 8 files, ~200 KB total  
- `engine.rs` (56 KB) — core orchestrator  
- `gates.rs` (26 KB) — approval/condition gates within workflows  
- `dispatch.rs` (26 KB) — step dispatch  
- `metrics.rs` (52 KB) — SOP execution metrics  
- `condition.rs` (14 KB) — conditional branching  
- `audit.rs` (10 KB) — audit trail  
- `types.rs` (15 KB) — data structures  
- Tools: `sop_advance`, `sop_approve`, `sop_execute`, `sop_list`, `sop_status`

**What it does:** Define multi-step procedures (e.g., "deploy to staging → run tests → get approval → deploy to prod"). The agent follows each step, checks conditions, waits at approval gates, records audit trails, and collects metrics.  
**Why it matters:** Turns the assistant from a Q&A bot into a process executor. Critical for personal automation (morning routines, weekly reviews, deployment workflows).  
**Laravel port:** Eloquent-backed `SopProcedure`, `SopStep`, `SopExecution` models; a gate/condition evaluator; approval via Telegram/Discord callback buttons; 5 matching tools.

### 3. SkillForge — Auto-Discovery & Integration of Skills
**ZeroClaw:** `src/skillforge/` — 4 files, ~35 KB  
- Pipeline: **Scout** (discovers from GitHub/ClawHub/HuggingFace) → **Evaluate** (scores candidates, min 0.7) → **Integrate** (generates manifests)  
- Config: `SkillForgeConfig { enabled, auto_integrate, sources, scan_interval_hours, min_score, github_token, output_dir }`

**What it does:** Scans external registries for compatible skills, evaluates their quality and safety, and automatically generates integration manifests for qualified candidates.  
**Why it matters:** Enables a growing ecosystem where skills can be discovered and installed without manual coding.  
**Laravel port:** A `SkillForge` service with a GitHub search scout, evaluation rubric, and auto-generation of skill plugin database entries in the existing `PluginManager`.

### 4. Approval System — Human-in-the-Loop Gating
**ZeroClaw:** `src/approval/mod.rs` (14.5 KB)  
Standalone approval module for gating any action that requires human confirmation before execution.  
**Why it matters:** Essential for the "supervised" autonomy level. Currently Laraclaw has the autonomy level concept but no interactive approval flow where the agent pauses and asks "Should I proceed?" via channel.  
**Laravel port:** `ApprovalRequest` model with pending/approved/rejected state; channel-specific callback handlers (Telegram inline keyboard, Discord buttons); timeout auto-reject.

### 5. Screenshot Tool
**ZeroClaw:** `src/tools/screenshot.rs` (11.5 KB)  
Platform-native screenshot capture: macOS (`screencapture`), Linux (`gnome-screenshot` / `scrot` / `import`). Returns base64-encoded PNG. Supports region selection on macOS. Security-scoped with filename sanitisation and shell injection prevention.  
**Why it matters:** A Tailscale-connected device can screenshot its display and send it to the user on another device. Critical for remote monitoring.  
**Laravel port:** A `ScreenshotSkill` that shells out to platform commands, stores to `storage/app/screenshots/`, returns the image via the active channel.

---

## Priority 2 — Valuable Subsystems

### 6. Browser Automation Tool
**ZeroClaw:** `src/tools/browser.rs` (87 KB!) + `browser_open.rs` (16 KB)  
Full headless browser automation — navigate, click, fill forms, extract content. Separate `browser_open` for simply opening URLs in the user's default browser.  
**Why it matters:** Web scraping, form filling, and UI testing as AI skills.  
**Laravel port:** A `BrowserSkill` wrapping a headless Chrome/Puppeteer instance via a PHP bridge (e.g., `spatie/browsershot` or `chrome-php/chrome`).

### 7. Observability: OpenTelemetry + Prometheus
**ZeroClaw:** `src/observability/` — 9 files  
- `otel.rs` (19 KB) — OpenTelemetry trace/span export  
- `prometheus.rs` (17 KB) — Prometheus metrics endpoint  
- `runtime_trace.rs` (12 KB) — per-agent-invocation tracing  
- `traits.rs` — pluggable `Observer` trait with `multi.rs` fan-out  
- `noop.rs`, `verbose.rs`, `log.rs` — alternative backends  

**Laraclaw has:** `MetricsCollector` and `TokenUsageTracker` — but no OpenTelemetry export, no Prometheus `/metrics` endpoint, no per-invocation trace spans.  
**Laravel port:** Integrate `open-telemetry/opentelemetry-php-contrib` for trace export; expose a `/metrics` route for Prometheus scraping; add trace context to each agent invocation.

### 8. Cost Tracking Module
**ZeroClaw:** `src/cost/` — 3 files (tracker.rs 17 KB, types.rs 5 KB)  
Dedicated cost tracking with provider-specific pricing, daily/weekly/monthly aggregation.  
**Laraclaw has:** `TokenUsageTracker` and a phase 12 TODO for "Token Usage Tracking & Cost Analytics" — but the ZeroClaw implementation is a more complete reference with per-provider cost calculation and budget alerting.  
**Laravel port:** Extend `TokenUsageTracker` with provider pricing tables, budget limits, and a dashboard cost analytics panel.

### 9. Delegate Tool — Multi-Agent Task Delegation
**ZeroClaw:** `src/tools/delegate.rs` (36 KB)  
Allows one agent to delegate a sub-task to another agent instance, wait for results, and incorporate them.  
**Laraclaw has:** Multi-agent collaboration (Phase 10) with planner/executor/reviewer — but no dynamic delegation as a callable tool.  
**Laravel port:** A `DelegateSkill` that creates a sub-conversation with a secondary `CoreAgent` configured for the specific sub-task, returns results to the parent conversation.

### 10. Cron CRUD Tools
**ZeroClaw:** 6 files — `cron_add.rs`, `cron_list.rs`, `cron_remove.rs`, `cron_run.rs`, `cron_runs.rs`, `cron_update.rs`  
Full CRUD for scheduled tasks as AI tools the agent can invoke.  
**Laraclaw has:** `SchedulerSkill` + dashboard UI + `laraclaw:run-scheduled-tasks` command — but the ZeroClaw version exposes individual tools for add/remove/update/run/view-history, letting the agent manage its own cron programmatically.  
**Laravel port:** Decompose `SchedulerSkill` into granular tools matching these 6 operations.

### 11. HTTP Request + Web Fetch/Search Tools
**ZeroClaw:**  
- `http_request.rs` (30 KB) — Generic HTTP client tool (GET, POST, PUT, DELETE with headers/body)  
- `web_fetch.rs` (26 KB) — Fetches web page content, extracts text/markdown  
- `web_search_tool.rs` (11 KB) — Web search via API  

**Laraclaw has:** `WebSearchSkill` — but no generic HTTP request tool or content extraction tool.  
**Laravel port:** `HttpRequestSkill` wrapping Laravel's HTTP client; `WebFetchSkill` with HTML → Markdown conversion (readability extraction).

### 12. PDF Read Tool
**ZeroClaw:** `src/tools/pdf_read.rs` (19 KB)  
Reads and extracts text from PDF files.  
**Why it matters:** Users frequently share PDFs. Combined with document ingestion, this enables the agent to read attachments.  
**Laravel port:** A `PdfReadSkill` using `smalot/pdfparser` or `spatie/pdf-to-text`.

---

## Priority 3 — Nice-to-Have / Future

### 13. Git Operations Tool
**ZeroClaw:** `src/tools/git_operations.rs` (29 KB)  
Full git workflow: status, diff, commit, push, branch, log, etc.  
**Laravel port:** `GitSkill` shelling out to `git` CLI.

### 14. Pushover Notifications
**ZeroClaw:** `src/tools/pushover.rs` (14 KB)  
Push notification delivery via Pushover API.  
**Laraclaw has:** Notification infrastructure (Phase 12 planned) but no Pushover integration.  
**Laravel port:** Add Pushover as a notification channel.

### 15. Model Routing Config Tool
**ZeroClaw:** `src/tools/model_routing_config.rs` (38 KB)  
Lets the agent dynamically switch between AI providers/models based on task type, cost, and capability.  
**Why it matters:** Auto-routing cheap queries to small models, complex ones to GPT-4/Claude.  
**Laravel port:** A `ModelRoutingSkill` that updates Prism provider config at runtime.

### 16. Image Info Tool
**ZeroClaw:** `src/tools/image_info.rs` (17 KB)  
Extracts metadata and analysis from images (dimensions, format, EXIF data).  
**Laravel port:** `ImageInfoSkill` using `intervention/image`.

### 17. Content/Glob Search Tools
**ZeroClaw:** `content_search.rs` (32 KB), `glob_search.rs` (14 KB)  
Search file contents by text pattern and find files by glob pattern within the workspace.  
**Laravel port:** Extend `FileSystemSkill` with search sub-commands.

### 18. Hooks System
**ZeroClaw:** `src/hooks/`  
Lifecycle hooks for extensibility (before/after agent run, tool execution, message processing).  
**Laraclaw has:** Laravel events/listeners — but no formal hook registration API.  
**Laravel port:** Define explicit hook points as Laravel events with a `HookManager` registration facade.

### 19. Proxy Config Tool
**ZeroClaw:** `src/tools/proxy_config.rs` (18 KB)  
Network proxy configuration for outbound requests.  
**Laravel port:** A config-based proxy setting in `laraclaw.php` + a tool to toggle it.

### 20. Hardware Tools (IoT/Embedded)
**ZeroClaw:** `hardware_board_info.rs`, `hardware_memory_map.rs`, `hardware_memory_read.rs`  
Board diagnostics, memory mapping, register reading — for Raspberry Pi / embedded deployment.  
**Relevance:** Only if targeting embedded Laravel (unlikely near-term). Lower priority.

### 21. CLI Discovery
**ZeroClaw:** `src/tools/cli_discovery.rs` (6.7 KB)  
Dynamically discovers available CLI tools on the host system.  
**Laravel port:** `CliDiscoverySkill` scanning PATH for known utilities.

### 22. Composio Integration
**ZeroClaw:** `src/tools/composio.rs` (68 KB)  
Integration with [Composio](https://composio.dev/) — third-party automation platform that provides 250+ app actions.  
**Laravel port:** A `ComposioSkill` wrapping their REST API.

---

## Priority 4 — Architecture/Infra Patterns (Not Features)

### 23. Daemon Mode
**ZeroClaw:** `src/daemon/` — Long-running background process with signal handling, PID management, graceful shutdown.  
**Laraclaw equivalent:** Laravel queues + Horizon already provide this. Not a gap per se, but ZeroClaw's standalone daemon model is relevant if deploying on minimal hardware.

### 24. Onboarding Wizard
**ZeroClaw:** `src/onboard/wizard.rs` — Interactive setup wizard with MCP (Model Context Protocol) references.  
**Laraclaw has:** `laraclaw:install` command — but no interactive wizard UX.  
**Laravel port:** Enhance `laraclaw:install` with `laravel/prompts` for a step-by-step wizard.

### 25. Doctor / Health Modules
**ZeroClaw:** `src/doctor/`, `src/health/` — System diagnostics and health checks.  
**Laraclaw has:** `laraclaw:doctor` and `laraclaw:status` commands — likely already covers this.

### 26. RAG Module
**ZeroClaw:** `src/rag/` — Dedicated retrieval-augmented generation pipeline.  
**Laraclaw has:** Vector embeddings, document ingestion, semantic search — but as scattered services, not a unified RAG pipeline class.  
**Laravel port:** Unified `RagPipeline` service composing retrieval → reranking → context injection.

---

## Integrations Registry: Missing Channels & Providers

ZeroClaw's registry (`src/integrations/registry.rs`) lists 70+ integrations. Key ones **not in Laraclaw**:

### Chat Channels (Laraclaw has: Telegram, Discord, WhatsApp, CLI)
| Missing | Status in ZeroClaw | Priority |
|---------|-------------------|----------|
| Signal | Available | High |
| iMessage | Available | Medium |
| Slack | Available | High |
| Matrix | Available | Medium |
| Webhooks (generic) | Available | High |
| Microsoft Teams | ComingSoon | Medium |
| Nostr | ComingSoon | Low |
| Zalo | ComingSoon | Low |
| DingTalk | Available | Low |

### AI Providers (Laraclaw has: OpenAI, Anthropic, Ollama via Prism)
ZeroClaw lists 28 providers. Notable missing ones that Prism may not cover:
| Provider | Notes |
|----------|-------|
| OpenRouter | Meta-router, accesses many models |
| DeepSeek | Strong code models |
| xAI (Grok) | Twitter/X integration |
| Mistral | European LLMs |
| Perplexity | Search-augmented |
| Google (Gemini) | Via Prism, but verify |
| Venice | Privacy-focused |
| Groq | Ultra-fast inference |
| Together AI | Open-source hosting |
| Fireworks AI | Fast open-source |

### Productivity (All ComingSoon in ZeroClaw)
GitHub, Notion, Apple Notes, Apple Reminders, Obsidian, Things 3, Bear Notes, Trello, Linear

### Smart Home (All ComingSoon)
Home Assistant, Philips Hue, 8Sleep

### Music (All ComingSoon)
Spotify, Sonos, Shazam

---

## MCP (Model Context Protocol) Support
ZeroClaw's MCP references are minimal (3 code search hits: README.md, providers-reference.md, onboard/wizard.rs). There is **no dedicated MCP server module** — it appears to be referenced as a future direction. Laraclaw already has `laravel/mcp` as a dependency, which puts it *ahead* of ZeroClaw in this area.

---

## Summary: Top 10 Features to Port Next

| # | Feature | Effort | Impact | ZeroClaw Source |
|---|---------|--------|--------|-----------------|
| 1 | HEARTBEAT.md Engine | Small | High | `src/heartbeat/` |
| 2 | Approval System | Medium | High | `src/approval/` |
| 3 | Screenshot Tool | Small | High | `src/tools/screenshot.rs` |
| 4 | SOP Engine | Large | High | `src/sop/` |
| 5 | HTTP Request Tool | Small | Medium | `src/tools/http_request.rs` |
| 6 | Web Fetch/Extract Tool | Small | Medium | `src/tools/web_fetch.rs` |
| 7 | PDF Read Tool | Small | Medium | `src/tools/pdf_read.rs` |
| 8 | Cron CRUD Tools | Medium | Medium | `src/tools/cron_*.rs` |
| 9 | SkillForge | Medium | Medium | `src/skillforge/` |
| 10 | Browser Automation | Large | High | `src/tools/browser.rs` |
