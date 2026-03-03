<?php

namespace App\Laraclaw\Security;

use Illuminate\Http\Request;

class SlackSignatureVerifier
{
    public function verify(Request $request): bool
    {
        $signingSecret = (string) config('services.slack.signing_secret', '');

        if ($signingSecret === '') {
            return true;
        }

        $timestamp = (string) $request->header('X-Slack-Request-Timestamp');
        $signature = (string) $request->header('X-Slack-Signature');

        if ($timestamp === '' || $signature === '') {
            return false;
        }

        if (abs(time() - (int) $timestamp) > 60 * 5) {
            return false;
        }

        $baseString = 'v0:'.$timestamp.':'.$request->getContent();
        $expected = 'v0='.hash_hmac('sha256', $baseString, $signingSecret);

        return hash_equals($expected, $signature);
    }
}
