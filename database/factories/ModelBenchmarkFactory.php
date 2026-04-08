<?php

namespace Database\Factories;

use App\Models\ModelBenchmark;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ModelBenchmark>
 */
class ModelBenchmarkFactory extends Factory
{
    protected $model = ModelBenchmark::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $responseTime = fake()->numberBetween(700, 5000);
        $successfulRuns = fake()->numberBetween(1, 8);
        $failedRuns = fake()->numberBetween(0, 2);

        return [
            'provider' => fake()->randomElement(['openai', 'anthropic', 'openrouter', 'nvidia']),
            'model_id' => fake()->slug(2),
            'model_name' => fake()->words(2, true),
            'last_status' => 'pass',
            'last_response_time_ms' => $responseTime,
            'fastest_response_time_ms' => max(200, $responseTime - fake()->numberBetween(50, 250)),
            'slowest_response_time_ms' => $responseTime + fake()->numberBetween(50, 500),
            'average_response_time_ms' => $responseTime,
            'last_response_excerpt' => fake()->sentence(),
            'last_error_message' => null,
            'last_tested_at' => now()->subMinutes(fake()->numberBetween(1, 180)),
            'total_runs' => $successfulRuns + $failedRuns,
            'successful_runs' => $successfulRuns,
            'failed_runs' => $failedRuns,
        ];
    }
}
