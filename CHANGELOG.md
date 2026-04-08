# Changelog

## [Unreleased]

### Added
- NVIDIA NIM provider (`config/ai.php`, `ProviderCatalog`) at `https://integrate.api.nvidia.com/v1`
- NVIDIA model catalog with verified current models: Kimi K2 Instruct, Step 3.5 Flash, Nemotron 3 Super 120B, Qwen 3.5 122B, Gemma 4 31B, Mistral Small 4 119B (`config/laraclaw-models.php`)

### Changed
- NVIDIA provider uses `groq` driver instead of `openai` — Prism's OpenAI handler targets the new `/responses` endpoint which NVIDIA doesn't support; Groq handler uses standard `/chat/completions` which NVIDIA expects
- Refactored provider storage from SQLite database to file-based config (`config/laraclaw-providers.php`), aligned with Laravel AI SDK's config-driven provider pattern
- Removed `LaraclawProvider` Eloquent model, migration, and factory (no longer needed)
- Removed `Schema::hasTable()` guard from `LaraclawServiceProvider::boot()` — providers now load from config unconditionally

### Fixed
- ZAI provider was hitting the general API endpoint instead of the Coding Plan endpoint due to missing `/coding/` path in the URL, causing rate limit errors (`config/ai.php`)
- Invalid ZAI model names in model catalog replaced with correct ones from Z.AI OpenClaw docs (`config/laraclaw-models.php`)
- `ZaiProvider::cheapestTextModel()` fallback referenced nonexistent `glm-5-turbo`, updated to `glm-4.5-air`
- Added explicit `zai` case in `ProviderCatalog::resolveLabFromDriver()` instead of relying on default fallback
