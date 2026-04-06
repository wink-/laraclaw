<?php

namespace App\Laraclaw\AI;

class ModelCatalog
{
    /**
     * Get all models for a given provider.
     *
     * @return array<string, array{name: string, context: int}>
     */
    public function getModels(string $provider): array
    {
        return config("laraclaw-models.{$provider}", []);
    }

    /**
     * Get all models for every provider.
     *
     * @return array<string, array<string, array{name: string, context: int}>>
     */
    public function all(): array
    {
        return config('laraclaw-models', []);
    }

    /**
     * Get models as a select-friendly options array for a provider.
     * Returns ['model-id' => 'Human Name (context window)', ...]
     */
    public function getOptionsForProvider(string $provider): array
    {
        $models = $this->getModels($provider);

        return collect($models)->mapWithKeys(fn ($model, $id) => [
            $id => "{$model['name']} ({$this->formatContext($model['context'])})",
        ])->toArray();
    }

    /**
     * Get the list of providers that have models configured.
     *
     * @return string[]
     */
    public function getProviders(): array
    {
        return array_keys($this->all());
    }

    /**
     * Check if a specific model exists for a provider.
     */
    public function hasModel(string $provider, string $model): bool
    {
        return array_key_exists($model, $this->getModels($provider));
    }

    /**
     * Get a single model's metadata.
     */
    public function getModelInfo(string $provider, string $model): ?array
    {
        return $this->getModels($provider)[$model] ?? null;
    }

    /**
     * Search models across all providers by name or id.
     */
    public function search(string $query): array
    {
        $query = strtolower($query);
        $results = [];

        foreach ($this->all() as $provider => $models) {
            foreach ($models as $id => $meta) {
                if (str_contains(strtolower($id), $query) || str_contains(strtolower($meta['name']), $query)) {
                    $results[$provider][] = ['id' => $id, ...$meta];
                }
            }
        }

        return $results;
    }

    /**
     * Add a model to the catalog (runtime + persist to config file).
     */
    public function addModel(string $provider, string $id, string $name, int $context): void
    {
        $key = "laraclaw-models.{$provider}";
        $models = config($key, []);
        $models[$id] = ['name' => $name, 'context' => $context];
        config([$key => $models]);
        $this->persist();
    }

    /**
     * Update a model's metadata in the catalog.
     */
    public function updateModel(string $provider, string $id, string $name, int $context): void
    {
        $key = "laraclaw-models.{$provider}";
        $models = config($key, []);
        $models[$id] = ['name' => $name, 'context' => $context];
        config([$key => $models]);
        $this->persist();
    }

    /**
     * Remove a model from the catalog.
     */
    public function removeModel(string $provider, string $id): void
    {
        $key = "laraclaw-models.{$provider}";
        $models = config($key, []);
        unset($models[$id]);
        config([$key => $models]);
        $this->persist();
    }

    private function persist(): void
    {
        $configPath = config_path('laraclaw-models.php');
        $data = config('laraclaw-models', []);

        $lines = ['<?php', '', 'return ['];
        foreach ($data as $provider => $models) {
            $lines[] = '';
            $lines[] = "    '{$provider}' => [";
            foreach ($models as $id => $meta) {
                $lines[] = "        '{$id}' => ['name' => '{$meta['name']}', 'context' => {$meta['context']}],";
            }
            $lines[] = '    ],';
        }
        $lines[] = '];';
        $lines[] = '';

        file_put_contents($configPath, implode("\n", $lines));
    }

    private function formatContext(int $tokens): string
    {
        if ($tokens >= 1_000_000) {
            return round($tokens / 1_000_000, 1).'M';
        }

        return round($tokens / 1_000).'K';
    }
}
