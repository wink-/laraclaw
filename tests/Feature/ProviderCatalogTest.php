<?php

use App\Laraclaw\AI\ProviderCatalog;
use App\Models\User;
use Laravel\Ai\Enums\Lab;
use Livewire\Volt\Volt;

uses()->beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

/*
|--------------------------------------------------------------------------
| ProviderCatalog Read Tests
|--------------------------------------------------------------------------
*/

test('all returns empty array when no custom providers configured', function () {
    $catalog = new ProviderCatalog;

    expect($catalog->all())->toBe([]);
});

test('get returns null for nonexistent provider', function () {
    $catalog = new ProviderCatalog;

    expect($catalog->get('nonexistent'))->toBeNull();
});

test('has returns false for nonexistent provider', function () {
    $catalog = new ProviderCatalog;

    expect($catalog->has('nonexistent'))->toBeFalse();
});

test('getAvailableDrivers returns array with openai and anthropic', function () {
    $catalog = new ProviderCatalog;
    $drivers = $catalog->getAvailableDrivers();

    expect($drivers)->toBeArray();
    expect($drivers)->toHaveKeys(['openai', 'anthropic', 'gemini', 'ollama', 'groq']);
});

/*
|--------------------------------------------------------------------------
| ProviderCatalog CRUD Tests
|--------------------------------------------------------------------------
*/

test('addProvider creates provider that exists in catalog', function () {
    $catalog = new ProviderCatalog;

    $catalog->addProvider('test-provider', 'Test Provider', 'openai', 'TEST_API_KEY', 'https://api.test.com');

    expect($catalog->has('test-provider'))->toBeTrue();

    $provider = $catalog->get('test-provider');
    expect($provider)->toBeArray()
        ->and($provider['name'])->toBe('Test Provider')
        ->and($provider['driver'])->toBe('openai')
        ->and($provider['key_env'])->toBe('TEST_API_KEY')
        ->and($provider['url'])->toBe('https://api.test.com');
});

test('addProvider without url stores null url', function () {
    $catalog = new ProviderCatalog;

    $catalog->addProvider('no-url', 'No URL Provider', 'anthropic', 'NO_URL_KEY');

    $provider = $catalog->get('no-url');
    expect($provider['url'])->toBeNull();
});

test('updateProvider changes name and driver', function () {
    $catalog = new ProviderCatalog;
    $catalog->addProvider('update-test', 'Original', 'openai', 'ORIG_KEY');

    $catalog->updateProvider('update-test', 'Updated', 'anthropic', 'NEW_KEY', 'https://new.url');

    $provider = $catalog->get('update-test');
    expect($provider['name'])->toBe('Updated')
        ->and($provider['driver'])->toBe('anthropic')
        ->and($provider['key_env'])->toBe('NEW_KEY')
        ->and($provider['url'])->toBe('https://new.url');
});

test('removeProvider removes provider from catalog', function () {
    $catalog = new ProviderCatalog;
    $catalog->addProvider('remove-test', 'To Remove', 'openai', 'REMOVE_KEY');

    expect($catalog->has('remove-test'))->toBeTrue();

    $catalog->removeProvider('remove-test');

    expect($catalog->has('remove-test'))->toBeFalse();
});

test('getProviderKeys returns list of provider keys', function () {
    $catalog = new ProviderCatalog;
    $catalog->addProvider('provider-a', 'A', 'openai', 'KEY_A');
    $catalog->addProvider('provider-b', 'B', 'anthropic', 'KEY_B');

    $keys = $catalog->getProviderKeys();

    expect($keys)->toBe(['provider-a', 'provider-b']);
});

/*
|--------------------------------------------------------------------------
| Lab Enum Resolution Tests
|--------------------------------------------------------------------------
*/

test('resolveLabForProvider returns correct Lab for known driver', function () {
    $catalog = new ProviderCatalog;
    $catalog->addProvider('test-openai', 'Test', 'openai', 'TEST_KEY');

    $lab = $catalog->resolveLabForProvider('test-openai');

    expect($lab)->toBe(Lab::OpenAI);
});

test('resolveLabForProvider returns null for nonexistent provider', function () {
    $catalog = new ProviderCatalog;

    expect($catalog->resolveLabForProvider('nonexistent'))->toBeNull();
});

test('resolveLabFromDriver maps all common drivers', function () {
    $catalog = new ProviderCatalog;

    expect($catalog->resolveLabFromDriver('openai'))->toBe(Lab::OpenAI)
        ->and($catalog->resolveLabFromDriver('anthropic'))->toBe(Lab::Anthropic)
        ->and($catalog->resolveLabFromDriver('gemini'))->toBe(Lab::Gemini)
        ->and($catalog->resolveLabFromDriver('groq'))->toBe(Lab::Groq)
        ->and($catalog->resolveLabFromDriver('deepseek'))->toBe(Lab::DeepSeek);
});

test('getDriverForProvider returns driver string', function () {
    $catalog = new ProviderCatalog;
    $catalog->addProvider('driver-test', 'Test', 'mistral', 'DRIVER_KEY');

    expect($catalog->getDriverForProvider('driver-test'))->toBe('mistral');
});

test('getDriverForProvider returns null for nonexistent provider', function () {
    $catalog = new ProviderCatalog;

    expect($catalog->getDriverForProvider('nonexistent'))->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Database Persistence Tests
|--------------------------------------------------------------------------
*/

test('addProvider persists to database and is readable by fresh ProviderCatalog', function () {
    $catalog = new ProviderCatalog;
    $catalog->addProvider('persisted', 'Persisted Provider', 'openai', 'PERSISTED_KEY', 'https://persisted.com');

    $fresh = new ProviderCatalog;
    expect($fresh->has('persisted'))->toBeTrue();

    $provider = $fresh->get('persisted');
    expect($provider['name'])->toBe('Persisted Provider')
        ->and($provider['driver'])->toBe('openai')
        ->and($provider['key_env'])->toBe('PERSISTED_KEY')
        ->and($provider['url'])->toBe('https://persisted.com');
});

test('removeProvider persists removal to database', function () {
    $catalog = new ProviderCatalog;
    $catalog->addProvider('temp-provider', 'Temp', 'openai', 'TEMP_KEY');
    $catalog->removeProvider('temp-provider');

    $fresh = new ProviderCatalog;
    expect($fresh->has('temp-provider'))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Provider Registration Tests
|--------------------------------------------------------------------------
*/

test('registerProvider sets config for ai providers', function () {
    $catalog = new ProviderCatalog;
    $catalog->addProvider('reg-test', 'Registration Test', 'openai', 'REG_TEST_KEY', 'https://reg-test.com');

    $catalog->registerProvider('reg-test');

    $aiConfig = config('ai.providers.reg-test');
    expect($aiConfig)->toBeArray()
        ->and($aiConfig['driver'])->toBe('openai')
        ->and($aiConfig)->toHaveKey('url')
        ->and($aiConfig['url'])->toBe('https://reg-test.com');
});

test('registerProviders registers all custom providers', function () {
    $catalog = new ProviderCatalog;
    $catalog->addProvider('multi-a', 'A', 'openai', 'KEY_A');
    $catalog->addProvider('multi-b', 'B', 'anthropic', 'KEY_B');

    $catalog->registerProviders();

    expect(config('ai.providers.multi-a'))->toBeArray()
        ->and(config('ai.providers.multi-b'))->toBeArray();
});

/*
|--------------------------------------------------------------------------
| Volt Providers Page Tests
|--------------------------------------------------------------------------
*/

test('Volt providers page renders successfully', function () {
    Volt::test('laraclaw.providers')
        ->assertSuccessful()
        ->assertSee('Providers');
});

test('Volt providers page shows empty state when no providers configured', function () {
    Volt::test('laraclaw.providers')
        ->assertSee('No custom providers');
});
