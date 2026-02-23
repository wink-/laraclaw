<?php

namespace App\Http\Controllers;

use App\Laraclaw\Facades\Laraclaw;
use App\Laraclaw\Gateways\TelegramGateway;
use App\Laraclaw\Security\SecurityManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramWebhookController extends Controller
{
    public function __construct(
        protected TelegramGateway $gateway,
        protected SecurityManager $security,
    ) {}

    /**
     * Handle incoming Telegram webhook.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->all();

        // Verify the webhook secret token
        $secretToken = $request->header('X-Telegram-Bot-Api-Secret-Token');
        $configuredSecret = config('services.telegram.secret_token');

        if (! $this->security->verifyTelegramWebhook($configuredSecret, $secretToken)) {
            return response()->json(['error' => 'Invalid webhook'], 403);
        }

        // Parse the incoming message
        $parsedMessage = $this->gateway->parseIncomingMessage($payload);

        // Check user authorization
        $userId = (string) ($parsedMessage['user_id'] ?? $parsedMessage['sender_id'] ?? '');
        if (! $this->security->isUserAllowed($userId, 'telegram')) {
            logger()->warning('Unauthorized Telegram user attempted access', [
                'user_id' => $userId,
            ]);

            return response()->json(['status' => 'unauthorized'], 403);
        }

        // Check channel authorization
        $chatId = (string) ($parsedMessage['chat_id'] ?? '');
        if (! $this->security->isChannelAllowed($chatId, 'telegram')) {
            logger()->warning('Unauthorized Telegram channel', [
                'chat_id' => $chatId,
            ]);

            return response()->json(['status' => 'unauthorized'], 403);
        }

        // Skip empty messages
        if (empty($parsedMessage['content'])) {
            return response()->json(['status' => 'ignored']);
        }

        // Find or create conversation
        $conversation = $this->gateway->findOrCreateConversation($parsedMessage);

        // Process with Laraclaw
        try {
            $response = Laraclaw::chat($conversation, $parsedMessage['content']);

            // Send response back to Telegram
            $this->gateway->sendMessage($conversation, $response);
        } catch (\Throwable $e) {
            // Send error message to user
            $this->gateway->sendMessage(
                $conversation,
                'Sorry, I encountered an error processing your message. Please try again.'
            );

            // Log the error
            logger()->error('Telegram webhook error', [
                'error' => $e->getMessage(),
                'conversation_id' => $conversation->id,
            ]);
        }

        return response()->json(['status' => 'ok']);
    }
}
