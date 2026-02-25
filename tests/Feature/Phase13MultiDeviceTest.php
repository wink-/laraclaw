<?php

use App\Laraclaw\Heartbeat\HeartbeatEngine;
use App\Laraclaw\Tunnels\TailscaleNetworkManager;
use App\Models\HeartbeatRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;

uses(RefreshDatabase::class);

// --- Tailscale Network Manager ---

it('reports disconnected when tailscale is not running', function () {
    Process::fake([
        '*tailscale status*' => Process::result(output: '', exitCode: 1),
    ]);

    $manager = new TailscaleNetworkManager;
    $status = $manager->getNetworkStatus();

    expect($status['connected'])->toBeFalse()
        ->and($status['peers'])->toBeEmpty();
});

it('parses tailscale network status when connected', function () {
    $fakeJson = json_encode([
        'BackendState' => 'Running',
        'Self' => [
            'ID' => 'self-123',
            'HostName' => 'laraclaw-dev',
            'DNSName' => 'laraclaw-dev.tailnet.ts.net.',
            'OS' => 'linux',
            'TailscaleIPs' => ['100.64.0.1', 'fd7a::1'],
            'Online' => true,
            'Active' => true,
        ],
        'Peer' => [
            'peer-456' => [
                'HostName' => 'phone',
                'DNSName' => 'phone.tailnet.ts.net.',
                'OS' => 'android',
                'TailscaleIPs' => ['100.64.0.2'],
                'Online' => true,
                'Active' => true,
                'LastSeen' => '2026-01-01T00:00:00Z',
                'ExitNode' => false,
            ],
        ],
        'CurrentTailnet' => ['Name' => 'example@gmail.com'],
        'MagicDNSSuffix' => 'tailnet.ts.net',
    ]);

    Process::fake([
        '*tailscale status*' => Process::result(output: $fakeJson, exitCode: 0),
    ]);

    $manager = new TailscaleNetworkManager;
    $status = $manager->getNetworkStatus();

    expect($status['connected'])->toBeTrue()
        ->and($status['tailnet_name'])->toBe('example@gmail.com')
        ->and($status['self']['hostname'])->toBe('laraclaw-dev')
        ->and($status['self']['tailscale_ips'])->toContain('100.64.0.1')
        ->and($status['peers'])->toHaveCount(1)
        ->and($status['peers'][0]['hostname'])->toBe('phone')
        ->and($status['peers'][0]['os'])->toBe('android');
});

it('returns tailscale ip addresses', function () {
    Process::fake([
        '*tailscale ip*' => Process::result(output: "100.64.0.1\nfd7a::1\n", exitCode: 0),
        '*tailscale status*' => Process::result(output: '', exitCode: 1),
    ]);

    $manager = new TailscaleNetworkManager;
    $ips = $manager->getIpAddresses();

    expect($ips)->toContain('100.64.0.1')
        ->and($ips)->toContain('fd7a::1');
});

it('runs tailscale status artisan command', function () {
    Process::fake([
        '*tailscale status*' => Process::result(output: json_encode([
            'BackendState' => 'Running',
            'Self' => [
                'ID' => 'x',
                'HostName' => 'dev',
                'DNSName' => 'dev.ts.net.',
                'OS' => 'linux',
                'TailscaleIPs' => ['100.64.0.1'],
                'Online' => true,
                'Active' => true,
            ],
            'Peer' => [],
            'CurrentTailnet' => ['Name' => 'me@example.com'],
            'MagicDNSSuffix' => 'ts.net',
        ]), exitCode: 0),
        '*tailscale serve*' => Process::result(output: '', exitCode: 1),
    ]);

    $this->artisan('laraclaw:tailscale:status')
        ->assertSuccessful();
});

// --- Heartbeat Engine ---

it('parses heartbeat file with enabled and disabled items', function () {
    $path = storage_path('laraclaw/test_heartbeat.md');

    file_put_contents($path, implode("\n", [
        '# Test Heartbeat',
        '- [x] Check system health @every(30m)',
        '- [ ] Summarize conversations @daily',
        '- [x] Report disk usage @every(6h)',
    ]));

    $engine = new HeartbeatEngine($path);
    $items = $engine->parseHeartbeatFile();

    expect($items)->toHaveCount(3)
        ->and($items[0]['enabled'])->toBeTrue()
        ->and($items[0]['schedule'])->toBe('every:30m')
        ->and($items[1]['enabled'])->toBeFalse()
        ->and($items[1]['schedule'])->toBe('every:24h')
        ->and($items[2]['enabled'])->toBeTrue()
        ->and($items[2]['schedule'])->toBe('every:6h');

    @unlink($path);
});

it('returns empty array when heartbeat file does not exist', function () {
    $engine = new HeartbeatEngine('/nonexistent/HEARTBEAT.md');

    expect($engine->parseHeartbeatFile())->toBeEmpty();
});

it('strips schedule annotations from instruction text', function () {
    $path = storage_path('laraclaw/test_strip.md');

    file_put_contents($path, '- [x] Check system health @every(1h)');

    $engine = new HeartbeatEngine($path);
    $items = $engine->parseHeartbeatFile();

    expect($items[0]['instruction'])->toBe('Check system health')
        ->and($items[0]['schedule'])->toBe('every:1h');

    @unlink($path);
});

it('creates heartbeat run records in the database', function () {
    HeartbeatRun::create([
        'heartbeat_id' => 'heartbeat_1',
        'instruction' => 'Test instruction',
        'status' => 'success',
        'response' => 'All good',
        'executed_at' => now(),
    ]);

    expect(HeartbeatRun::query()->count())->toBe(1)
        ->and(HeartbeatRun::query()->first()->status)->toBe('success');
});

it('runs heartbeat command in dry-run mode', function () {
    $path = storage_path('laraclaw/test_dryrun.md');

    file_put_contents($path, implode("\n", [
        '- [x] Check health @hourly',
        '- [ ] Send report @daily',
    ]));

    config()->set('laraclaw.heartbeat.path', $path);

    $this->artisan('laraclaw:heartbeat:run', ['--dry-run' => true])
        ->assertSuccessful();

    @unlink($path);
});

it('reports no heartbeat items when file is missing', function () {
    config()->set('laraclaw.heartbeat.path', '/nonexistent/HEARTBEAT.md');

    $this->artisan('laraclaw:heartbeat:run')
        ->assertSuccessful();
});

// --- PWA & Layout ---

it('has pwa manifest json in public directory', function () {
    $path = public_path('manifest.json');

    expect(file_exists($path))->toBeTrue();

    $manifest = json_decode(file_get_contents($path), true);

    expect($manifest['short_name'])->toBe('Laraclaw')
        ->and($manifest['display'])->toBe('standalone');
});

it('has service worker js in public directory', function () {
    expect(file_exists(public_path('sw.js')))->toBeTrue();

    $content = file_get_contents(public_path('sw.js'));

    expect($content)->toContain('CACHE_NAME')
        ->and($content)->toContain('fetch');
});

it('has offline fallback page in public directory', function () {
    expect(file_exists(public_path('offline.html')))->toBeTrue();

    $content = file_get_contents(public_path('offline.html'));

    expect($content)->toContain('Laraclaw')
        ->and($content)->toContain('Offline');
});

// --- Config & Integration ---

it('has tailscale config section', function () {
    expect(config('laraclaw.tailscale'))->toBeArray()
        ->and(config('laraclaw.tailscale.enabled'))->toBeBool()
        ->and(config('laraclaw.tailscale.serve_port'))->toBeInt();
});

it('has heartbeat config section', function () {
    expect(config('laraclaw.heartbeat'))->toBeArray()
        ->and(config('laraclaw.heartbeat.enabled'))->toBeBool();
});

it('registers tailscale network manager in container', function () {
    expect(app(TailscaleNetworkManager::class))->toBeInstanceOf(TailscaleNetworkManager::class);
});

it('registers heartbeat engine in container', function () {
    expect(app(HeartbeatEngine::class))->toBeInstanceOf(HeartbeatEngine::class);
});
