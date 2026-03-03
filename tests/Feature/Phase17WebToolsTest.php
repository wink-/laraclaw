<?php

use App\Laraclaw\Skills\HttpRequestSkill;
use App\Laraclaw\Skills\WebFetchSkill;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('performs safe http requests with query and headers', function () {
    Http::fake([
        'https://api.example.test/*' => function (Request $request) {
            expect($request->url())->toContain('q=laraclaw')
                ->and($request->header('X-Test')[0] ?? null)->toBe('phase17');

            return Http::response(['ok' => true, 'source' => 'api-example'], 200);
        },
    ]);

    $skill = app(HttpRequestSkill::class);

    $result = $skill->execute([
        'method' => 'GET',
        'url' => 'https://api.example.test/search',
        'query' => ['q' => 'laraclaw'],
        'headers' => ['X-Test' => 'phase17'],
        'timeout' => 5,
    ]);

    expect($result)->toContain('Status: 200')
        ->and($result)->toContain('Success: yes')
        ->and($result)->toContain('api-example');
});

it('blocks unsafe targets for http request skill', function () {
    $skill = app(HttpRequestSkill::class);

    $result = $skill->execute([
        'method' => 'GET',
        'url' => 'http://127.0.0.1:8080/secret',
    ]);

    expect($result)->toContain('not allowed');
});

it('allows private network targets when explicitly enabled', function () {
    config()->set('laraclaw.security.allow_private_network_urls', true);

    Http::fake([
        'http://100.64.0.5/*' => Http::response(['ok' => true, 'source' => 'tailscale-node'], 200),
    ]);

    $skill = app(HttpRequestSkill::class);

    $result = $skill->execute([
        'method' => 'GET',
        'url' => 'http://100.64.0.5/internal',
    ]);

    expect($result)->toContain('Status: 200')
        ->and($result)->toContain('tailscale-node');
});

it('fetches a web page and returns cleaned text content', function () {
    Http::fake([
        'https://docs.example.test/*' => Http::response(<<<'HTML'
            <html>
            <head><title>Laraclaw Docs</title></head>
            <body>
                <h1>Welcome</h1>
                <p>Laraclaw keeps memory and tools unified.</p>
                <script>console.log('ignored');</script>
            </body>
            </html>
            HTML, 200),
    ]);

    $skill = app(WebFetchSkill::class);

    $result = $skill->execute([
        'url' => 'https://docs.example.test/guide',
        'max_chars' => 1000,
    ]);

    expect($result)->toContain('Title: Laraclaw Docs')
        ->and($result)->toContain('Laraclaw keeps memory and tools unified.')
        ->and($result)->not->toContain('console.log');
});

it('rejects unsupported schemes for web fetch skill', function () {
    $skill = app(WebFetchSkill::class);

    $result = $skill->execute([
        'url' => 'ftp://example.com/file.txt',
    ]);

    expect($result)->toContain('only http and https URLs are allowed');
});

it('allows loopback fetch when explicitly enabled', function () {
    config()->set('laraclaw.security.allow_loopback_urls', true);

    Http::fake([
        'http://127.0.0.1/*' => Http::response('<html><head><title>Local Service</title></head><body>ok</body></html>', 200),
    ]);

    $skill = app(WebFetchSkill::class);

    $result = $skill->execute([
        'url' => 'http://127.0.0.1/status',
    ]);

    expect($result)->toContain('Title: Local Service')
        ->and($result)->toContain('Content:');
});
