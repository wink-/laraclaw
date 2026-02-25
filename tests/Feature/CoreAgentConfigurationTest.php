<?php

use App\Laraclaw\Agents\CoreAgent;
use Laravel\Ai\Enums\Lab;

it('uses agent-specific provider and model when configured', function () {
    config()->set('laraclaw.ai.provider', 'openai');
    config()->set('laraclaw.ai.model', 'gpt-4o-mini');
    config()->set('laraclaw.ai.agents.builder.provider', 'anthropic');
    config()->set('laraclaw.ai.agents.builder.model', 'claude-opus-4-20250514');

    $agent = new CoreAgent(collect());

    $method = new ReflectionMethod(CoreAgent::class, 'configureProvider');
    $method->setAccessible(true);
    $method->invoke($agent, 'builder');

    expect($agent->provider())->toBe(Lab::Anthropic)
        ->and($agent->model())->toBe('claude-opus-4-20250514');
});

it('falls back to global provider and model for unknown agent key', function () {
    config()->set('laraclaw.ai.provider', 'gemini');
    config()->set('laraclaw.ai.model', 'gemini-2.5-flash');
    config()->set('laraclaw.ai.agents.builder.provider', 'anthropic');
    config()->set('laraclaw.ai.agents.builder.model', 'claude-opus-4-20250514');

    $agent = new CoreAgent(collect());

    $method = new ReflectionMethod(CoreAgent::class, 'configureProvider');
    $method->setAccessible(true);
    $method->invoke($agent, 'unknown-agent');

    expect($agent->provider())->toBe(Lab::Gemini)
        ->and($agent->model())->toBe('gemini-2.5-flash');
});

it('uses global provider and model when no agent key is provided', function () {
    config()->set('laraclaw.ai.provider', 'openrouter');
    config()->set('laraclaw.ai.model', 'anthropic/claude-opus-4.1');

    $agent = new CoreAgent(collect());

    $method = new ReflectionMethod(CoreAgent::class, 'configureProvider');
    $method->setAccessible(true);
    $method->invoke($agent, null);

    expect($agent->provider())->toBe(Lab::OpenRouter)
        ->and($agent->model())->toBe('anthropic/claude-opus-4.1');
});
