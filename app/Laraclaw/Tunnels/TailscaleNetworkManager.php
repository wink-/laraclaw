<?php

namespace App\Laraclaw\Tunnels;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;

class TailscaleNetworkManager
{
    protected string $tailscalePath;

    protected int $port;

    public function __construct(?string $tailscalePath = null, ?int $port = null)
    {
        $this->tailscalePath = $tailscalePath ?? config('laraclaw.tunnels.providers.tailscale.path', 'tailscale');
        $this->port = $port ?? (int) config('laraclaw.tailscale.serve_port', 8000);
    }

    /**
     * Get comprehensive tailnet status including self info and peers.
     *
     * @return array{connected: bool, self: array<string, mixed>, peers: array<int, array<string, mixed>>, tailnet_name: ?string, magic_dns_suffix: ?string}
     */
    public function getNetworkStatus(): array
    {
        $result = Process::run(sprintf('%s status --json 2>/dev/null', escapeshellcmd($this->tailscalePath)));

        if (! $result->successful()) {
            return [
                'connected' => false,
                'self' => [],
                'peers' => [],
                'tailnet_name' => null,
                'magic_dns_suffix' => null,
            ];
        }

        $data = json_decode($result->output(), true);

        if (empty($data) || ($data['BackendState'] ?? '') !== 'Running') {
            return [
                'connected' => false,
                'self' => [],
                'peers' => [],
                'tailnet_name' => null,
                'magic_dns_suffix' => null,
            ];
        }

        $selfInfo = $this->parseSelfInfo($data);
        $peers = $this->parsePeers($data);
        $magicDnsSuffix = $data['MagicDNSSuffix'] ?? null;
        $tailnetName = $data['CurrentTailnet']['Name'] ?? null;

        return [
            'connected' => true,
            'self' => $selfInfo,
            'peers' => $peers,
            'tailnet_name' => $tailnetName,
            'magic_dns_suffix' => $magicDnsSuffix,
        ];
    }

    /**
     * Start `tailscale serve` to expose the app on the tailnet (private, no public funnel).
     */
    public function startServe(?int $port = null): bool
    {
        $port = $port ?? $this->port;

        if ($this->isServeActive()) {
            return true;
        }

        if (! $this->isConnected()) {
            return false;
        }

        $command = sprintf(
            '%s serve --bg --https=%d http://127.0.0.1:%d 2>&1',
            escapeshellcmd($this->tailscalePath),
            443,
            $port
        );

        $result = Process::run($command);

        if ($result->successful() || str_contains($result->output(), 'Available within your tailnet')) {
            $url = $this->getServeUrl();
            Cache::put('laraclaw.tailscale.serve_active', true, now()->addHours(24));
            Cache::put('laraclaw.tailscale.serve_url', $url, now()->addHours(24));

            return true;
        }

        return false;
    }

    /**
     * Stop `tailscale serve`.
     */
    public function stopServe(): bool
    {
        $result = Process::run(sprintf('%s serve --https=443 off 2>/dev/null || true', escapeshellcmd($this->tailscalePath)));

        Cache::forget('laraclaw.tailscale.serve_active');
        Cache::forget('laraclaw.tailscale.serve_url');

        return true;
    }

    /**
     * Check if `tailscale serve` is currently active.
     */
    public function isServeActive(): bool
    {
        $result = Process::run(sprintf('%s serve status 2>/dev/null', escapeshellcmd($this->tailscalePath)));

        if (! $result->successful()) {
            Cache::forget('laraclaw.tailscale.serve_active');

            return false;
        }

        $output = $result->output();

        // If the output shows active handlers, serve is running
        return str_contains($output, 'http://') || str_contains($output, 'proxy');
    }

    /**
     * Get the HTTPS URL for this device on the tailnet.
     */
    public function getServeUrl(): ?string
    {
        $status = $this->getNetworkStatus();

        if (! $status['connected'] || empty($status['self'])) {
            return null;
        }

        $dnsName = $status['self']['dns_name'] ?? null;

        if ($dnsName) {
            return 'https://'.rtrim($dnsName, '.');
        }

        return null;
    }

    /**
     * Check if Tailscale is connected.
     */
    public function isConnected(): bool
    {
        $result = Process::run(sprintf('%s status --json 2>/dev/null', escapeshellcmd($this->tailscalePath)));

        if (! $result->successful()) {
            return false;
        }

        $data = json_decode($result->output(), true);

        return ! empty($data['BackendState']) && $data['BackendState'] === 'Running';
    }

    /**
     * Get the Tailscale IP addresses for this device.
     *
     * @return array<int, string>
     */
    public function getIpAddresses(): array
    {
        $result = Process::run(sprintf('%s ip 2>/dev/null', escapeshellcmd($this->tailscalePath)));

        if (! $result->successful()) {
            return [];
        }

        return array_filter(array_map('trim', explode("\n", $result->output())));
    }

    /**
     * Get the MagicDNS hostname for this device.
     */
    public function getHostname(): ?string
    {
        $status = $this->getNetworkStatus();

        return $status['self']['dns_name'] ?? null;
    }

    /**
     * Get the Tailscale certificate paths for HTTPS.
     *
     * @return array{cert: ?string, key: ?string}
     */
    public function getCertPaths(): array
    {
        $hostname = $this->getHostname();

        if (! $hostname) {
            return ['cert' => null, 'key' => null];
        }

        $hostname = rtrim($hostname, '.');

        $result = Process::run(sprintf(
            '%s cert --cert-file=/dev/null --key-file=/dev/null %s 2>&1',
            escapeshellcmd($this->tailscalePath),
            escapeshellarg($hostname)
        ));

        // Tailscale stores certs in a standard location
        $certDir = '/var/lib/tailscale/certs';

        if (is_dir($certDir)) {
            $certFile = $certDir.'/'.$hostname.'.crt';
            $keyFile = $certDir.'/'.$hostname.'.key';

            if (file_exists($certFile) && file_exists($keyFile)) {
                return ['cert' => $certFile, 'key' => $keyFile];
            }
        }

        return ['cert' => null, 'key' => null];
    }

    /**
     * Parse self info from Tailscale status JSON.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function parseSelfInfo(array $data): array
    {
        $self = $data['Self'] ?? [];

        if (empty($self)) {
            return [];
        }

        return [
            'id' => $self['ID'] ?? null,
            'hostname' => $self['HostName'] ?? null,
            'dns_name' => $self['DNSName'] ?? null,
            'os' => $self['OS'] ?? null,
            'tailscale_ips' => $self['TailscaleIPs'] ?? [],
            'online' => $self['Online'] ?? false,
            'active' => $self['Active'] ?? false,
        ];
    }

    /**
     * Parse peer info from Tailscale status JSON.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, array<string, mixed>>
     */
    protected function parsePeers(array $data): array
    {
        $peers = [];
        $peerMap = $data['Peer'] ?? [];

        foreach ($peerMap as $id => $peer) {
            $peers[] = [
                'id' => $id,
                'hostname' => $peer['HostName'] ?? 'unknown',
                'dns_name' => $peer['DNSName'] ?? null,
                'os' => $peer['OS'] ?? null,
                'tailscale_ips' => $peer['TailscaleIPs'] ?? [],
                'online' => $peer['Online'] ?? false,
                'active' => $peer['Active'] ?? false,
                'last_seen' => $peer['LastSeen'] ?? null,
                'exit_node' => $peer['ExitNode'] ?? false,
            ];
        }

        return $peers;
    }
}
