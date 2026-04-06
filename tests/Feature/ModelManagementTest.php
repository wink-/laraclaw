<?php

use App\Laraclaw\AI\ModelCatalog;
use App\Models\User;
use Livewire\Volt\Volt;

uses()->beforeEach(function () {
    $this->configPath = config_path('laraclaw-models.php');
    $this->originalConfig = file_get_contents($this->configPath);

    $this->actingAs(User::factory()->create());
})->afterEach(function () {
    file_put_contents($this->configPath, $this->originalConfig);
});

/*
|--------------------------------------------------------------------------
| ModelCatalog CRUD Tests
|--------------------------------------------------------------------------
*/

test('addModel adds a model and hasModel confirms it exists', function () {
    $catalog = new ModelCatalog;

    $catalog->addModel('openai', 'test-model', 'Test Model', 32000);

    expect($catalog->hasModel('openai', 'test-model'))->toBeTrue();

    $info = $catalog->getModelInfo('openai', 'test-model');
    expect($info)->toBeArray()
        ->and($info['name'])->toBe('Test Model')
        ->and($info['context'])->toBe(32000);
});

test('addModel creates new provider that appears in getProviders', function () {
    $catalog = new ModelCatalog;

    $catalog->addModel('new-provider', 'test-id', 'Test', 8000);

    expect($catalog->getProviders())->toContain('new-provider')
        ->and($catalog->hasModel('new-provider', 'test-id'))->toBeTrue();
});

test('updateModel changes name and context of existing model', function () {
    $catalog = new ModelCatalog;
    $catalog->addModel('openai', 'test-model', 'Original Name', 16000);

    $catalog->updateModel('openai', 'test-model', 'Updated Name', 64000);

    $info = $catalog->getModelInfo('openai', 'test-model');
    expect($info['name'])->toBe('Updated Name')
        ->and($info['context'])->toBe(64000);
});

test('removeModel removes model from getModels', function () {
    $catalog = new ModelCatalog;
    $catalog->addModel('openai', 'test-model', 'Test Model', 32000);

    $catalog->removeModel('openai', 'test-model');

    expect($catalog->hasModel('openai', 'test-model'))->toBeFalse()
        ->and($catalog->getModels('openai'))->not->toHaveKey('test-model');
});

test('removeModel on last model leaves provider key with empty models', function () {
    $catalog = new ModelCatalog;
    $catalog->addModel('solo-provider', 'only-model', 'Only One', 4000);

    $catalog->removeModel('solo-provider', 'only-model');

    expect($catalog->getModels('solo-provider'))->toBe([])
        ->and($catalog->getProviders())->toContain('solo-provider');
});

/*
|--------------------------------------------------------------------------
| Config File Persistence Tests
|--------------------------------------------------------------------------
*/

test('addModel persists to config file and is readable by fresh ModelCatalog', function () {
    $catalog = new ModelCatalog;
    $catalog->addModel('openai', 'persisted-model', 'Persisted Model', 50000);

    // Reload the config from disk to simulate a fresh request
    $reloaded = require $this->configPath;
    app('config')->set('laraclaw-models', $reloaded);

    $fresh = new ModelCatalog;
    expect($fresh->hasModel('openai', 'persisted-model'))->toBeTrue();

    $info = $fresh->getModelInfo('openai', 'persisted-model');
    expect($info['name'])->toBe('Persisted Model')
        ->and($info['context'])->toBe(50000);
});

/*
|--------------------------------------------------------------------------
| Volt Models Page Tests
|--------------------------------------------------------------------------
*/

test('Volt models page renders successfully and shows Model Catalog', function () {
    Volt::test('laraclaw.models')
        ->assertSuccessful()
        ->assertSee('Model Catalog');
});

test('Volt models page shows providers from catalog', function () {
    Volt::test('laraclaw.models')
        ->assertSee('Openai');
});
