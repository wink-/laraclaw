<?php

namespace App\Laraclaw\Tunnels;

use App\Laraclaw\Tunnels\Contracts\TunnelServiceInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Sleep;

class CloudflareTunnelService implements TunnelServiceInterface
{
    protected ?string $url = null;

    public function __construct(
        protected string $cloudflaredPath = 'cloudflared',
        protected int $port = 8000,
    ) {}

    /**
     * Start the Cloudflare quick tunnel.
     *
     * @param  array<string, mixed>  $options
     */
    public function start(array $options = []): bool
    {
        $port = $options['port'] ?? $this->port;

        if ($this->isActive()) {
            return true;
        }

        // Check if cloudflared is available
        if (! $this->isCloudflaredAvailable()) {
            return false;
        }

        // Build the cloudflared command for quick tunnel
        $command = sprintf(
            '%s tunnel --url http://localhost:%d 2>&1',
            escapeshellcmd($this->cloudflaredPath),
            $port
        );

        // Start cloudflared in background and capture output
        $process = Process::start($command);

        // Wait for cloudflared to start and output the URL
        $output = '';
        $maxAttempts = 30;

        for ($i = 0; $i < $maxAttempts; $i++) {
            Sleep::for(500)->milliseconds();

            $output .= $process->latestOutput();

            // Parse the output to find the tunnel URL
            if (preg_match('#https://[a-zA-Z0-9-]+\.trycloudflare\.com#', $output, $matches)) {
                $this->url = $matches[0];

                // Store URL in cache for status checks
                Cache::put('laraclaw.tunnel.cloudflare.url', $this->url, now()->addHours(24));
                Cache::put('laraclaw.tunnel.cloudflare.active', true, now()->addHours(24));

                return true;
            }
        }

        return false;
    }

    /**
     * Stop the Cloudflare tunnel.
     */
    public function stop(): bool
    {
        // Kill cloudflared processes
        Process::run('pkill -f cloudflared 2>/dev/null || true');

        $this->url = null;
        Cache::forget('laraclaw.tunnel.cloudflare.url');
        Cache::forget('laraclaw.tunnel.cloudflare.active');

        return true;
    }

    /**
     * Check if the Cloudflare tunnel is currently active.
     */
    public function isActive(): bool
    {
        // Check if we have a cached URL and the process is running
        $cachedUrl = Cache::get('laraclaw.tunnel.cloudflare.url');

        if (! $cachedUrl) {
            return false;
        }

        // Check if cloudflared process is running
        $result = Process::run('pgrep -f cloudflared 2>/dev/null');

        if (! $result->successful() || empty(trim($result->output()))) {
            Cache::forget('laraclaw.tunnel.cloudflare.url');
            Cache::forget('laraclaw.tunnel.cloudflare.active');

            return false;
        }

        $this->url = $cachedUrl;

        return true;
    }

    /**
     * Get the public URL of the Cloudflare tunnel.
     */
    public function getUrl(): ?string
    {
        if ($this->url) {
            return $this->url;
        }

        return Cache::get('laraclaw.tunnel.cloudflare.url');
    }

    /**
     * Get the name of the tunnel provider.
     */
    public function getName(): string
    {
        return 'cloudflare';
    }

    /**
     * Check if cloudflared binary is available.
     */
    public function isCloudflaredAvailable(): bool
    {
        $result = Process::run(sprintf('%s --version 2>/dev/null', escapeshellcmd($this->cloudflaredPath)));

        return $result->successful();
    }
}
