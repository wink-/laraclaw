<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class LaraclawHealthCommand extends Command
{
    protected $signature = 'laraclaw:health
                            {--json : Output as JSON}';

    protected $description = 'Check Laraclaw system health';

    protected array $checks = [];

    public function handle(): int
    {
        $this->runChecks();

        if ($this->option('json')) {
            $this->line(json_encode($this->checks, JSON_PRETTY_PRINT));

            return $this->checks['status'] === 'healthy' ? self::SUCCESS : self::FAILURE;
        }

        $this->displayResults();

        return $this->checks['status'] === 'healthy' ? self::SUCCESS : self::FAILURE;
    }

    protected function runChecks(): void
    {
        $this->checks = [
            'status' => 'healthy',
            'timestamp' => now()->toIso8601String(),
            'checks' => [],
        ];

        // Database check
        try {
            DB::connection()->getPdo();
            $this->checks['checks']['database'] = ['status' => 'healthy', 'message' => 'Connected'];
        } catch (\Throwable $e) {
            $this->checks['checks']['database'] = ['status' => 'unhealthy', 'message' => $e->getMessage()];
            $this->checks['status'] = 'unhealthy';
        }

        // Cache check
        try {
            Cache::put('laraclaw.health.check', 'ok', 10);
            $value = Cache::get('laraclaw.health.check');
            if ($value === 'ok') {
                $this->checks['checks']['cache'] = ['status' => 'healthy', 'message' => 'Working'];
            } else {
                $this->checks['checks']['cache'] = ['status' => 'unhealthy', 'message' => 'Cache read failed'];
                $this->checks['status'] = 'unhealthy';
            }
        } catch (\Throwable $e) {
            $this->checks['checks']['cache'] = ['status' => 'unhealthy', 'message' => $e->getMessage()];
            $this->checks['status'] = 'unhealthy';
        }

        // AI Provider check
        $provider = config('laraclaw.ai.provider', 'openai');
        $hasKey = match ($provider) {
            'openai' => ! empty(env('OPENAI_API_KEY')),
            'anthropic' => ! empty(env('ANTHROPIC_API_KEY')),
            'openrouter' => ! empty(env('OPENROUTER_API_KEY')),
            'ollama' => true,
            default => false,
        };
        $this->checks['checks']['ai_provider'] = [
            'status' => $hasKey ? 'healthy' : 'degraded',
            'message' => $hasKey ? "Provider: {$provider}" : "Provider: {$provider} (not configured)",
        ];

        // Gateways check
        $gateways = [
            'telegram' => ! empty(env('TELEGRAM_BOT_TOKEN')),
            'discord' => ! empty(env('DISCORD_BOT_TOKEN')),
        ];
        $this->checks['checks']['gateways'] = [
            'status' => 'healthy',
            'message' => implode(', ', array_keys(array_filter($gateways))) ?: 'None configured',
        ];
    }

    protected function displayResults(): void
    {
        $status = $this->checks['status'];
        $icon = $status === 'healthy' ? '✅' : '❌';

        $this->newLine();
        $this->info("{$icon} Laraclaw Health Check - {$status}");
        $this->info('================================');
        $this->newLine();

        foreach ($this->checks['checks'] as $name => $check) {
            $checkIcon = $check['status'] === 'healthy' ? '✓' : ($check['status'] === 'degraded' ? '⚠' : '✗');
            $this->line("  {$checkIcon} {$name}: {$check['message']}");
        }

        $this->newLine();
    }
}
