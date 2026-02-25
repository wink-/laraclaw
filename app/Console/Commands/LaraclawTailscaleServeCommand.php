<?php

namespace App\Console\Commands;

use App\Laraclaw\Tunnels\TailscaleNetworkManager;
use Illuminate\Console\Command;

class LaraclawTailscaleServeCommand extends Command
{
    protected $signature = 'laraclaw:tailscale:serve
                            {action=start : Action to perform (start, stop, status)}
                            {--port= : Local port to expose (default from config)}';

    protected $description = 'Manage Tailscale serve to expose Laraclaw on your tailnet';

    public function handle(TailscaleNetworkManager $manager): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'start' => $this->startServe($manager),
            'stop' => $this->stopServe($manager),
            'status' => $this->serveStatus($manager),
            default => $this->invalidAction($action),
        };
    }

    protected function startServe(TailscaleNetworkManager $manager): int
    {
        if (! $manager->isConnected()) {
            $this->error('Tailscale is not connected. Run `tailscale up` first.');

            return self::FAILURE;
        }

        $port = $this->option('port') ? (int) $this->option('port') : null;

        $this->info('Starting Tailscale serve...');

        if ($manager->startServe($port)) {
            $url = $manager->getServeUrl();
            $this->info('Laraclaw is now accessible on your tailnet!');
            if ($url) {
                $this->newLine();
                $this->line("  URL: <fg=green>{$url}</>");
            }
            $this->newLine();
            $this->line('Access from any device on your tailnet (phone, work PC, home PC).');

            return self::SUCCESS;
        }

        $this->error('Failed to start Tailscale serve. Check `tailscale serve status` for details.');

        return self::FAILURE;
    }

    protected function stopServe(TailscaleNetworkManager $manager): int
    {
        $this->info('Stopping Tailscale serve...');
        $manager->stopServe();
        $this->info('Tailscale serve stopped.');

        return self::SUCCESS;
    }

    protected function serveStatus(TailscaleNetworkManager $manager): int
    {
        $active = $manager->isServeActive();
        $url = $manager->getServeUrl();

        $this->line('Tailscale serve: '.($active ? '<fg=green>Active</>' : '<fg=red>Inactive</>'));

        if ($url) {
            $this->line("URL: {$url}");
        }

        $ips = $manager->getIpAddresses();
        if (! empty($ips)) {
            $this->line('Tailscale IPs: '.implode(', ', $ips));
        }

        return self::SUCCESS;
    }

    protected function invalidAction(string $action): int
    {
        $this->error("Unknown action: {$action}. Use start, stop, or status.");

        return self::FAILURE;
    }
}
