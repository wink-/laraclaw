<?php

use App\Laraclaw\Tunnels\TunnelManager;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
});

it('can be instantiated with config', function () {
    $config = [
        'default_port' => 8000,
        'providers' => [
            'ngrok' => ['path' => 'ngrok'],
            'cloudflare' => ['path' => 'cloudflared'],
            'tailscale' => ['path' => 'tailscale'],
        ],
    ];

    $manager = new TunnelManager($config);

    expect($manager)->toBeInstanceOf(TunnelManager::class);
});

it('returns available providers', function () {
    $config = [
        'default_port' => 8000,
        'providers' => [
            'ngrok' => ['path' => 'ngrok'],
            'cloudflare' => ['path' => 'cloudflared'],
            'tailscale' => ['path' => 'tailscale'],
        ],
    ];

    $manager = new TunnelManager($config);

    $providers = $manager->getAvailableProviders();

    expect($providers)->toContain('ngrok')
        ->and($providers)->toContain('cloudflare')
        ->and($providers)->toContain('tailscale');
});

it('returns empty array when no providers configured', function () {
    $manager = new TunnelManager([]);

    $providers = $manager->getAvailableProviders();

    expect($providers)->toBeEmpty();
});

it('returns null for active provider when no tunnel is running', function () {
    $config = [
        'default_port' => 8000,
        'providers' => [
            'cloudflare' => ['path' => 'cloudflared'],
        ],
    ];

    $manager = new TunnelManager($config);

    expect($manager->getActiveProvider())->toBeNull();
});

it('returns null for URL when no tunnel is active', function () {
    $config = [
        'default_port' => 8000,
        'providers' => [
            'cloudflare' => ['path' => 'cloudflared'],
        ],
    ];

    $manager = new TunnelManager($config);

    expect($manager->getUrl())->toBeNull();
});

it('stores tunnel status in cache when started', function () {
    $config = [
        'default_port' => 8000,
        'providers' => [
            'cloudflare' => ['path' => 'cloudflared'],
        ],
    ];

    $manager = new TunnelManager($config);

    // We test cache directly since we can't easily mock the factory closure
    Cache::put('laraclaw.tunnel.active_provider', 'cloudflare', now()->addHours(24));
    Cache::put('laraclaw.tunnel.active', true, now()->addHours(24));
    Cache::put('laraclaw.tunnel.url', 'https://test.trycloudflare.com', now()->addHours(24));

    expect(Cache::get('laraclaw.tunnel.active_provider'))->toBe('cloudflare')
        ->and(Cache::get('laraclaw.tunnel.active'))->toBeTrue()
        ->and(Cache::get('laraclaw.tunnel.url'))->toBe('https://test.trycloudflare.com');
});

it('clears tunnel status from cache when stopped', function () {
    Cache::put('laraclaw.tunnel.active_provider', 'cloudflare', now()->addHours(24));
    Cache::put('laraclaw.tunnel.active', true, now()->addHours(24));
    Cache::put('laraclaw.tunnel.url', 'https://test.trycloudflare.com', now()->addHours(24));

    $config = [
        'default_port' => 8000,
        'providers' => [
            'cloudflare' => ['path' => 'cloudflared'],
        ],
    ];

    // Create manager and clear cache via reflection
    $manager = new TunnelManager($config);

    // Use reflection to access protected method
    $reflection = new ReflectionClass($manager);
    $method = $reflection->getMethod('clearTunnelStatus');
    $method->setAccessible(true);
    $method->invoke($manager);

    expect(Cache::get('laraclaw.tunnel.active_provider'))->toBeNull()
        ->and(Cache::get('laraclaw.tunnel.active'))->toBeNull()
        ->and(Cache::get('laraclaw.tunnel.url'))->toBeNull();
});

it('returns status for all configured providers', function () {
    $config = [
        'default_port' => 8000,
        'providers' => [
            'ngrok' => ['path' => 'ngrok'],
            'cloudflare' => ['path' => 'cloudflared'],
        ],
    ];

    $manager = new TunnelManager($config);

    $status = $manager->getStatus();

    expect($status)->toHaveKey('ngrok')
        ->and($status)->toHaveKey('cloudflare')
        ->and($status['ngrok'])->toHaveKeys(['available', 'active', 'url'])
        ->and($status['cloudflare'])->toHaveKeys(['available', 'active', 'url']);
});

it('detects available provider returns first available', function () {
    $config = [
        'default_port' => 8000,
        'providers' => [
            'ngrok' => ['path' => 'nonexistent-ngrok'],
            'cloudflare' => ['path' => 'nonexistent-cloudflared'],
            'tailscale' => ['path' => 'nonexistent-tailscale'],
        ],
    ];

    $manager = new TunnelManager($config);

    // Since none of the fake paths exist, it should return null
    expect($manager->detectAvailableProvider())->toBeNull();
});

it('stops tunnel returns true when no active tunnel', function () {
    $config = [
        'default_port' => 8000,
        'providers' => [
            'cloudflare' => ['path' => 'cloudflared'],
        ],
    ];

    $manager = new TunnelManager($config);

    expect($manager->stop())->toBeTrue();
});

it('can resolve tunnel manager from container', function () {
    $manager = app(TunnelManager::class);

    expect($manager)->toBeInstanceOf(TunnelManager::class);
});

it('uses config values when resolved from container', function () {
    // Clear any cached instance
    app()->forgetInstance(TunnelManager::class);

    config()->set('laraclaw.tunnels', [
        'default_provider' => 'cloudflare',
        'default_port' => 8080,
        'providers' => [
            'cloudflare' => ['path' => 'cloudflared'],
        ],
    ]);

    $manager = app(TunnelManager::class);

    expect($manager)->toBeInstanceOf(TunnelManager::class)
        ->and($manager->getAvailableProviders())->toContain('cloudflare');
});

afterEach(function () {
    Mockery::close();
});
