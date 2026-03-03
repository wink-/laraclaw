<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessNewMemoryJob;
use App\Laraclaw\Gateways\SlackGateway;
use App\Laraclaw\Security\SecurityManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SlackWebhookController extends Controller
{
    public function __construct(
        protected SlackGateway $gateway,
        protected SecurityManager $security,
    ) {}

    /**
     * Handle incoming Slack webhook.
     */
    public function __invoke(Request $request): JsonResponse
    {
        if ($request->input('type') === 'url_verification') {
            return response()->json([
                'challenge' => $request->input('challenge'),
            ]);
        }

        if (! $this->verifySlackSignature($request)) {
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        $parsedMessage = $this->gateway->parseIncomingMessage($request->all());

        if (empty($parsedMessage['content'])) {
            return response()->json(['status' => 'ignored']);
        }

        $userId = (string) ($parsedMessage['sender_id'] ?? '');
        if ($userId !== '' && ! $this->security->isUserAllowed($userId, 'slack')) {
            return response()->json(['status' => 'unauthorized'], 403);
        }

        $channelId = (string) ($parsedMessage['channel_id'] ?? '');
        if ($channelId !== '' && ! $this->security->isChannelAllowed($channelId, 'slack')) {
            return response()->json(['status' => 'unauthorized'], 403);
        }

        $conversation = $this->gateway->findOrCreateConversation($parsedMessage);

        ProcessNewMemoryJob::dispatch(
            conversationId: $conversation->id,
            platform: 'slack',
            content: (string) $parsedMessage['content'],
            parsedMessage: $parsedMessage,
        );

        return response()->json([
            'status' => 'queued',
            'conversation_id' => $conversation->id,
        ], 202);
    }

    protected function verifySlackSignature(Request $request): bool
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
