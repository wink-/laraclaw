<?php

namespace App\Http\Controllers;

use App\Laraclaw\Facades\Laraclaw;
use App\Laraclaw\Gateways\TelegramGateway;
use App\Laraclaw\Security\SecurityManager;
use App\Laraclaw\Voice\VoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramWebhookController extends Controller
{
    public function __construct(
        protected TelegramGateway $gateway,
        protected SecurityManager $security,
        protected VoiceService $voice,
    ) {}

    /**
     * Handle incoming Telegram webhook.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->all();
        $replyWithVoice = (bool) config('laraclaw.voice.reply_with_voice_for_voice_notes', true);

        // Verify the webhook secret token
        $secretToken = $request->header('X-Telegram-Bot-Api-Secret-Token');
        $configuredSecret = config('services.telegram.secret_token');

        if (! $this->security->verifyTelegramWebhook($configuredSecret, $secretToken)) {
            return response()->json(['error' => 'Invalid webhook'], 403);
        }

        // Parse the incoming message
        $parsedMessage = $this->gateway->parseIncomingMessage($payload);

        $voiceFileId = $parsedMessage['voice_file_id'] ?? $parsedMessage['audio_file_id'] ?? null;
        $incomingVoice = filled($voiceFileId);
        if ($voiceFileId) {
            $localPath = $this->gateway->downloadFile($voiceFileId);
            if ($localPath) {
                try {
                    $transcript = $this->voice->transcribe($localPath);
                    if (filled($transcript)) {
                        $parsedMessage['content'] = $transcript;
                    }
                } catch (\Throwable $e) {
                    logger()->warning('Telegram voice transcription failed', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

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

            if ($replyWithVoice && $incomingVoice) {
                $audioPath = null;

                try {
                    $audioPath = $this->voice->speak($response);
                    $voiceSent = $this->gateway->sendVoiceMessage($conversation, $audioPath, $response);

                    if (! $voiceSent) {
                        $this->gateway->sendMessage($conversation, $response);
                    }
                } catch (\Throwable $e) {
                    logger()->warning('Telegram voice reply failed, falling back to text', [
                        'error' => $e->getMessage(),
                    ]);

                    $this->gateway->sendMessage($conversation, $response);
                } finally {
                    if ($audioPath && file_exists($audioPath)) {
                        @unlink($audioPath);
                    }
                }
            } else {
                $this->gateway->sendMessage($conversation, $response);
            }
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
