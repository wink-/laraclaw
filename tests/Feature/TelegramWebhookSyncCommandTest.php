<?php

use App\Laraclaw\Gateways\TelegramGateway;
use App\Laraclaw\Tunnels\TunnelManager;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses()->beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('telegram webhook sync uses explicit url when provided', function () {
    config(['services.telegram.bot_token' => 'test-token']);

    $tunnelManager = Mockery::mock(TunnelManager::class);
    $telegram = Mockery::mock(TelegramGateway::class);

    $telegram->shouldReceive('setWebhook')
        ->once()
        ->with('https://example.test/laraclaw/webhooks/telegram')
        ->andReturnTrue();
    $telegram->shouldReceive('getWebhookInfo')
        ->once()
        ->andReturn(['pending_update_count' => 0]);

    app()->instance(TunnelManager::class, $tunnelManager);
    app()->instance(TelegramGateway::class, $telegram);

    $this->artisan('laraclaw:telegram:webhook-sync', ['--url' => 'https://example.test'])
        ->expectsOutput('Telegram webhook synced successfully.')
        ->expectsOutput('Webhook URL: https://example.test/laraclaw/webhooks/telegram')
        ->assertSuccessful();
});

test('telegram webhook sync starts tunnel when no active url exists', function () {
    config(['services.telegram.bot_token' => 'test-token']);

    $tunnelManager = Mockery::mock(TunnelManager::class);
    $telegram = Mockery::mock(TelegramGateway::class);

    $tunnelManager->shouldReceive('getUrl')
        ->once()
        ->andReturnNull();
    $tunnelManager->shouldReceive('start')
        ->once()
        ->with('cloudflare', ['port' => 8000])
        ->andReturnTrue();
    $tunnelManager->shouldReceive('getUrl')
        ->once()
        ->andReturn('https://active-tunnel.test');

    $telegram->shouldReceive('setWebhook')
        ->once()
        ->with('https://active-tunnel.test/laraclaw/webhooks/telegram')
        ->andReturnTrue();
    $telegram->shouldReceive('getWebhookInfo')
        ->once()
        ->andReturn(['pending_update_count' => 2]);

    app()->instance(TunnelManager::class, $tunnelManager);
    app()->instance(TelegramGateway::class, $telegram);

    $this->artisan('laraclaw:telegram:webhook-sync')
        ->expectsOutput('Telegram webhook synced successfully.')
        ->expectsOutput('Webhook URL: https://active-tunnel.test/laraclaw/webhooks/telegram')
        ->expectsOutput('Pending updates: 2')
        ->assertSuccessful();
});

test('telegram gateway sends secret token when setting webhook', function () {
    config([
        'services.telegram.bot_token' => 'test-token',
        'services.telegram.secret_token' => 'secret-123',
    ]);

    Http::fake([
        'https://api.telegram.org/*' => Http::response(['ok' => true], 200),
    ]);

    $gateway = app(TelegramGateway::class);

    expect($gateway->setWebhook('https://example.test/laraclaw/webhooks/telegram'))->toBeTrue();

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://api.telegram.org/bottest-token/setWebhook'
            && $request['url'] === 'https://example.test/laraclaw/webhooks/telegram'
            && $request['secret_token'] === 'secret-123';
    });
});

afterEach(function () {
    Mockery::close();
});
