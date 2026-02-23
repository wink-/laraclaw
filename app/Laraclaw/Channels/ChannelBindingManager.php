<?php

namespace App\Laraclaw\Channels;

use App\Models\ChannelBinding;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Collection;

class ChannelBindingManager
{
    /**
     * Bind a channel to a user and optionally a conversation.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public function bind(
        string $gateway,
        string $channelId,
        ?int $userId = null,
        ?int $conversationId = null,
        ?array $metadata = null
    ): ChannelBinding {
        return ChannelBinding::updateOrCreate(
            [
                'gateway' => $gateway,
                'channel_id' => $channelId,
            ],
            [
                'user_id' => $userId,
                'conversation_id' => $conversationId,
                'metadata' => $metadata,
                'active' => true,
            ]
        );
    }

    /**
     * Unbind a channel.
     */
    public function unbind(string $gateway, string $channelId): bool
    {
        return ChannelBinding::byChannel($gateway, $channelId)->delete() > 0;
    }

    /**
     * Get a specific binding by gateway and channel ID.
     */
    public function getBinding(string $gateway, string $channelId): ?ChannelBinding
    {
        return ChannelBinding::byChannel($gateway, $channelId)->first();
    }

    /**
     * List all bindings, optionally filtered.
     *
     * @return Collection<int, ChannelBinding>
     */
    public function listBindings(?string $gateway = null, ?bool $activeOnly = true): Collection
    {
        $query = ChannelBinding::query()->with(['user', 'conversation']);

        if ($gateway) {
            $query->forGateway($gateway);
        }

        if ($activeOnly) {
            $query->active();
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Activate a binding.
     */
    public function activateBinding(string $gateway, string $channelId): bool
    {
        $binding = $this->getBinding($gateway, $channelId);

        if (! $binding) {
            return false;
        }

        $binding->update(['active' => true]);

        return true;
    }

    /**
     * Deactivate a binding.
     */
    public function deactivateBinding(string $gateway, string $channelId): bool
    {
        $binding = $this->getBinding($gateway, $channelId);

        if (! $binding) {
            return false;
        }

        $binding->update(['active' => false]);

        return true;
    }

    /**
     * Get the user associated with a channel binding.
     */
    public function getUserForChannel(string $gateway, string $channelId): ?User
    {
        $binding = $this->getBinding($gateway, $channelId);

        return $binding?->user;
    }

    /**
     * Get the conversation associated with a channel binding.
     */
    public function getConversationForChannel(string $gateway, string $channelId): ?Conversation
    {
        $binding = $this->getBinding($gateway, $channelId);

        return $binding?->conversation;
    }
}
