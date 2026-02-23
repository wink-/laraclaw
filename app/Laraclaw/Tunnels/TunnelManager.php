<?php

namespace App\Laraclaw\Tunnels;

use App\Laraclaw\Tunnels\Contracts\TunnelServiceInterface;
use Illuminate\Support\Facades\Cache;

class TunnelManager
{
    protected array $providers = [];

    protected ?string $activeProvider = null;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config = [])
    {
        $this->registerProviders($config);
    }

    /**
     * Register tunnel providers from configuration.
     *
     * @param  array<string, mixed>  $config
     */
    protected function registerProviders(array $config): void
    {
        // Register ngrok
        if (isset($config['providers']['ngrok'])) {
            $this->providers['ngrok'] = fn () => new NgrokService(
                ngrokPath: $config['providers']['ngrok']['path'] ?? 'ngrok',
                authToken: $config['providers']['ngrok']['auth_token'] ?? null,
                port: $config['default_port'] ?? 8000,
                region: $config['providers']['ngrok']['region'] ?? 'us',
            );
        }

        // Register cloudflare
        if (isset($config['providers']['cloudflare'])) {
            $this->providers['cloudflare'] = fn () => new CloudflareTunnelService(
                cloudflaredPath: $config['providers']['cloudflare']['path'] ?? 'cloudflared',
                port: $config['default_port'] ?? 8000,
            );
        }

        // Register tailscale
        if (isset($config['providers']['tailscale'])) {
            $this->providers['tailscale'] = fn () => new TailscaleService(
                tailscalePath: $config['providers']['tailscale']['path'] ?? 'tailscale',
                port: $config['default_port'] ?? 8000,
            );
        }
    }

    /**
     * Start a tunnel with the specified or default provider.
     *
     * @param  array<string, mixed>  $options
     */
    public function start(?string $provider = null, array $options = []): bool
    {
        $provider = $provider ?? $this->detectAvailableProvider();

        if (! $provider) {
            return false;
        }

        $service = $this->getProvider($provider);

        if (! $service) {
            return false;
        }

        $success = $service->start($options);

        if ($success) {
            $this->activeProvider = $provider;
            $this->storeTunnelStatus($provider, true, $service->getUrl());
        }

        return $success;
    }

    /**
     * Stop the currently active tunnel.
     */
    public function stop(): bool
    {
        $activeProvider = $this->getActiveProvider();

        if (! $activeProvider) {
            return true;
        }

        $service = $this->getProvider($activeProvider);

        if (! $service) {
            return false;
        }

        $success = $service->stop();
        $this->clearTunnelStatus();
        $this->activeProvider = null;

        return $success;
    }

    /**
     * Get the status of all tunnel providers.
     *
     * @return array<string, array{available: bool, active: bool, url: ?string}>
     */
    public function getStatus(): array
    {
        $status = [];

        foreach ($this->providers as $name => $factory) {
            $service = $factory();

            $status[$name] = [
                'available' => $this->isProviderAvailable($name),
                'active' => $service->isActive(),
                'url' => $service->getUrl(),
            ];
        }

        return $status;
    }

    /**
     * Get the public URL of the active tunnel.
     */
    public function getUrl(): ?string
    {
        $activeProvider = $this->getActiveProvider();

        if (! $activeProvider) {
            return null;
        }

        $service = $this->getProvider($activeProvider);

        return $service?->getUrl();
    }

    /**
     * Get the name of the active tunnel provider.
     */
    public function getActiveProvider(): ?string
    {
        // Check cache for active tunnel
        $cachedProvider = Cache::get('laraclaw.tunnel.active_provider');

        if ($cachedProvider) {
            $service = $this->getProvider($cachedProvider);

            if ($service && $service->isActive()) {
                return $cachedProvider;
            }

            // Cache is stale, clear it
            $this->clearTunnelStatus();
        }

        // Check each provider if any is active
        foreach ($this->providers as $name => $factory) {
            $service = $factory();

            if ($service->isActive()) {
                $this->activeProvider = $name;
                $this->storeTunnelStatus($name, true, $service->getUrl());

                return $name;
            }
        }

        return null;
    }

    /**
     * Detect which tunnel provider is available.
     */
    public function detectAvailableProvider(): ?string
    {
        $defaultProvider = config('laraclaw.tunnels.default_provider', 'cloudflare');

        // Check if default provider is available
        if ($this->isProviderAvailable($defaultProvider)) {
            return $defaultProvider;
        }

        // Check other providers
        $preferredOrder = ['cloudflare', 'ngrok', 'tailscale'];

        foreach ($preferredOrder as $provider) {
            if ($this->isProviderAvailable($provider)) {
                return $provider;
            }
        }

        return null;
    }

    /**
     * Check if a specific provider is available.
     */
    public function isProviderAvailable(string $provider): bool
    {
        $service = $this->getProvider($provider);

        if (! $service) {
            return false;
        }

        return match ($service::class) {
            NgrokService::class => $service->isNgrokAvailable(),
            CloudflareTunnelService::class => $service->isCloudflaredAvailable(),
            TailscaleService::class => $service->isTailscaleAvailable() && $service->isTailscaleConnected(),
            default => false,
        };
    }

    /**
     * Get all registered provider names.
     *
     * @return array<int, string>
     */
    public function getAvailableProviders(): array
    {
        return array_keys($this->providers);
    }

    /**
     * Get a tunnel service instance by name.
     */
    protected function getProvider(string $name): ?TunnelServiceInterface
    {
        if (! isset($this->providers[$name])) {
            return null;
        }

        return $this->providers[$name]();
    }

    /**
     * Store tunnel status in cache.
     */
    protected function storeTunnelStatus(string $provider, bool $active, ?string $url): void
    {
        Cache::put('laraclaw.tunnel.active_provider', $provider, now()->addHours(24));
        Cache::put('laraclaw.tunnel.active', $active, now()->addHours(24));
        Cache::put('laraclaw.tunnel.url', $url, now()->addHours(24));
    }

    /**
     * Clear tunnel status from cache.
     */
    protected function clearTunnelStatus(): void
    {
        Cache::forget('laraclaw.tunnel.active_provider');
        Cache::forget('laraclaw.tunnel.active');
        Cache::forget('laraclaw.tunnel.url');
    }
}
