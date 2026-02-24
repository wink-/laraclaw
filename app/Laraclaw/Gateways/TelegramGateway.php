<?php

namespace App\Laraclaw\Gateways;

use App\Models\Conversation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramGateway extends BaseGateway
{
    protected string $botToken;

    protected string $apiBaseUrl;

    public function __construct()
    {
        $this->botToken = config('services.telegram.bot_token', env('TELEGRAM_BOT_TOKEN', ''));
        $this->apiBaseUrl = "https://api.telegram.org/bot{$this->botToken}";
    }

    public function getName(): string
    {
        return 'telegram';
    }

    /**
     * Parse Telegram webhook update into standardized format.
     *
     * @param  array<string, mixed>  $payload
     * @return array{content: string, sender_id: string, sender_name: ?string, timestamp: ?string, chat_id: string}
     */
    public function parseIncomingMessage(array $payload): array
    {
        $message = $payload['message'] ?? $payload['edited_message'] ?? [];

        $from = $message['from'] ?? [];
        $chat = $message['chat'] ?? [];

        // Handle different message types
        $content = $this->extractMessageContent($message);

        return [
            'content' => $content,
            'sender_id' => (string) ($from['id'] ?? ''),
            'sender_name' => trim(($from['first_name'] ?? '').' '.($from['last_name'] ?? '')),
            'timestamp' => $message['date'] ?? null,
            'chat_id' => (string) ($chat['id'] ?? ''),
            'username' => $from['username'] ?? null,
            'message_id' => (string) ($message['message_id'] ?? ''),
            'voice_file_id' => $message['voice']['file_id'] ?? null,
            'audio_file_id' => $message['audio']['file_id'] ?? null,
        ];
    }

    /**
     * Extract text content from various Telegram message types.
     */
    protected function extractMessageContent(array $message): string
    {
        // Regular text message
        if (isset($message['text'])) {
            return $message['text'];
        }

        // Caption from media messages
        if (isset($message['caption'])) {
            $type = $this->getMessageType($message);

            return "[{$type}] {$message['caption']}";
        }

        // Media without caption
        $type = $this->getMessageType($message);
        if ($type !== 'unknown') {
            return "[{$type}]";
        }

        return '';
    }

    /**
     * Get the message type from a Telegram message.
     */
    protected function getMessageType(array $message): string
    {
        $types = ['photo', 'video', 'audio', 'voice', 'document', 'sticker', 'gif', 'video_note', 'contact', 'location', 'venue'];

        foreach ($types as $type) {
            if (isset($message[$type])) {
                return ucfirst($type);
            }
        }

        return 'unknown';
    }

    /**
     * Find or create a Telegram conversation.
     */
    public function findOrCreateConversation(array $parsedMessage): Conversation
    {
        $chatId = $parsedMessage['chat_id'];
        $existingConversation = $this->findConversationByIdentifier($chatId);

        if ($existingConversation) {
            return $existingConversation;
        }

        $title = null;
        if ($parsedMessage['sender_name']) {
            $title = "Telegram: {$parsedMessage['sender_name']}";
        }

        return $this->createConversation($chatId, $title, null);
    }

    /**
     * Send a message to Telegram.
     */
    public function sendMessage(Conversation $conversation, string $content): bool
    {
        $chatId = $this->getConversationIdentifier($conversation);

        if (! $chatId) {
            Log::error('TelegramGateway: No chat ID for conversation', [
                'conversation_id' => $conversation->id,
            ]);

            return false;
        }

        try {
            $response = Http::post("{$this->apiBaseUrl}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $content,
                'parse_mode' => 'Markdown',
            ]);

            if (! $response->successful()) {
                Log::error('TelegramGateway: Failed to send message', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('TelegramGateway: Exception sending message', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Verify Telegram webhook using secret token.
     */
    public function verifyWebhook(array $payload, ?string $signature = null): bool
    {
        // Check for required fields
        if (! isset($payload['update_id'])) {
            return false;
        }

        // If a secret token is configured, verify it
        $secretToken = config('services.telegram.secret_token', env('TELEGRAM_SECRET_TOKEN'));
        if ($secretToken && $signature !== $secretToken) {
            return false;
        }

        return true;
    }

    /**
     * Set the webhook URL for this bot.
     */
    public function setWebhook(string $url): bool
    {
        $response = Http::post("{$this->apiBaseUrl}/setWebhook", [
            'url' => $url,
            'allowed_updates' => ['message', 'edited_message'],
        ]);

        return $response->successful();
    }

    /**
     * Delete the webhook.
     */
    public function deleteWebhook(): bool
    {
        $response = Http::post("{$this->apiBaseUrl}/deleteWebhook");

        return $response->successful();
    }

    /**
     * Get bot information.
     */
    public function getMe(): ?array
    {
        $response = Http::get("{$this->apiBaseUrl}/getMe");

        if ($response->successful()) {
            return $response->json('result');
        }

        return null;
    }

    public function downloadFile(string $fileId): ?string
    {
        try {
            $fileResponse = Http::get("{$this->apiBaseUrl}/getFile", [
                'file_id' => $fileId,
            ]);

            if (! $fileResponse->successful()) {
                return null;
            }

            $filePath = $fileResponse->json('result.file_path');
            if (! $filePath) {
                return null;
            }

            $downloadUrl = "https://api.telegram.org/file/bot{$this->botToken}/{$filePath}";
            $downloadResponse = Http::get($downloadUrl);

            if (! $downloadResponse->successful()) {
                return null;
            }

            $extension = pathinfo($filePath, PATHINFO_EXTENSION) ?: 'ogg';
            $localPath = storage_path('app/private/laraclaw/voice/telegram-'.uniqid().'.'.$extension);
            $directory = dirname($localPath);

            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            file_put_contents($localPath, $downloadResponse->body());

            return $localPath;
        } catch (\Throwable $e) {
            Log::error('TelegramGateway: Failed to download file', [
                'file_id' => $fileId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
