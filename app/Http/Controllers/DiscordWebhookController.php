<?php

namespace App\Http\Controllers;

use App\Laraclaw\Facades\Laraclaw;
use App\Laraclaw\Gateways\DiscordGateway;
use App\Laraclaw\Security\SecurityManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiscordWebhookController extends Controller
{
    public function __construct(
        protected DiscordGateway $gateway,
        protected SecurityManager $security,
    ) {}

    /**
     * Handle incoming Discord webhook.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->all();
        $body = $request->getContent();

        // Verify Discord signature (Ed25519)
        $signature = $request->header('X-Signature-Ed25519');
        $timestamp = $request->header('X-Signature-Timestamp');
        $publicKey = config('services.discord.public_key');

        if ($signature && $timestamp && $publicKey) {
            if (! $this->security->verifyDiscordWebhook($publicKey, $body, $signature, $timestamp)) {
                return response()->json(['error' => 'Invalid signature'], 401);
            }
        }

        // Handle Discord ping (verification)
        if ($payload['type'] === 1) {
            return response()->json(['type' => 1]);
        }

        // Parse the incoming message
        $parsedMessage = $this->gateway->parseIncomingMessage($payload);

        // Check user authorization
        $userId = (string) ($parsedMessage['user_id'] ?? $parsedMessage['sender_id'] ?? '');
        if (! $this->security->isUserAllowed($userId, 'discord')) {
            logger()->warning('Unauthorized Discord user attempted access', [
                'user_id' => $userId,
            ]);

            return response()->json([
                'type' => 4,
                'data' => ['content' => 'You are not authorized to use this bot.'],
            ]);
        }

        // Check channel authorization
        $channelId = (string) ($parsedMessage['channel_id'] ?? '');
        if (! $this->security->isChannelAllowed($channelId, 'discord')) {
            logger()->warning('Unauthorized Discord channel', [
                'channel_id' => $channelId,
            ]);

            return response()->json([
                'type' => 4,
                'data' => ['content' => 'This channel is not authorized.'],
            ]);
        }

        // Skip empty messages
        if (empty($parsedMessage['content'])) {
            return response()->json(['type' => 1]);
        }

        // Find or create conversation
        $conversation = $this->gateway->findOrCreateConversation($parsedMessage);

        // Process with Laraclaw
        try {
            $response = Laraclaw::chat($conversation, $parsedMessage['content']);

            // For interactions, respond directly
            if (isset($parsedMessage['interaction_token'])) {
                return response()->json([
                    'type' => 4, // CHANNEL_MESSAGE_WITH_SOURCE
                    'data' => [
                        'content' => $response,
                    ],
                ]);
            }

            // For regular messages, send via API
            $this->gateway->sendMessage($conversation, $response);
        } catch (\Throwable $e) {
            $errorMessage = 'Sorry, I encountered an error processing your message. Please try again.';

            if (isset($parsedMessage['interaction_token'])) {
                return response()->json([
                    'type' => 4,
                    'data' => ['content' => $errorMessage],
                ]);
            }

            $this->gateway->sendMessage($conversation, $errorMessage);

            logger()->error('Discord webhook error', [
                'error' => $e->getMessage(),
                'conversation_id' => $conversation->id,
            ]);
        }

        return response()->json(['type' => 1]);
    }
}
