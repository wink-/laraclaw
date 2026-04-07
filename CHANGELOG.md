# Changelog

## [Unreleased]

### Changed
- Refactored provider storage from SQLite database to file-based config (`config/laraclaw-providers.php`), aligned with Laravel AI SDK's config-driven provider pattern
- Removed `LaraclawProvider` Eloquent model, migration, and factory (no longer needed)
- Removed `Schema::hasTable()` guard from `LaraclawServiceProvider::boot()` — providers now load from config unconditionally

### Fixed
- ZAI provider was hitting the general API endpoint instead of the Coding Plan endpoint due to missing `/coding/` path in the URL, causing rate limit errors (`config/ai.php`)
- Invalid ZAI model names in model catalog replaced with correct ones from Z.AI OpenClaw docs (`config/laraclaw-models.php`)
- `ZaiProvider::cheapestTextModel()` fallback referenced nonexistent `glm-5-turbo`, updated to `glm-4.5-air`
- Added explicit `zai` case in `ProviderCatalog::resolveLabFromDriver()` instead of relying on default fallback
