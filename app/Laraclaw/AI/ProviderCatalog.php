<?php

namespace App\Laraclaw\AI;

use App\Models\LaraclawProvider;
use Laravel\Ai\Enums\Lab;

class ProviderCatalog
{
    public const BUILT_IN_PROVIDERS = [
        'openai', 'anthropic', 'gemini', 'ollama', 'groq', 'mistral',
        'deepseek', 'xai', 'openrouter', 'cohere', 'azure', 'jina',
        'voyageai', 'eleven', 'zai', 'zai-anthropic',
    ];

    /**
     * Get all custom providers.
     *
     * @return array<string, array{name: string, driver: string, key_env: string, url: ?string}>
     */
    public function all(): array
    {
        return LaraclawProvider::all()->mapWithKeys(fn (LaraclawProvider $model) => [
            $model->key => $this->toArray($model),
        ])->all();
    }

    /**
     * Get a specific custom provider.
     *
     * @return array{name: string, driver: string, key_env: string, url: ?string}|null
     */
    public function get(string $key): ?array
    {
        $model = LaraclawProvider::where('key', $key)->first();

        if ($model === null) {
            return null;
        }

        return $this->toArray($model);
    }

    /**
     * Check if a custom provider exists.
     */
    public function has(string $key): bool
    {
        return LaraclawProvider::where('key', $key)->exists();
    }

    /**
     * Get the list of custom provider keys.
     *
     * @return string[]
     */
    public function getProviderKeys(): array
    {
        return LaraclawProvider::pluck('key')->all();
    }

    /**
     * Get the driver for a custom provider.
     */
    public function getDriverForProvider(string $key): ?string
    {
        return $this->get($key)['driver'] ?? null;
    }

    /**
     * Resolve a custom provider key to its Lab enum value.
     */
    public function resolveLabForProvider(string $key): ?Lab
    {
        $driver = $this->getDriverForProvider($key);

        if ($driver === null) {
            return null;
        }

        return $this->resolveLabFromDriver($driver);
    }

    /**
     * Get the available driver options for the UI dropdown.
     *
     * @return array<string, string>
     */
    public function getAvailableDrivers(): array
    {
        return [
            'openai' => 'OpenAI',
            'anthropic' => 'Anthropic',
            'gemini' => 'Google Gemini',
            'ollama' => 'Ollama',
            'groq' => 'Groq',
            'mistral' => 'Mistral',
            'deepseek' => 'DeepSeek',
            'xai' => 'xAI (Grok)',
            'openrouter' => 'OpenRouter',
            'cohere' => 'Cohere',
            'azure' => 'Azure OpenAI',
        ];
    }

    /**
     * Add a custom provider (persist to DB).
     */
    public function addProvider(string $key, string $name, string $driver, string $keyEnv, ?string $url = null): void
    {
        LaraclawProvider::create([
            'key' => $key,
            'name' => $name,
            'driver' => $driver,
            'key_env' => $keyEnv,
            'url' => $url,
        ]);

        $this->registerProvider($key);
    }

    /**
     * Update a custom provider.
     */
    public function updateProvider(string $key, string $name, string $driver, string $keyEnv, ?string $url = null): void
    {
        $model = LaraclawProvider::where('key', $key)->first();

        if ($model !== null) {
            $model->update([
                'name' => $name,
                'driver' => $driver,
                'key_env' => $keyEnv,
                'url' => $url,
            ]);
        }

        $this->registerProvider($key);
    }

    /**
     * Remove a custom provider.
     */
    public function removeProvider(string $key): void
    {
        LaraclawProvider::where('key', $key)->delete();
    }

    /**
     * Register all custom providers into the Laravel AI config.
     */
    public function registerProviders(): void
    {
        foreach ($this->all() as $key => $provider) {
            $this->registerProvider($key);
        }
    }

    /**
     * Register a single provider into the Laravel AI config.
     */
    public function registerProvider(string $key): void
    {
        $provider = $this->get($key);

        if ($provider === null) {
            return;
        }

        $config = [
            'driver' => $provider['driver'],
            'key' => getenv($provider['key_env']) ?: null,
        ];

        if ($provider['url'] !== null && $provider['url'] !== '') {
            $config['url'] = $provider['url'];
        }

        config(["ai.providers.{$key}" => $config]);
    }

    /**
     * Resolve a driver string to its Lab enum value.
     */
    public function resolveLabFromDriver(string $driver): Lab
    {
        return match ($driver) {
            'openai' => Lab::OpenAI,
            'anthropic' => Lab::Anthropic,
            'gemini' => Lab::Gemini,
            'ollama' => Lab::Ollama,
            'groq' => Lab::Groq,
            'mistral' => Lab::Mistral,
            'deepseek' => Lab::DeepSeek,
            'xai' => Lab::xAI,
            'openrouter' => Lab::OpenRouter,
            'cohere' => Lab::Cohere,
            'azure' => Lab::Azure,
            'zai' => Lab::OpenAI,
            default => Lab::OpenAI,
        };
    }

    /**
     * Check whether a custom provider's API key is set in the environment.
     */
    public function hasApiKey(string $key): bool
    {
        $provider = $this->get($key);

        if ($provider === null) {
            return false;
        }

        $value = getenv($provider['key_env']);

        return $value !== false && $value !== '';
    }

    /**
     * Convert a LaraclawProvider model to its array representation.
     *
     * @return array{name: string, driver: string, key_env: string, url: ?string}
     */
    private function toArray(LaraclawProvider $model): array
    {
        return [
            'name' => $model->name,
            'driver' => $model->driver,
            'key_env' => $model->key_env,
            'url' => $model->url,
        ];
    }
}
