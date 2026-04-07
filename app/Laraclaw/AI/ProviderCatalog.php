<?php

namespace App\Laraclaw\AI;

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
        return config('laraclaw-providers', []);
    }

    /**
     * Get a specific custom provider.
     *
     * @return array{name: string, driver: string, key_env: string, url: ?string}|null
     */
    public function get(string $key): ?array
    {
        return config("laraclaw-providers.{$key}");
    }

    /**
     * Check if a custom provider exists.
     */
    public function has(string $key): bool
    {
        return config("laraclaw-providers.{$key}") !== null;
    }

    /**
     * Get the list of custom provider keys.
     *
     * @return string[]
     */
    public function getProviderKeys(): array
    {
        return array_keys(config('laraclaw-providers', []));
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
     * Add a custom provider.
     */
    public function addProvider(string $key, string $name, string $driver, string $keyEnv, ?string $url = null): void
    {
        $providers = config('laraclaw-providers', []);
        $providers[$key] = [
            'name' => $name,
            'driver' => $driver,
            'key_env' => $keyEnv,
            'url' => $url,
        ];
        config(['laraclaw-providers' => $providers]);
        $this->persist();

        $this->registerProvider($key);
    }

    /**
     * Update a custom provider.
     */
    public function updateProvider(string $key, string $name, string $driver, string $keyEnv, ?string $url = null): void
    {
        $providers = config('laraclaw-providers', []);
        $providers[$key] = [
            'name' => $name,
            'driver' => $driver,
            'key_env' => $keyEnv,
            'url' => $url,
        ];
        config(['laraclaw-providers' => $providers]);
        $this->persist();

        $this->registerProvider($key);
    }

    /**
     * Remove a custom provider.
     */
    public function removeProvider(string $key): void
    {
        $providers = config('laraclaw-providers', []);
        unset($providers[$key]);
        config(['laraclaw-providers' => $providers]);
        $this->persist();
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
     * Persist the current providers config to disk.
     */
    private function persist(): void
    {
        $providers = config('laraclaw-providers', []);

        $content = "<?php\n\n"
            ."/*\n"
            ."|--------------------------------------------------------------------------\n"
            ."| Custom AI Providers\n"
            ."|--------------------------------------------------------------------------\n"
            ."|\n"
            ."| Custom providers managed via the Providers UI. Each provider maps to a\n"
            ."| built-in driver with a custom API key (resolved from the named env var)\n"
            ."| and an optional base URL override.\n"
            ."|\n"
            ."*/\n\n"
            .'return '.var_export($providers, true).";\n";

        file_put_contents(config_path('laraclaw-providers.php'), $content);
    }
}
