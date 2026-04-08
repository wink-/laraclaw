<?php

use App\Laraclaw\Agents\CoreAgent;
use App\Models\ModelBenchmark;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Laravel\Ai\Enums\Lab;
use Livewire\Volt\Volt;

uses()->beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('testing a model stores a passing benchmark record', function () {
    $agent = new class extends CoreAgent
    {
        public function provider(): Lab
        {
            return Lab::OpenAI;
        }

        public function model(): string
        {
            return 'gpt-4o';
        }

        public function applyProviderOverride(string $provider): void {}

        public function applyModelOverride(string $model): void {}

        public function prompt(string $message): string
        {
            return 'Hello! Fast response.';
        }
    };

    app()->instance(CoreAgent::class, $agent);

    Volt::test('laraclaw.models')
        ->call('testModel', 'openai', 'gpt-4o');

    $benchmark = ModelBenchmark::query()->where('provider', 'openai')->where('model_id', 'gpt-4o')->first();

    expect($benchmark)->not->toBeNull()
        ->and($benchmark->last_status)->toBe('pass')
        ->and($benchmark->successful_runs)->toBe(1)
        ->and($benchmark->failed_runs)->toBe(0)
        ->and($benchmark->last_response_excerpt)->toContain('Hello! Fast response.');
});

test('testing a model stores failing benchmark details', function () {
    $agent = new class extends CoreAgent
    {
        public function provider(): Lab
        {
            return Lab::OpenAI;
        }

        public function model(): string
        {
            return 'gpt-4o';
        }

        public function applyProviderOverride(string $provider): void {}

        public function applyModelOverride(string $model): void {}

        public function prompt(string $message): string
        {
            throw new RuntimeException('Timed out after 60001 milliseconds');
        }
    };

    app()->instance(CoreAgent::class, $agent);

    Volt::test('laraclaw.models')
        ->call('testModel', 'nvidia', 'google/gemma-4-31b-it');

    $benchmark = ModelBenchmark::query()->where('provider', 'nvidia')->where('model_id', 'google/gemma-4-31b-it')->first();

    expect($benchmark)->not->toBeNull()
        ->and($benchmark->last_status)->toBe('fail')
        ->and($benchmark->successful_runs)->toBe(0)
        ->and($benchmark->failed_runs)->toBe(1)
        ->and($benchmark->last_error_message)->toContain('Timed out');
});

test('models page shows persisted benchmark data on the card', function () {
    ModelBenchmark::factory()->create([
        'provider' => 'openai',
        'model_id' => 'gpt-4o',
        'model_name' => 'GPT-4o',
        'last_status' => 'pass',
        'last_response_time_ms' => 1163,
        'fastest_response_time_ms' => 980,
        'slowest_response_time_ms' => 1600,
        'average_response_time_ms' => 1163,
        'total_runs' => 1,
        'successful_runs' => 1,
        'failed_runs' => 0,
    ]);

    Volt::test('laraclaw.models')
        ->assertSee('Last benchmark')
        ->assertSee('1163ms')
        ->assertSee('Avg 1163ms');
});

test('benchmarks page renders model and provider rankings', function () {
    ModelBenchmark::factory()->create([
        'provider' => 'openai',
        'model_id' => 'gpt-4o-mini',
        'model_name' => 'GPT-4o Mini',
        'last_response_time_ms' => 1163,
        'fastest_response_time_ms' => 1000,
        'average_response_time_ms' => 1163,
        'successful_runs' => 3,
        'failed_runs' => 0,
        'total_runs' => 3,
    ]);

    ModelBenchmark::factory()->create([
        'provider' => 'nvidia',
        'model_id' => 'nemotron-3-super-120b-a12b',
        'model_name' => 'Nemotron 3 Super 120B',
        'last_response_time_ms' => 12023,
        'fastest_response_time_ms' => 11800,
        'average_response_time_ms' => 12023,
        'successful_runs' => 1,
        'failed_runs' => 0,
        'total_runs' => 1,
    ]);

    Volt::test('laraclaw.benchmarks')
        ->assertSuccessful()
        ->assertSee('Benchmarks')
        ->assertSee('GPT-4o Mini')
        ->assertSee('openai')
        ->assertSee('nvidia');
});

test('models page renders without benchmark table', function () {
    Schema::dropIfExists('model_benchmarks');

    Volt::test('laraclaw.models')
        ->assertSuccessful()
        ->assertSee('Model Catalog');
});
