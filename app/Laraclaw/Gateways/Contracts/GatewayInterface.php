<?php

namespace App\Laraclaw\Gateways\Contracts;

use App\Models\Conversation;
use App\Models\Message;

interface GatewayInterface
{
    /**
     * Get the gateway name identifier.
     */
    public function getName(): string;

    /**
     * Parse an incoming webhook payload into a standardized format.
     *
     * @param  array<string, mixed>  $payload
     * @return array{content: string, sender_id: string, sender_name: ?string, timestamp: ?string}
     */
    public function parseIncomingMessage(array $payload): array;

    /**
     * Find or create a conversation from an incoming message.
     */
    public function findOrCreateConversation(array $parsedMessage): Conversation;

    /**
     * Send a response message through this gateway.
     */
    public function sendMessage(Conversation $conversation, string $content): bool;

    /**
     * Verify the webhook is valid (for security).
     *
     * @param  array<string, mixed>  $payload
     */
    public function verifyWebhook(array $payload, ?string $signature = null): bool;

    /**
     * Get the gateway-specific conversation identifier from a conversation model.
     */
    public function getConversationIdentifier(Conversation $conversation): ?string;
}
