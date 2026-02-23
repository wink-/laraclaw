<?php

namespace App\Laraclaw\Gateways;

use App\Laraclaw\Gateways\Contracts\GatewayInterface;
use App\Models\Conversation;
use App\Models\User;

abstract class BaseGateway implements GatewayInterface
{
    /**
     * Find an existing conversation by gateway identifier.
     */
    protected function findConversationByIdentifier(string $identifier): ?Conversation
    {
        return Conversation::query()
            ->where('gateway', $this->getName())
            ->where('gateway_conversation_id', $identifier)
            ->first();
    }

    /**
     * Create a new conversation for this gateway.
     */
    protected function createConversation(string $identifier, ?string $title = null, ?int $userId = null): Conversation
    {
        return Conversation::create([
            'gateway' => $this->getName(),
            'gateway_conversation_id' => $identifier,
            'title' => $title,
            'user_id' => $userId,
        ]);
    }

    /**
     * Find or create a user from gateway-specific data.
     * Override in subclasses for specific gateway user handling.
     */
    protected function findOrCreateUser(string $senderId, ?string $senderName = null): ?User
    {
        // By default, don't create users - return null
        // Subclasses can override this to link gateway users to app users
        return null;
    }

    /**
     * Get the gateway-specific conversation identifier.
     */
    public function getConversationIdentifier(Conversation $conversation): ?string
    {
        return $conversation->gateway_conversation_id;
    }

    /**
     * Default webhook verification - always returns true.
     * Override in subclasses for signature verification.
     */
    public function verifyWebhook(array $payload, ?string $signature = null): bool
    {
        return true;
    }
}
