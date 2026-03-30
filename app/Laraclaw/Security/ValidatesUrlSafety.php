<?php

namespace App\Laraclaw\Security;

trait ValidatesUrlSafety
{
    protected function validateUrlSafety(string $url): ?string
    {
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return 'Error: invalid URL.';
        }

        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true)) {
            return 'Error: only http and https URLs are allowed.';
        }

        if ($host === '') {
            return 'Error: invalid URL host.';
        }

        $isLoopbackHost = in_array($host, ['localhost', '127.0.0.1', '::1'], true);

        if (! (bool) config('laraclaw.security.allow_loopback_urls', false) && $isLoopbackHost) {
            return 'Error: localhost and loopback URLs are not allowed.';
        }

        if (! (bool) config('laraclaw.security.allow_private_network_urls', false)
            && ! $isLoopbackHost
            && filter_var($host, FILTER_VALIDATE_IP)
            && ! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return 'Error: private or reserved IP addresses are not allowed.';
        }

        return null;
    }
}
