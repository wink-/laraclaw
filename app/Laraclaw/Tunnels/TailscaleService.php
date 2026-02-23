<?php

namespace App\Laraclaw\Tunnels;

use App\Laraclaw\Tunnels\Contracts\TunnelServiceInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Sleep;

class TailscaleService implements TunnelServiceInterface
{
    protected ?string $url = null;

    public function __construct(
        protected string $tailscalePath = 'tailscale',
        protected int $port = 8000,
    ) {}

    /**
     * Start the Tailscale funnel.
     *
     * @param  array<string, mixed>  $options
     */
    public function start(array $options = []): bool
    {
        $port = $options['port'] ?? $this->port;

        if ($this->isActive()) {
            return true;
        }

        // Check if Tailscale is available and connected
        if (! $this->isTailscaleAvailable()) {
            return false;
        }

        if (! $this->isTailscaleConnected()) {
            return false;
        }

        // Start the funnel using `tailscale fun`
        $command = sprintf(
            '%s fun --yes %d 2>&1',
            escapeshellcmd($this->tailscalePath),
            $port
        );

        $process = Process::start($command);

        // Wait for funnel to start
        Sleep::for(2)->seconds();

        // Get the funnel URL
        $url = $this->fetchFunnelUrl();

        if ($url) {
            $this->url = $url;
            Cache::put('laraclaw.tunnel.tailscale.url', $this->url, now()->addHours(24));
            Cache::put('laraclaw.tunnel.tailscale.active', true, now()->addHours(24));

            return true;
        }

        return false;
    }

    /**
     * Stop the Tailscale funnel.
     */
    public function stop(): bool
    {
        Process::run(sprintf('%s fun --off 2>/dev/null || true', escapeshellcmd($this->tailscalePath)));

        $this->url = null;
        Cache::forget('laraclaw.tunnel.tailscale.url');
        Cache::forget('laraclaw.tunnel.tailscale.active');

        return true;
    }

    /**
     * Check if the Tailscale funnel is currently active.
     */
    public function isActive(): bool
    {
        $cachedUrl = Cache::get('laraclaw.tunnel.tailscale.url');

        // Check if funnel is actually running
        $result = Process::run(sprintf('%s fun --status 2>/dev/null', escapeshellcmd($this->tailscalePath)));

        if (! $result->successful() || ! str_contains($result->output(), 'running')) {
            Cache::forget('laraclaw.tunnel.tailscale.url');
            Cache::forget('laraclaw.tunnel.tailscale.active');

            return false;
        }

        if ($cachedUrl) {
            $this->url = $cachedUrl;

            return true;
        }

        return false;
    }

    /**
     * Get the public URL of the Tailscale funnel.
     */
    public function getUrl(): ?string
    {
        if ($this->url) {
            return $this->url;
        }

        return Cache::get('laraclaw.tunnel.tailscale.url') ?? $this->fetchFunnelUrl();
    }

    /**
     * Get the name of the tunnel provider.
     */
    public function getName(): string
    {
        return 'tailscale';
    }

    /**
     * Check if Tailscale binary is available.
     */
    public function isTailscaleAvailable(): bool
    {
        $result = Process::run(sprintf('%s version 2>/dev/null', escapeshellcmd($this->tailscalePath)));

        return $result->successful();
    }

    /**
     * Check if Tailscale is connected.
     */
    public function isTailscaleConnected(): bool
    {
        $result = Process::run(sprintf('%s status --json 2>/dev/null', escapeshellcmd($this->tailscalePath)));

        if (! $result->successful()) {
            return false;
        }

        $data = json_decode($result->output(), true);

        return ! empty($data['BackendState']) && $data['BackendState'] === 'Running';
    }

    /**
     * Fetch the funnel URL from Tailscale status.
     */
    protected function fetchFunnelUrl(): ?string
    {
        $result = Process::run(sprintf('%s fun --status 2>/dev/null', escapeshellcmd($this->tailscalePath)));

        if (! $result->successful()) {
            return null;
        }

        $output = $result->output();

        // Try to extract URL from funnel status output
        if (preg_match('#https://[a-zA-Z0-9.-]+\.ts\.net#', $output, $matches)) {
            return $matches[0];
        }

        // Alternative: try to get from tailscale status
        $statusResult = Process::run(sprintf('%s status --json 2>/dev/null', escapeshellcmd($this->tailscalePath)));

        if ($statusResult->successful()) {
            $data = json_decode($statusResult->output(), true);

            if (! empty($data['Self']['DNSName'])) {
                return 'https://'.rtrim($data['Self']['DNSName'], '.');
            }
        }

        return null;
    }
}
