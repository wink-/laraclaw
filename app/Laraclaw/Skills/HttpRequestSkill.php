<?php

namespace App\Laraclaw\Skills;

use App\Laraclaw\Skills\Contracts\SkillInterface;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class HttpRequestSkill implements SkillInterface, Tool
{
    /**
     * @var array<int, string>
     */
    protected array $allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

    public function name(): string
    {
        return 'http_request';
    }

    public function description(): Stringable|string
    {
        return 'Send safe outbound HTTP requests with method, headers, query, and payload support.';
    }

    public function execute(array $parameters): string
    {
        $method = strtoupper((string) ($parameters['method'] ?? 'GET'));
        $url = trim((string) ($parameters['url'] ?? ''));
        $timeout = max(1, min(30, (int) ($parameters['timeout'] ?? 10)));

        if ($url === '') {
            return 'Error: url is required.';
        }

        if (! in_array($method, $this->allowedMethods, true)) {
            return 'Error: method must be one of GET, POST, PUT, PATCH, DELETE.';
        }

        $safetyError = $this->validateUrlSafety($url);
        if ($safetyError !== null) {
            return $safetyError;
        }

        $headers = is_array($parameters['headers'] ?? null) ? $parameters['headers'] : [];
        $query = is_array($parameters['query'] ?? null) ? $parameters['query'] : [];
        $jsonBody = is_array($parameters['json'] ?? null) ? $parameters['json'] : null;
        $body = $parameters['body'] ?? null;

        try {
            $client = Http::timeout($timeout)->withHeaders($headers);

            if (! empty($query)) {
                $client = $client->withQueryParameters($query);
            }

            $response = match ($method) {
                'GET' => $client->get($url),
                'POST' => $jsonBody !== null ? $client->post($url, $jsonBody) : $client->send('POST', $url, ['body' => $body]),
                'PUT' => $jsonBody !== null ? $client->put($url, $jsonBody) : $client->send('PUT', $url, ['body' => $body]),
                'PATCH' => $jsonBody !== null ? $client->patch($url, $jsonBody) : $client->send('PATCH', $url, ['body' => $body]),
                'DELETE' => $jsonBody !== null ? $client->send('DELETE', $url, ['json' => $jsonBody]) : $client->delete($url),
            };

            $responseBody = Str::limit((string) $response->body(), 4000, "\n... (response truncated)");

            return "HTTP {$method} {$url}\n"
                ."Status: {$response->status()}\n"
                .'Success: '.($response->successful() ? 'yes' : 'no')."\n\n"
                ."Body:\n{$responseBody}";
        } catch (\Throwable $exception) {
            return 'Error performing HTTP request: '.$exception->getMessage();
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'url' => $schema->string()->required()->description('Public http/https endpoint URL'),
            'method' => $schema->string()->enum($this->allowedMethods)->description('HTTP method to use'),
            'headers' => $schema->object()->description('Optional request headers as key/value pairs'),
            'query' => $schema->object()->description('Optional query params as key/value pairs'),
            'json' => $schema->object()->description('Optional JSON payload for POST/PUT/PATCH/DELETE requests'),
            'body' => $schema->string()->description('Optional raw request body when JSON payload is not provided'),
            'timeout' => $schema->integer()->min(1)->max(30)->description('Request timeout in seconds (1-30)'),
        ];
    }

    public function toTool(): Tool
    {
        return $this;
    }

    public function handle(Request $request): Stringable|string
    {
        return $this->execute($request->all());
    }

    protected function validateUrlSafety(string $url): ?string
    {
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return 'Error: invalid URL.';
        }

        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $allowPrivateNetwork = (bool) config('laraclaw.security.allow_private_network_urls', false);
        $allowLoopback = (bool) config('laraclaw.security.allow_loopback_urls', false);

        if (! in_array($scheme, ['http', 'https'], true)) {
            return 'Error: only http and https URLs are allowed.';
        }

        if ($host === '') {
            return 'Error: invalid URL host.';
        }

        $isLoopbackHost = in_array($host, ['localhost', '127.0.0.1', '::1'], true);

        if (! $allowLoopback && $isLoopbackHost) {
            return 'Error: localhost and loopback URLs are not allowed.';
        }

        if (! $allowPrivateNetwork
            && ! $isLoopbackHost
            && filter_var($host, FILTER_VALIDATE_IP)
            && ! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return 'Error: private or reserved IP addresses are not allowed.';
        }

        return null;
    }
}
