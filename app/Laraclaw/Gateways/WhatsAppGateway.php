<?php

namespace App\Laraclaw\Gateways;

use App\Models\Conversation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppGateway extends BaseGateway
{
    protected string $token;

    protected string $phoneNumberId;

    protected string $apiBaseUrl;

    public function __construct()
    {
        $this->token = config('laraclaw.gateways.whatsapp.token', env('WHATSAPP_TOKEN', ''));
        $this->phoneNumberId = config('laraclaw.gateways.whatsapp.phone_number_id', env('WHATSAPP_PHONE_NUMBER_ID', ''));
        $version = config('laraclaw.gateways.whatsapp.api_version', 'v18.0');
        $this->apiBaseUrl = "https://graph.facebook.com/{$version}/{$this->phoneNumberId}";
    }

    public function getName(): string
    {
        return 'whatsapp';
    }

    /**
     * Parse WhatsApp webhook update into standardized format.
     */
    public function parseIncomingMessage(array $payload): array
    {
        $entry = $payload['entry'][0] ?? [];
        $changes = $entry['changes'][0] ?? [];
        $value = $changes['value'] ?? [];
        $messages = $value['messages'] ?? [];
        $contacts = $value['contacts'] ?? [];

        if (empty($messages)) {
            return [
                'content' => '',
                'sender_id' => '',
                'sender_name' => null,
                'timestamp' => null,
                'chat_id' => '',
            ];
        }

        $message = $messages[0];
        $contact = $contacts[0] ?? [];

        $content = $this->extractMessageContent($message);

        return [
            'content' => $content,
            'sender_id' => (string) ($message['from'] ?? ''),
            'sender_name' => $contact['profile']['name'] ?? null,
            'timestamp' => $message['timestamp'] ?? null,
            'chat_id' => (string) ($message['from'] ?? ''), // In WhatsApp, chat_id is usually the sender's phone number
            'message_id' => (string) ($message['id'] ?? ''),
            'type' => $message['type'] ?? 'unknown',
            'audio_media_id' => $message['audio']['id'] ?? null,
            'voice_media_id' => $message['voice']['id'] ?? null,
        ];
    }

    protected function extractMessageContent(array $message): string
    {
        $type = $message['type'] ?? 'unknown';

        if ($type === 'text') {
            return $message['text']['body'] ?? '';
        }

        // Handle media types (image, video, audio, document)
        if (in_array($type, ['image', 'video', 'audio', 'document', 'voice'])) {
            $caption = $message[$type]['caption'] ?? '';

            return "[{$type}] {$caption}";
        }

        return "[{$type}]";
    }

    public function findOrCreateConversation(array $parsedMessage): Conversation
    {
        $chatId = $parsedMessage['chat_id'];
        $existingConversation = $this->findConversationByIdentifier($chatId);

        if ($existingConversation) {
            return $existingConversation;
        }

        $title = null;
        if ($parsedMessage['sender_name']) {
            $title = "WhatsApp: {$parsedMessage['sender_name']}";
        }

        return $this->createConversation($chatId, $title, null);
    }

    public function sendMessage(Conversation $conversation, string $content): bool
    {
        $chatId = $this->getConversationIdentifier($conversation);

        if (! $chatId) {
            Log::error('WhatsAppGateway: No chat ID for conversation', [
                'conversation_id' => $conversation->id,
            ]);

            return false;
        }

        try {
            $response = Http::withToken($this->token)
                ->post("{$this->apiBaseUrl}/messages", [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $chatId,
                    'type' => 'text',
                    'text' => [
                        'preview_url' => false,
                        'body' => $content,
                    ],
                ]);

            if (! $response->successful()) {
                Log::error('WhatsAppGateway: Failed to send message', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('WhatsAppGateway: Exception sending message', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function sendAudioMessage(Conversation $conversation, string $audioPath): bool
    {
        $chatId = $this->getConversationIdentifier($conversation);

        if (! $chatId || ! file_exists($audioPath)) {
            Log::error('WhatsAppGateway: Invalid audio message payload', [
                'conversation_id' => $conversation->id,
                'audio_path' => $audioPath,
            ]);

            return false;
        }

        $mediaId = $this->uploadAudioMedia($audioPath);
        if (! $mediaId) {
            return false;
        }

        try {
            $response = Http::withToken($this->token)
                ->post("{$this->apiBaseUrl}/messages", [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $chatId,
                    'type' => 'audio',
                    'audio' => [
                        'id' => $mediaId,
                    ],
                ]);

            if (! $response->successful()) {
                Log::error('WhatsAppGateway: Failed to send audio message', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('WhatsAppGateway: Exception sending audio message', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function verifyWebhook(array $payload, ?string $signature = null): bool
    {
        // WhatsApp uses a verify_token for the initial setup (GET request)
        // and X-Hub-Signature-256 for POST requests.
        // This method is typically called for POST requests.

        $appSecret = config('laraclaw.gateways.whatsapp.app_secret', env('WHATSAPP_APP_SECRET'));

        if (empty($appSecret) || empty($signature)) {
            return true; // Skip verification if not configured
        }

        // Signature format: sha256=hash
        if (! str_starts_with($signature, 'sha256=')) {
            return false;
        }

        $expectedHash = hash_hmac('sha256', json_encode($payload), $appSecret);
        $providedHash = substr($signature, 7);

        return hash_equals($expectedHash, $providedHash);
    }

    public function downloadMedia(string $mediaId): ?string
    {
        try {
            $mediaResponse = Http::withToken($this->token)
                ->get("https://graph.facebook.com/{$mediaId}");

            if (! $mediaResponse->successful()) {
                return null;
            }

            $downloadUrl = $mediaResponse->json('url');
            if (! $downloadUrl) {
                return null;
            }

            $downloadResponse = Http::withToken($this->token)->get($downloadUrl);
            if (! $downloadResponse->successful()) {
                return null;
            }

            $mimeType = $mediaResponse->json('mime_type', 'audio/ogg');
            $extension = str_contains($mimeType, 'mpeg') ? 'mp3' : 'ogg';
            $localPath = storage_path('app/private/laraclaw/voice/whatsapp-'.uniqid().'.'.$extension);
            $directory = dirname($localPath);

            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            file_put_contents($localPath, $downloadResponse->body());

            return $localPath;
        } catch (\Throwable $e) {
            Log::error('WhatsAppGateway: Failed to download media', [
                'media_id' => $mediaId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected function uploadAudioMedia(string $audioPath): ?string
    {
        try {
            $response = Http::withToken($this->token)
                ->attach('file', fopen($audioPath, 'r'), basename($audioPath))
                ->post("{$this->apiBaseUrl}/media", [
                    'messaging_product' => 'whatsapp',
                    'type' => 'audio/mpeg',
                ]);

            if (! $response->successful()) {
                Log::error('WhatsAppGateway: Failed to upload audio media', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            return $response->json('id');
        } catch (\Throwable $e) {
            Log::error('WhatsAppGateway: Exception uploading audio media', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
