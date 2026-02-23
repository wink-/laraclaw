<?php

namespace App\Console\Commands;

use App\Laraclaw\Tunnels\TunnelManager;
use Illuminate\Console\Command;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

class LaraclawTunnelCommand extends Command
{
    protected $signature = 'laraclaw:tunnel
                            {action : Action to perform (start, stop, status)}
                            {--provider= : Tunnel provider to use (ngrok, cloudflare, tailscale)}
                            {--port=8000 : Local port to tunnel}';

    protected $description = 'Manage local development tunnels (ngrok, Cloudflare, Tailscale)';

    protected TunnelManager $tunnelManager;

    public function __construct()
    {
        parent::__construct();
    }

    public function handle(TunnelManager $tunnelManager): int
    {
        $this->tunnelManager = $tunnelManager;

        $action = $this->argument('action');

        return match ($action) {
            'start' => $this->startTunnel(),
            'stop' => $this->stopTunnel(),
            'status' => $this->showStatus(),
            default => $this->invalidAction($action),
        };
    }

    protected function startTunnel(): int
    {
        $provider = $this->option('provider');
        $port = (int) $this->option('port');

        // If no provider specified, try to detect or prompt
        if (! $provider) {
            $provider = $this->tunnelManager->detectAvailableProvider();

            if (! $provider) {
                error('No tunnel providers available. Please install ngrok, cloudflared, or tailscale.');

                return self::FAILURE;
            }

            $availableProviders = array_filter(
                $this->tunnelManager->getAvailableProviders(),
                fn ($p) => $this->tunnelManager->isProviderAvailable($p)
            );

            if (count($availableProviders) > 1) {
                $provider = select(
                    label: 'Select a tunnel provider',
                    options: $availableProviders,
                    default: $provider
                );
            }
        }

        info("Starting {$provider} tunnel on port {$port}...");

        $success = spin(
            fn () => $this->tunnelManager->start($provider, ['port' => $port]),
            "Starting {$provider} tunnel..."
        );

        if ($success) {
            $url = $this->tunnelManager->getUrl();
            info('Tunnel started successfully!');
            $this->newLine();
            info("Public URL: {$url}");
            $this->newLine();

            $this->displayTunnelInfo($provider, $url);

            return self::SUCCESS;
        }

        error("Failed to start {$provider} tunnel.");

        if (! $this->tunnelManager->isProviderAvailable($provider)) {
            $this->line("  The '{$provider}' provider is not available or not installed.");
            $this->line('  Please install it first:');
            $this->displayInstallInstructions($provider);
        }

        return self::FAILURE;
    }

    protected function stopTunnel(): int
    {
        $activeProvider = $this->tunnelManager->getActiveProvider();

        if (! $activeProvider) {
            warning('No active tunnel found.');

            return self::SUCCESS;
        }

        info("Stopping {$activeProvider} tunnel...");

        $success = spin(
            fn () => $this->tunnelManager->stop(),
            'Stopping tunnel...'
        );

        if ($success) {
            info('Tunnel stopped successfully.');

            return self::SUCCESS;
        }

        error('Failed to stop tunnel.');

        return self::FAILURE;
    }

    protected function showStatus(): int
    {
        info('Laraclaw Tunnel Status');
        info('======================');
        $this->newLine();

        $status = $this->tunnelManager->getStatus();
        $activeProvider = $this->tunnelManager->getActiveProvider();

        if ($activeProvider) {
            $url = $this->tunnelManager->getUrl();
            info("Active Tunnel: {$activeProvider}");
            info("Public URL: {$url}");
            $this->newLine();
        }

        info('Provider Status:');

        foreach ($status as $provider => $info) {
            $available = $info['available'] ? '<info>installed</info>' : '<comment>not installed</comment>';
            $active = $info['active'] ? '<info>active</info>' : '<comment>inactive</comment>';

            $this->line("  {$provider}:");
            $this->line("    Binary: {$available}");
            $this->line("    Status: {$active}");

            if ($info['url']) {
                $this->line("    URL: {$info['url']}");
            }
        }

        $this->newLine();

        // Show available but not active providers
        $availableButNotActive = array_filter(
            $status,
            fn ($info, $name) => $info['available'] && ! $info['active'],
            ARRAY_FILTER_USE_BOTH
        );

        if (! empty($availableButNotActive)) {
            $providers = implode(', ', array_keys($availableButNotActive));
            info("Available providers: {$providers}");
            $this->line('  Run <info>php artisan laraclaw:tunnel start</info> to create a tunnel.');
        }

        return self::SUCCESS;
    }

    protected function invalidAction(string $action): int
    {
        error("Invalid action: {$action}");
        $this->line('Valid actions: start, stop, status');

        return self::FAILURE;
    }

    protected function displayTunnelInfo(string $provider, string $url): void
    {
        $this->line('Usage:');
        $this->line("  - Share your local app: {$url}");
        $this->line("  - Webhook URL: {$url}/webhook");
        $this->line("  - API endpoint: {$url}/api");

        $this->newLine();
        $this->line('Press Ctrl+C to stop the tunnel when done.');
    }

    protected function displayInstallInstructions(string $provider): void
    {
        $instructions = [
            'ngrok' => [
                '  Download from: https://ngrok.com/download',
                '  Or: brew install ngrok',
                '  Then: ngrok config add-authtoken YOUR_TOKEN',
            ],
            'cloudflare' => [
                '  Download from: https://developers.cloudflare.com/cloudflare-one/connections/connect-apps/install-and-setup/',
                '  Or: brew install cloudflared',
            ],
            'tailscale' => [
                '  Download from: https://tailscale.com/download',
                '  Or: brew install tailscale',
                '  Then: tailscale login && tailscale up',
            ],
        ];

        foreach ($instructions[$provider] ?? [] as $line) {
            $this->line($line);
        }
    }
}
