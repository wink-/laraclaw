# Changelog

## [Unreleased]

### Added
- NVIDIA NIM provider (`config/ai.php`, `ProviderCatalog`) using OpenAI-compatible driver at `https://integrate.api.nvidia.com/v1`
- NVIDIA model catalog with 8 NIM models: Llama 3.3 70B, Llama 3.1 405B, Nemotron 70B, Nemotron Super 49B, DeepSeek R1, Qwen 2.5 72B, Gemma 2 27B, Mixtral 8x22B (`config/laraclaw-models.php`)

### Changed
- Refactored provider storage from SQLite database to file-based config (`config/laraclaw-providers.php`), aligned with Laravel AI SDK's config-driven provider pattern
- Removed `LaraclawProvider` Eloquent model, migration, and factory (no longer needed)
- Removed `Schema::hasTable()` guard from `LaraclawServiceProvider::boot()` — providers now load from config unconditionally

### Fixed
- ZAI provider was hitting the general API endpoint instead of the Coding Plan endpoint due to missing `/coding/` path in the URL, causing rate limit errors (`config/ai.php`)
- Invalid ZAI model names in model catalog replaced with correct ones from Z.AI OpenClaw docs (`config/laraclaw-models.php`)
- `ZaiProvider::cheapestTextModel()` fallback referenced nonexistent `glm-5-turbo`, updated to `glm-4.5-air`
- Added explicit `zai` case in `ProviderCatalog::resolveLabFromDriver()` instead of relying on default fallback
