<?php

namespace App\Console\Commands;

use App\Laraclaw\Tunnels\TailscaleNetworkManager;
use Illuminate\Console\Command;

class LaraclawTailscaleStatusCommand extends Command
{
    protected $signature = 'laraclaw:tailscale:status';

    protected $description = 'Show Tailscale network status including peers on the tailnet';

    public function handle(TailscaleNetworkManager $manager): int
    {
        $this->info('Checking Tailscale network status...');

        $status = $manager->getNetworkStatus();

        if (! $status['connected']) {
            $this->error('Tailscale is not connected.');
            $this->line('Run `tailscale up` to connect to your tailnet.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Tailnet: '.($status['tailnet_name'] ?? 'Unknown'));
        $this->info('MagicDNS suffix: '.($status['magic_dns_suffix'] ?? 'N/A'));

        // Self info
        $self = $status['self'];
        $this->newLine();
        $this->line('<fg=green>This device:</>');
        $this->table(
            ['Property', 'Value'],
            [
                ['Hostname', $self['hostname'] ?? 'N/A'],
                ['DNS Name', $self['dns_name'] ?? 'N/A'],
                ['OS', $self['os'] ?? 'N/A'],
                ['IP Addresses', implode(', ', $self['tailscale_ips'] ?? [])],
                ['Online', ($self['online'] ?? false) ? 'Yes' : 'No'],
            ]
        );

        // Serve status
        $serveActive = $manager->isServeActive();
        $serveUrl = $manager->getServeUrl();
        $this->newLine();
        $this->line('<fg=cyan>Serve status:</>');
        $this->line('  Active: '.($serveActive ? '<fg=green>Yes</>' : '<fg=red>No</>'));
        if ($serveUrl) {
            $this->line('  URL: '.$serveUrl);
        }

        // Peers
        $peers = $status['peers'];
        if (! empty($peers)) {
            $this->newLine();
            $this->line('<fg=yellow>Tailnet peers ('.count($peers).'):</>');

            $rows = [];
            foreach ($peers as $peer) {
                $rows[] = [
                    $peer['hostname'],
                    $peer['os'] ?? 'N/A',
                    implode(', ', $peer['tailscale_ips'] ?? []),
                    ($peer['online'] ?? false) ? 'online' : 'offline',
                    $peer['last_seen'] ?? 'N/A',
                ];
            }

            $this->table(['Hostname', 'OS', 'IPs', 'Status', 'Last Seen'], $rows);
        } else {
            $this->line('No peers found on tailnet.');
        }

        return self::SUCCESS;
    }
}
