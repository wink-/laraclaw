<?php

namespace App\Laraclaw\Tunnels;

use App\Laraclaw\Tunnels\Contracts\TunnelServiceInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Sleep;

class NgrokService implements TunnelServiceInterface
{
    protected ?string $url = null;

    protected ?int $processId = null;

    public function __construct(
        protected string $ngrokPath = 'ngrok',
        protected ?string $authToken = null,
        protected int $port = 8000,
        protected string $region = 'us',
    ) {}

    /**
     * Start the ngrok tunnel.
     *
     * @param  array<string, mixed>  $options
     */
    public function start(array $options = []): bool
    {
        $port = $options['port'] ?? $this->port;

        if ($this->isActive()) {
            return true;
        }

        // Check if ngrok is available
        if (! $this->isNgrokAvailable()) {
            return false;
        }

        // Build the ngrok command
        $command = sprintf(
            '%s http %d --region=%s --log=stdout',
            escapeshellcmd($this->ngrokPath),
            $port,
            $this->region
        );

        // Add auth token if provided
        if ($this->authToken) {
            $command .= sprintf(' --authtoken=%s', escapeshellarg($this->authToken));
        }

        // Start ngrok in background
        $process = Process::start($command);

        // Wait for ngrok to start
        Sleep::for(3)->seconds();

        // Get the public URL from ngrok API
        $url = $this->fetchUrlFromApi();

        if ($url) {
            $this->url = $url;

            return true;
        }

        return false;
    }

    /**
     * Stop the ngrok tunnel.
     */
    public function stop(): bool
    {
        try {
            // Kill ngrok process via API
            $response = Http::timeout(5)->delete('http://127.0.0.1:4040/api/tunnels');

            // Also try to kill any running ngrok processes
            Process::run('pkill -f ngrok 2>/dev/null || true');

            $this->url = null;

            return true;
        } catch (ConnectionException) {
            // ngrok API not available, try to kill process
            Process::run('pkill -f ngrok 2>/dev/null || true');
            $this->url = null;

            return true;
        }
    }

    /**
     * Check if the ngrok tunnel is currently active.
     */
    public function isActive(): bool
    {
        return $this->fetchUrlFromApi() !== null;
    }

    /**
     * Get the public URL of the ngrok tunnel.
     */
    public function getUrl(): ?string
    {
        if ($this->url) {
            return $this->url;
        }

        return $this->fetchUrlFromApi();
    }

    /**
     * Get the name of the tunnel provider.
     */
    public function getName(): string
    {
        return 'ngrok';
    }

    /**
     * Check if ngrok binary is available.
     */
    public function isNgrokAvailable(): bool
    {
        $result = Process::run(sprintf('%s version 2>/dev/null', escapeshellcmd($this->ngrokPath)));

        return $result->successful();
    }

    /**
     * Fetch the public URL from ngrok's local API.
     */
    protected function fetchUrlFromApi(): ?string
    {
        try {
            $response = Http::timeout(5)->get('http://127.0.0.1:4040/api/tunnels');

            if (! $response->successful()) {
                return null;
            }

            $data = $response->json();

            if (empty($data['tunnels'])) {
                return null;
            }

            // Find the HTTPS tunnel
            foreach ($data['tunnels'] as $tunnel) {
                if (isset($tunnel['public_url']) && str_starts_with($tunnel['public_url'], 'https://')) {
                    return $tunnel['public_url'];
                }
            }

            // Fall back to first tunnel
            return $data['tunnels'][0]['public_url'] ?? null;
        } catch (ConnectionException) {
            return null;
        }
    }
}
