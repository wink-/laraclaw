<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessNewMemoryJob;
use App\Laraclaw\Gateways\SlackGateway;
use App\Laraclaw\Security\SecurityManager;
use App\Laraclaw\Security\SlackSignatureVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SlackWebhookController extends Controller
{
    public function __construct(
        protected SlackGateway $gateway,
        protected SecurityManager $security,
        protected SlackSignatureVerifier $slackSignatureVerifier,
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

        if (! $this->slackSignatureVerifier->verify($request)) {
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
}
