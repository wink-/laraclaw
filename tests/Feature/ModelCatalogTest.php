<?php

use App\Laraclaw\AI\ModelCatalog;

test('all returns non-empty array with expected provider keys', function () {
    $catalog = new ModelCatalog;
    $all = $catalog->all();

    expect($all)->not()->toBeEmpty();
    expect($all)->toHaveKeys(['openai', 'anthropic', 'openrouter']);
    expect($all['openai'])->toBeArray();
    expect($all['openai'])->not()->toBeEmpty();
});

test('getProviders returns array containing openai, anthropic, and openrouter', function () {
    $catalog = new ModelCatalog;
    $providers = $catalog->getProviders();

    expect($providers)->toBeArray();
    expect($providers)->toContain('openai');
    expect($providers)->toContain('anthropic');
    expect($providers)->toContain('openrouter');
});

test('getModels returns models including gpt-4o for openai provider', function () {
    $catalog = new ModelCatalog;
    $models = $catalog->getModels('openai');

    expect($models)->toBeArray();
    expect($models)->toHaveKey('gpt-4o');
    expect($models['gpt-4o'])->toHaveKeys(['name', 'context']);
});

test('getModels returns empty array for nonexistent provider', function () {
    $catalog = new ModelCatalog;

    expect($catalog->getModels('nonexistent'))->toBe([]);
});

test('getOptionsForProvider returns model id and label pairs with context info', function () {
    $catalog = new ModelCatalog;
    $options = $catalog->getOptionsForProvider('openai');

    expect($options)->toBeArray();
    expect($options)->toHaveKey('gpt-4o');

    // The label should contain context info like "128K"
    expect($options['gpt-4o'])->toContain('128K');
    expect($options['gpt-4o'])->toContain('GPT-4o');

    // Models with >= 1M context should show "M"
    expect($options['gpt-4.1'])->toContain('1M');
});

test('hasModel returns true for existing model and false for nonexistent', function () {
    $catalog = new ModelCatalog;

    expect($catalog->hasModel('openai', 'gpt-4o'))->toBeTrue();
    expect($catalog->hasModel('openai', 'nonexistent'))->toBeFalse();
});

test('getModelInfo returns array with name and context keys', function () {
    $catalog = new ModelCatalog;
    $info = $catalog->getModelInfo('openai', 'gpt-4o');

    expect($info)->toBeArray();
    expect($info)->toHaveKey('name');
    expect($info)->toHaveKey('context');
    expect($info['name'])->toBe('GPT-4o');
    expect($info['context'])->toBe(128_000);
});

test('getModelInfo returns null for nonexistent model', function () {
    $catalog = new ModelCatalog;

    expect($catalog->getModelInfo('openai', 'nonexistent'))->toBeNull();
});

test('search returns results containing anthropic provider when searching for claude', function () {
    $catalog = new ModelCatalog;
    $results = $catalog->search('claude');

    expect($results)->toBeArray();
    expect($results)->toHaveKey('anthropic');

    // Also verify results include model data
    expect($results['anthropic'])->not()->toBeEmpty();
    expect($results['anthropic'][0])->toHaveKey('id');
});

test('search returns empty array for nonexistent query', function () {
    $catalog = new ModelCatalog;

    expect($catalog->search('nonexistent'))->toBe([]);
});
