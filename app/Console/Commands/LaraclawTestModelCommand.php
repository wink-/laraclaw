<?php

namespace App\Console\Commands;

use App\Laraclaw\Agents\CoreAgent;
use App\Laraclaw\AI\ModelCatalog;
use Illuminate\Console\Command;

use function Laravel\Prompts\select;

class LaraclawTestModelCommand extends Command
{
    protected $signature = 'laraclaw:test-model
                            {--provider= : Provider key (openai, anthropic, openrouter, etc.)}
                            {--model= : Model ID from catalog}
                            {--prompt=Hello : Prompt to send}
                            {--all : Run every model in every provider}';

    protected $description = 'Test AI models from the catalog';

    public function handle(ModelCatalog $catalog, CoreAgent $agent): int
    {
        $prompt = $this->option('prompt');

        if ($this->option('all')) {
            return $this->testAll($catalog, $agent, $prompt);
        }

        $provider = $this->option('provider');
        $model = $this->option('model');

        if ($provider && $model) {
            return $this->testSingle($catalog, $agent, $provider, $model, $prompt);
        }

        return $this->interactive($catalog, $agent, $prompt);
    }

    protected function testAll(ModelCatalog $catalog, CoreAgent $agent, string $prompt): int
    {
        $this->info("Testing all models with prompt: \"{$prompt}\"");
        $this->newLine();

        $originalProvider = $agent->provider()->value;
        $originalModel = $agent->model();

        $results = [];

        foreach ($catalog->all() as $provider => $models) {
            foreach (array_keys($models) as $modelId) {
                $results[] = $this->runTest($agent, $provider, $modelId, $prompt);
            }
        }

        $agent->applyProviderOverride($originalProvider);
        $agent->applyModelOverride($originalModel);

        $this->displayResults($results);

        $failures = count(array_filter($results, fn ($r) => $r['status'] === 'fail'));

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }

    protected function testSingle(ModelCatalog $catalog, CoreAgent $agent, string $provider, string $model, string $prompt): int
    {
        if (! $catalog->hasModel($provider, $model)) {
            $this->error("Model \"{$model}\" not found in provider \"{$provider}\".");

            return self::FAILURE;
        }

        $originalProvider = $agent->provider()->value;
        $originalModel = $agent->model();

        $this->info("Testing [{$provider}] {$model} with prompt: \"{$prompt}\"");
        $this->newLine();

        $result = $this->runTest($agent, $provider, $model, $prompt);

        $agent->applyProviderOverride($originalProvider);
        $agent->applyModelOverride($originalModel);

        $this->displayResults([$result]);

        return $result['status'] === 'fail' ? self::FAILURE : self::SUCCESS;
    }

    protected function interactive(ModelCatalog $catalog, CoreAgent $agent, string $prompt): int
    {
        $providers = $catalog->getProviders();

        if (empty($providers)) {
            $this->error('No providers configured in the model catalog.');

            return self::FAILURE;
        }

        $provider = select('Select a provider', array_combine($providers, $providers));

        $models = $catalog->getModels($provider);

        if (empty($models)) {
            $this->error("No models found for provider \"{$provider}\".");

            return self::FAILURE;
        }

        $modelOptions = [];
        foreach ($models as $id => $meta) {
            $modelOptions[$id] = $meta['name'];
        }

        $model = select('Select a model', $modelOptions);

        return $this->testSingle($catalog, $agent, $provider, $model, $prompt);
    }

    protected function runTest(CoreAgent $agent, string $provider, string $model, string $prompt): array
    {
        $start = microtime(true);

        try {
            $agent->applyProviderOverride($provider);
            $agent->applyModelOverride($model);

            $response = $agent->prompt($prompt);
            $elapsed = round((microtime(true) - $start) * 1000);

            $text = $response->text();
            $truncated = mb_strlen($text) > 200 ? mb_substr($text, 0, 200).'...' : $text;

            return [
                'provider' => $provider,
                'model' => $model,
                'response' => $truncated,
                'time' => $elapsed,
                'status' => 'pass',
            ];
        } catch (\Throwable $e) {
            $elapsed = round((microtime(true) - $start) * 1000);

            return [
                'provider' => $provider,
                'model' => $model,
                'response' => $e->getMessage(),
                'time' => $elapsed,
                'status' => 'fail',
            ];
        }
    }

    protected function displayResults(array $results): void
    {
        $rows = array_map(fn ($r) => [
            $r['provider'],
            $r['model'],
            $r['response'],
            $r['time'].'ms',
            $r['status'] === 'pass' ? '<info>PASS</info>' : '<error>FAIL</error>',
        ], $results);

        $this->table(['Provider', 'Model', 'Response', 'Time', 'Status'], $rows);
    }
}
