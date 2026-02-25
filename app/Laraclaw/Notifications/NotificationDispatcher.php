<?php

namespace App\Laraclaw\Notifications;

use App\Laraclaw\Gateways\DiscordGateway;
use App\Laraclaw\Gateways\TelegramGateway;
use App\Laraclaw\Gateways\WhatsAppGateway;
use App\Models\ChannelBinding;
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

        $channelId = $notification->channel_id;

        if (! $channelId && $notification->user_id) {
            $channelId = ChannelBinding::query()
                ->where('gateway', $notification->gateway)
                ->where('user_id', $notification->user_id)
                ->where('active', true)
                ->latest('id')
                ->value('channel_id');
        }

        if (! $conversation) {
            if (! $channelId) {
                return false;
            }

            $conversation = Conversation::query()->create([
                'user_id' => $notification->user_id,
                'title' => 'Proactive Notification',
                'gateway' => $notification->gateway,
                'gateway_conversation_id' => $channelId,
            ]);

            $notification->update([
                'conversation_id' => $conversation->id,
                'channel_id' => $channelId,
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
