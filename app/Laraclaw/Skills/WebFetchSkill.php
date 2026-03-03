<?php

namespace App\Laraclaw\Skills;

use App\Laraclaw\Skills\Contracts\SkillInterface;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class WebFetchSkill implements SkillInterface, Tool
{
    public function name(): string
    {
        return 'web_fetch';
    }

    public function description(): Stringable|string
    {
        return 'Fetch a webpage and return clean, concise text content for analysis.';
    }

    public function execute(array $parameters): string
    {
        $url = trim((string) ($parameters['url'] ?? ''));
        $maxChars = max(200, min(10000, (int) ($parameters['max_chars'] ?? 3000)));
        $timeout = max(1, min(30, (int) ($parameters['timeout'] ?? 10)));

        if ($url === '') {
            return 'Error: url is required.';
        }

        $safetyError = $this->validateUrlSafety($url);
        if ($safetyError !== null) {
            return $safetyError;
        }

        try {
            $response = Http::timeout($timeout)
                ->accept('text/html,application/xhtml+xml;q=0.9,text/plain;q=0.8,*/*;q=0.5')
                ->get($url);

            if (! $response->successful()) {
                return "Error: fetch failed with status {$response->status()}.";
            }

            $body = (string) $response->body();
            $title = $this->extractTitle($body);
            $text = $this->extractTextContent($body);

            if ($text === '') {
                $text = Str::limit($body, $maxChars, "\n... (content truncated)");
            } else {
                $text = Str::limit($text, $maxChars, "\n... (content truncated)");
            }

            $output = "Fetched: {$url}\nStatus: {$response->status()}";

            if ($title !== null) {
                $output .= "\nTitle: {$title}";
            }

            $output .= "\n\nContent:\n{$text}";

            return $output;
        } catch (\Throwable $exception) {
            return 'Error fetching page: '.$exception->getMessage();
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'url' => $schema->string()->required()->description('Public webpage URL to fetch (http/https only)'),
            'max_chars' => $schema->integer()->min(200)->max(10000)->description('Maximum content length returned (default: 3000)'),
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

    protected function extractTitle(string $html): ?string
    {
        if (! preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
            return null;
        }

        $title = trim(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES | ENT_HTML5));

        return $title !== '' ? $title : null;
    }

    protected function extractTextContent(string $body): string
    {
        $withoutScripts = preg_replace('/<(script|style)[^>]*>.*?<\/(script|style)>/is', ' ', $body);
        $text = strip_tags((string) $withoutScripts);

        return (string) Str::of(html_entity_decode($text, ENT_QUOTES | ENT_HTML5))->squish();
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
