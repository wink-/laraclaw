<?php

namespace App\Laraclaw\Tunnels;

use App\Laraclaw\Tunnels\Contracts\TunnelServiceInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Sleep;

class CloudflareTunnelService implements TunnelServiceInterface
{
    protected const CACHE_PREFIX = 'laraclaw.tunnel.cloudflare';

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

        $this->ensureLogDirectoryExists();

        File::put($this->getLogPath(), '');

        $command = sprintf(
            "sh -c 'nohup %s tunnel --url http://127.0.0.1:%d > %s 2>&1 & echo $!'",
            escapeshellarg($this->cloudflaredPath),
            $port,
            escapeshellarg($this->getLogPath())
        );

        $result = Process::run($command);

        if (! $result->successful()) {
            return false;
        }

        $pid = trim($result->output());

        if ($pid === '') {
            return false;
        }

        Cache::put(self::CACHE_PREFIX.'.pid', $pid, now()->addHours(24));

        $output = '';
        $maxAttempts = 30;

        for ($i = 0; $i < $maxAttempts; $i++) {
            Sleep::for(500)->milliseconds();

            if (File::exists($this->getLogPath())) {
                $output = File::get($this->getLogPath());
            }

            if (preg_match('#https://[a-zA-Z0-9-]+\.trycloudflare\.com#', $output, $matches)) {
                $this->url = $matches[0];

                Cache::put(self::CACHE_PREFIX.'.url', $this->url, now()->addHours(24));
                Cache::put(self::CACHE_PREFIX.'.active', true, now()->addHours(24));

                return true;
            }
        }

        $this->stop();

        return false;
    }

    /**
     * Stop the Cloudflare tunnel.
     */
    public function stop(): bool
    {
        $pid = Cache::get(self::CACHE_PREFIX.'.pid');

        if ($pid) {
            Process::run(sprintf("sh -c 'kill %s >/dev/null 2>&1 || true'", escapeshellarg((string) $pid)));
        } else {
            Process::run("sh -c 'pkill -f cloudflared >/dev/null 2>&1 || true'");
        }

        $this->url = null;
        Cache::forget(self::CACHE_PREFIX.'.url');
        Cache::forget(self::CACHE_PREFIX.'.active');
        Cache::forget(self::CACHE_PREFIX.'.pid');

        return true;
    }

    /**
     * Check if the Cloudflare tunnel is currently active.
     */
    public function isActive(): bool
    {
        $cachedUrl = Cache::get(self::CACHE_PREFIX.'.url');
        $cachedPid = Cache::get(self::CACHE_PREFIX.'.pid');

        if (! $cachedUrl) {
            return false;
        }

        $result = $cachedPid
            ? Process::run(sprintf("sh -c 'kill -0 %s >/dev/null 2>&1'", escapeshellarg((string) $cachedPid)))
            : Process::run("sh -c 'pgrep -f cloudflared >/dev/null 2>&1'");

        if (! $result->successful()) {
            Cache::forget(self::CACHE_PREFIX.'.url');
            Cache::forget(self::CACHE_PREFIX.'.active');
            Cache::forget(self::CACHE_PREFIX.'.pid');

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

        return Cache::get(self::CACHE_PREFIX.'.url');
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

    protected function getLogPath(): string
    {
        return storage_path('logs/cloudflared.log');
    }

    protected function ensureLogDirectoryExists(): void
    {
        File::ensureDirectoryExists(dirname($this->getLogPath()));
    }
}
