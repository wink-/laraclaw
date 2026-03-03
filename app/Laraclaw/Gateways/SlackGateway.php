<?php

namespace App\Laraclaw\Gateways;

use App\Models\Conversation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SlackGateway extends BaseGateway
{
    protected string $botToken;

    protected string $apiBaseUrl = 'https://slack.com/api';

    public function __construct()
    {
        $this->botToken = (string) config('services.slack.bot_token', config('services.slack.notifications.bot_user_oauth_token', ''));
    }

    public function getName(): string
    {
        return 'slack';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{content: string, sender_id: string, sender_name: ?string, timestamp: ?string, channel_id: string}
     */
    public function parseIncomingMessage(array $payload): array
    {
        $event = $payload['event'] ?? [];

        if (($event['subtype'] ?? null) === 'bot_message') {
            return [
                'content' => '',
                'sender_id' => '',
                'sender_name' => null,
                'timestamp' => null,
                'channel_id' => '',
            ];
        }

        return [
            'content' => (string) ($event['text'] ?? ''),
            'sender_id' => (string) ($event['user'] ?? ''),
            'sender_name' => null,
            'timestamp' => (string) ($event['ts'] ?? ''),
            'channel_id' => (string) ($event['channel'] ?? ''),
            'thread_ts' => $event['thread_ts'] ?? $event['ts'] ?? null,
            'event_ts' => (string) ($event['event_ts'] ?? ''),
        ];
    }

    public function findOrCreateConversation(array $parsedMessage): Conversation
    {
        $channelId = (string) ($parsedMessage['channel_id'] ?? '');
        $threadTs = (string) ($parsedMessage['thread_ts'] ?? '');

        $identifier = $threadTs !== '' ? $channelId.':'.$threadTs : $channelId;

        $existingConversation = $this->findConversationByIdentifier($identifier);

        if ($existingConversation) {
            return $existingConversation;
        }

        $title = $threadTs !== '' ? "Slack Thread {$channelId}" : "Slack {$channelId}";

        return $this->createConversation($identifier, $title, null);
    }

    public function sendMessage(Conversation $conversation, string $content): bool
    {
        $identifier = $this->getConversationIdentifier($conversation);

        if (! $identifier) {
            return false;
        }

        [$channelId, $threadTs] = $this->splitIdentifier($identifier);

        try {
            $payload = [
                'channel' => $channelId,
                'text' => $content,
            ];

            if ($threadTs) {
                $payload['thread_ts'] = $threadTs;
            }

            $response = Http::withToken($this->botToken)
                ->post("{$this->apiBaseUrl}/chat.postMessage", $payload);

            if (! $response->successful() || ! $response->json('ok')) {
                Log::error('SlackGateway: Failed to send message', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('SlackGateway: Exception sending message', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    protected function splitIdentifier(string $identifier): array
    {
        if (! str_contains($identifier, ':')) {
            return [$identifier, null];
        }

        [$channel, $thread] = explode(':', $identifier, 2);

        return [$channel, $thread ?: null];
    }
}
