<?php

namespace App\Laraclaw\AI;

use App\Models\ModelBenchmark;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class ModelBenchmarkRepository
{
    public function isReady(): bool
    {
        return Schema::hasTable('model_benchmarks');
    }

    public function recordResult(
        string $provider,
        string $modelId,
        string $modelName,
        ?int $responseTimeMs,
        string $status,
        ?string $responseExcerpt = null,
        ?string $errorMessage = null,
    ): ModelBenchmark {
        if (! $this->isReady()) {
            throw new RuntimeException('Benchmark storage is not ready. Run php artisan migrate.');
        }

        $benchmark = ModelBenchmark::query()->firstOrNew([
            'provider' => $provider,
            'model_id' => $modelId,
        ]);

        $benchmark->model_name = $modelName;
        $benchmark->last_status = $status;
        $benchmark->last_response_time_ms = $responseTimeMs;
        $benchmark->last_response_excerpt = $responseExcerpt;
        $benchmark->last_error_message = $errorMessage;
        $benchmark->last_tested_at = now();
        $benchmark->total_runs = (int) $benchmark->total_runs + 1;

        if ($status === 'pass') {
            $previousSuccessfulRuns = (int) $benchmark->successful_runs;
            $benchmark->successful_runs = $previousSuccessfulRuns + 1;

            if ($responseTimeMs !== null) {
                $benchmark->fastest_response_time_ms = $benchmark->fastest_response_time_ms === null
                    ? $responseTimeMs
                    : min($benchmark->fastest_response_time_ms, $responseTimeMs);

                $benchmark->slowest_response_time_ms = $benchmark->slowest_response_time_ms === null
                    ? $responseTimeMs
                    : max($benchmark->slowest_response_time_ms, $responseTimeMs);

                $benchmark->average_response_time_ms = $previousSuccessfulRuns === 0
                    ? $responseTimeMs
                    : round(((((float) $benchmark->average_response_time_ms) * $previousSuccessfulRuns) + $responseTimeMs) / ($previousSuccessfulRuns + 1), 2);
            }
        } else {
            $benchmark->failed_runs = (int) $benchmark->failed_runs + 1;
        }

        $benchmark->save();

        return $benchmark->fresh();
    }

    public function getIndexedStats(): array
    {
        if (! $this->isReady()) {
            return [];
        }

        return ModelBenchmark::query()
            ->get()
            ->mapWithKeys(fn (ModelBenchmark $benchmark) => [
                "{$benchmark->provider}|{$benchmark->model_id}" => $this->toArray($benchmark),
            ])
            ->all();
    }

    public function rankedModels(): Collection
    {
        if (! $this->isReady()) {
            return collect();
        }

        return ModelBenchmark::query()
            ->orderByRaw('CASE WHEN successful_runs > 0 THEN 0 ELSE 1 END')
            ->orderByRaw('COALESCE(average_response_time_ms, last_response_time_ms, 999999999) asc')
            ->orderByDesc('successful_runs')
            ->get()
            ->map(fn (ModelBenchmark $benchmark) => $this->toArray($benchmark))
            ->values();
    }

    public function rankedProviders(): Collection
    {
        if (! $this->isReady()) {
            return collect();
        }

        $rankings = ModelBenchmark::query()
            ->get()
            ->groupBy('provider')
            ->map(function (Collection $benchmarks, string $provider): array {
                $successfulBenchmarks = $benchmarks->filter(fn (ModelBenchmark $benchmark) => $benchmark->successful_runs > 0);

                return [
                    'provider' => $provider,
                    'models_tested' => $benchmarks->count(),
                    'total_runs' => $benchmarks->sum('total_runs'),
                    'successful_runs' => $benchmarks->sum('successful_runs'),
                    'failed_runs' => $benchmarks->sum('failed_runs'),
                    'success_rate' => $this->calculateSuccessRate(
                        (int) $benchmarks->sum('successful_runs'),
                        (int) $benchmarks->sum('total_runs'),
                    ),
                    'average_response_time_ms' => $successfulBenchmarks->isEmpty()
                        ? null
                        : round((float) $successfulBenchmarks->avg(fn (ModelBenchmark $benchmark) => (float) ($benchmark->average_response_time_ms ?? $benchmark->last_response_time_ms)), 2),
                    'fastest_response_time_ms' => $successfulBenchmarks->isEmpty()
                        ? null
                        : $successfulBenchmarks->min('fastest_response_time_ms'),
                    'latest_tested_at_human' => $benchmarks->max('last_tested_at')?->diffForHumans(),
                ];
            })
            ->values()
            ->all();

        usort($rankings, function (array $left, array $right): int {
            $leftAverage = $left['average_response_time_ms'] ?? PHP_INT_MAX;
            $rightAverage = $right['average_response_time_ms'] ?? PHP_INT_MAX;

            if ($leftAverage === $rightAverage) {
                return $right['success_rate'] <=> $left['success_rate'];
            }

            return $leftAverage <=> $rightAverage;
        });

        return collect($rankings);
    }

    public function recentFailures(int $limit = 10): Collection
    {
        if (! $this->isReady()) {
            return collect();
        }

        return ModelBenchmark::query()
            ->where('last_status', 'fail')
            ->orderByDesc('last_tested_at')
            ->limit($limit)
            ->get()
            ->map(fn (ModelBenchmark $benchmark) => $this->toArray($benchmark))
            ->values();
    }

    private function toArray(ModelBenchmark $benchmark): array
    {
        return [
            'provider' => $benchmark->provider,
            'model_id' => $benchmark->model_id,
            'model_name' => $benchmark->model_name,
            'last_status' => $benchmark->last_status,
            'last_response_time_ms' => $benchmark->last_response_time_ms,
            'fastest_response_time_ms' => $benchmark->fastest_response_time_ms,
            'slowest_response_time_ms' => $benchmark->slowest_response_time_ms,
            'average_response_time_ms' => $benchmark->average_response_time_ms === null ? null : round((float) $benchmark->average_response_time_ms),
            'last_response_excerpt' => $benchmark->last_response_excerpt,
            'last_error_message' => $benchmark->last_error_message,
            'last_tested_at_human' => $benchmark->last_tested_at?->diffForHumans(),
            'total_runs' => $benchmark->total_runs,
            'successful_runs' => $benchmark->successful_runs,
            'failed_runs' => $benchmark->failed_runs,
            'success_rate' => $this->calculateSuccessRate($benchmark->successful_runs, $benchmark->total_runs),
        ];
    }

    private function calculateSuccessRate(int $successfulRuns, int $totalRuns): float
    {
        if ($totalRuns === 0) {
            return 0.0;
        }

        return round(($successfulRuns / $totalRuns) * 100, 1);
    }
}
