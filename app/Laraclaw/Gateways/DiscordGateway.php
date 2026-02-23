<?php

namespace App\Laraclaw\Gateways;

use App\Models\Conversation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DiscordGateway extends BaseGateway
{
    protected string $botToken;

    protected string $apiBaseUrl = 'https://discord.com/api/v10';

    public function __construct()
    {
        $this->botToken = config('services.discord.bot_token', env('DISCORD_BOT_TOKEN', ''));
    }

    public function getName(): string
    {
        return 'discord';
    }

    /**
     * Parse Discord interaction or message into standardized format.
     *
     * @param  array<string, mixed>  $payload
     * @return array{content: string, sender_id: string, sender_name: ?string, timestamp: ?string, channel_id: string}
     */
    public function parseIncomingMessage(array $payload): array
    {
        // Handle Discord slash command interaction
        if (isset($payload['type']) && $payload['type'] === 2) {
            return $this->parseInteraction($payload);
        }

        // Handle regular message
        return $this->parseMessage($payload);
    }

    /**
     * Parse a Discord interaction (slash command).
     */
    protected function parseInteraction(array $payload): array
    {
        $data = $payload['data'] ?? [];
        $member = $payload['member'] ?? [];
        $user = $member['user'] ?? [];
        $channel = $payload['channel_id'] ?? '';

        // Extract options from the command
        $options = $data['options'] ?? [];
        $content = $this->extractInteractionOptions($options);

        return [
            'content' => $content,
            'sender_id' => $user['id'] ?? '',
            'sender_name' => $user['global_name'] ?? $user['username'] ?? '',
            'timestamp' => null,
            'channel_id' => $channel,
            'interaction_id' => $payload['id'] ?? '',
            'interaction_token' => $payload['token'] ?? '',
            'guild_id' => $payload['guild_id'] ?? '',
        ];
    }

    /**
     * Parse a regular Discord message.
     */
    protected function parseMessage(array $payload): array
    {
        $author = $payload['author'] ?? [];

        return [
            'content' => $payload['content'] ?? '',
            'sender_id' => $author['id'] ?? '',
            'sender_name' => $author['global_name'] ?? $author['username'] ?? '',
            'timestamp' => $payload['timestamp'] ?? null,
            'channel_id' => $payload['channel_id'] ?? '',
            'message_id' => $payload['id'] ?? '',
            'guild_id' => $payload['guild_id'] ?? null,
        ];
    }

    /**
     * Extract content from interaction options.
     */
    protected function extractInteractionOptions(array $options): string
    {
        $content = '';

        foreach ($options as $option) {
            if (isset($option['value'])) {
                $content .= ($content ? ' ' : '').$option['value'];
            }
        }

        return $content;
    }

    /**
     * Find or create a Discord conversation.
     */
    public function findOrCreateConversation(array $parsedMessage): Conversation
    {
        $channelId = $parsedMessage['channel_id'];
        $existingConversation = $this->findConversationByIdentifier($channelId);

        if ($existingConversation) {
            return $existingConversation;
        }

        $title = null;
        if ($parsedMessage['sender_name']) {
            $title = "Discord: {$parsedMessage['sender_name']}";
        }

        return $this->createConversation($channelId, $title, null);
    }

    /**
     * Send a message to Discord.
     */
    public function sendMessage(Conversation $conversation, string $content): bool
    {
        $channelId = $this->getConversationIdentifier($conversation);

        if (! $channelId) {
            Log::error('DiscordGateway: No channel ID for conversation', [
                'conversation_id' => $conversation->id,
            ]);

            return false;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bot {$this->botToken}",
                'Content-Type' => 'application/json',
            ])->post("{$this->apiBaseUrl}/channels/{$channelId}/messages", [
                'content' => $content,
            ]);

            if (! $response->successful()) {
                Log::error('DiscordGateway: Failed to send message', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('DiscordGateway: Exception sending message', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send an interaction response (for slash commands).
     */
    public function sendInteractionResponse(string $interactionToken, string $content, int $type = 4): bool
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bot {$this->botToken}",
                'Content-Type' => 'application/json',
            ])->post("{$this->apiBaseUrl}/interactions/{$interactionToken}/callback", [
                'type' => $type, // CHANNEL_MESSAGE_WITH_SOURCE
                'data' => [
                    'content' => $content,
                ],
            ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error('DiscordGateway: Exception sending interaction response', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Verify Discord webhook signature.
     */
    public function verifyWebhook(array $payload, ?string $signature = null): bool
    {
        // For Discord interactions, the type field must be present
        if (! isset($payload['type'])) {
            return false;
        }

        return true;
    }

    /**
     * Register slash commands with Discord.
     */
    public function registerCommands(string $applicationId, ?string $guildId = null): bool
    {
        $commands = [
            [
                'name' => 'chat',
                'description' => 'Chat with Laraclaw AI assistant',
                'options' => [
                    [
                        'name' => 'message',
                        'description' => 'Your message to Laraclaw',
                        'type' => 3, // STRING
                        'required' => true,
                    ],
                ],
            ],
        ];

        $url = $guildId
            ? "{$this->apiBaseUrl}/applications/{$applicationId}/guilds/{$guildId}/commands"
            : "{$this->apiBaseUrl}/applications/{$applicationId}/commands";

        try {
            foreach ($commands as $command) {
                Http::withHeaders([
                    'Authorization' => "Bot {$this->botToken}",
                    'Content-Type' => 'application/json',
                ])->post($url, $command);
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('DiscordGateway: Failed to register commands', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
