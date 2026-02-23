<?php

namespace App\Http\Controllers;

use App\Laraclaw\Facades\Laraclaw;
use App\Laraclaw\Gateways\DiscordGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiscordWebhookController extends Controller
{
    public function __construct(
        protected DiscordGateway $gateway,
    ) {}

    /**
     * Handle incoming Discord webhook.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->all();

        // Verify the webhook
        if (! $this->gateway->verifyWebhook($payload)) {
            return response()->json(['error' => 'Invalid webhook'], 403);
        }

        // Handle Discord ping (verification)
        if ($payload['type'] === 1) {
            return response()->json(['type' => 1]);
        }

        // Parse the incoming message
        $parsedMessage = $this->gateway->parseIncomingMessage($payload);

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
