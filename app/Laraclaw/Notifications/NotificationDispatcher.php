<?php

namespace App\Laraclaw\Notifications;

use App\Laraclaw\Gateways\DiscordGateway;
use App\Laraclaw\Gateways\TelegramGateway;
use App\Laraclaw\Gateways\WhatsAppGateway;
use App\Models\Conversation;
use App\Models\LaraclawNotification;

class NotificationDispatcher
{
    public function dispatch(LaraclawNotification $notification): bool
    {
        $gateway = $this->resolveGateway($notification->gateway);

        if (! $gateway) {
            return false;
        }

        $conversation = $notification->conversation;

        if (! $conversation) {
            if (! $notification->channel_id) {
                return false;
            }

            $conversation = Conversation::query()->create([
                'user_id' => $notification->user_id,
                'title' => 'Proactive Notification',
                'gateway' => $notification->gateway,
                'gateway_conversation_id' => $notification->channel_id,
            ]);

            $notification->update([
                'conversation_id' => $conversation->id,
            ]);
        }

        return $gateway->sendMessage($conversation, $notification->message);
    }

    protected function resolveGateway(string $gateway): TelegramGateway|DiscordGateway|WhatsAppGateway|null
    {
        return match ($gateway) {
            'telegram' => app(TelegramGateway::class),
            'discord' => app(DiscordGateway::class),
            'whatsapp' => app(WhatsAppGateway::class),
            default => null,
        };
    }
}
