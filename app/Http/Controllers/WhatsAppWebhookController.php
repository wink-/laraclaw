<?php

namespace App\Http\Controllers;

use App\Laraclaw\Facades\Laraclaw;
use App\Laraclaw\Gateways\WhatsAppGateway;
use App\Laraclaw\Security\SecurityManager;
use App\Laraclaw\Voice\VoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppWebhookController extends Controller
{
    public function __construct(
        protected WhatsAppGateway $gateway,
        protected SecurityManager $security,
        protected VoiceService $voice,
    ) {}

    /**
     * Handle WhatsApp webhook verification (GET).
     */
    public function verify(Request $request)
    {
        $mode = $request->query('hub.mode');
        $token = $request->query('hub.verify_token');
        $challenge = $request->query('hub.challenge');

        $verifyToken = config('laraclaw.gateways.whatsapp.verify_token', env('WHATSAPP_VERIFY_TOKEN'));

        if ($mode === 'subscribe' && $token === $verifyToken) {
            return response($challenge, 200);
        }

        return response('Forbidden', 403);
    }

    /**
     * Handle incoming WhatsApp webhook (POST).
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();

        // Verify the webhook signature
        $signature = $request->header('X-Hub-Signature-256');
        if (! $this->gateway->verifyWebhook($payload, $signature)) {
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        // Parse the incoming message
        $parsedMessage = $this->gateway->parseIncomingMessage($payload);

        $mediaId = $parsedMessage['voice_media_id'] ?? $parsedMessage['audio_media_id'] ?? null;
        if ($mediaId) {
            $localPath = $this->gateway->downloadMedia($mediaId);
            if ($localPath) {
                try {
                    $transcript = $this->voice->transcribe($localPath);
                    if (filled($transcript)) {
                        $parsedMessage['content'] = $transcript;
                    }
                } catch (\Throwable $e) {
                    logger()->warning('WhatsApp voice transcription failed', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        // Check user authorization
        $userId = (string) ($parsedMessage['user_id'] ?? $parsedMessage['sender_id'] ?? '');
        if (! $this->security->isUserAllowed($userId, 'whatsapp')) {
            logger()->warning('Unauthorized WhatsApp user attempted access', [
                'user_id' => $userId,
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

            // Send response back to WhatsApp
            $this->gateway->sendMessage($conversation, $response);
        } catch (\Throwable $e) {
            // Send error message to user
            $this->gateway->sendMessage(
                $conversation,
                'Sorry, I encountered an error processing your message. Please try again.'
            );

            // Log the error
            logger()->error('WhatsApp webhook error', [
                'error' => $e->getMessage(),
                'conversation_id' => $conversation->id,
            ]);
        }

        return response()->json(['status' => 'ok']);
    }
}
