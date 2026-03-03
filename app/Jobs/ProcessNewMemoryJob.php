<?php

namespace App\Jobs;

use App\Laraclaw\Gateways\DiscordGateway;
use App\Laraclaw\Gateways\SlackGateway;
use App\Laraclaw\Gateways\TelegramGateway;
use App\Laraclaw\Gateways\WhatsAppGateway;
use App\Laraclaw\Memory\MemoryStore;
use App\Models\Conversation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessNewMemoryJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    /**
     * @param  array<string, mixed>  $parsedMessage
     */
    public function __construct(
        public int $conversationId,
        public string $platform,
        public string $content,
        public array $parsedMessage = [],
    ) {
        $this->onQueue(config('laraclaw.queues.queue_name', 'laraclaw'));
    }

    public function handle(MemoryStore $memoryStore): void
    {
        $conversation = Conversation::query()->find($this->conversationId);

        if (! $conversation) {
            return;
        }

        $metadata = $memoryStore->extractMetadata($this->content, $this->platform);

        $memoryStore->capture(
            platform: $this->platform,
            content: $this->content,
            userId: $conversation->user_id,
            conversationId: $conversation->id,
            metadata: array_merge($metadata, [
                'source_message' => [
                    'sender_id' => $this->parsedMessage['sender_id'] ?? null,
                    'timestamp' => $this->parsedMessage['timestamp'] ?? null,
                ],
            ]),
        );

        $topics = implode(', ', array_slice($metadata['topics'] ?? [], 0, 3));
        $suffix = $topics !== '' ? " Tags: {$topics}." : '';

        $this->resolveGateway($this->platform)?->sendMessage(
            $conversation,
            "✅ Saved and embedded.{$suffix}"
        );
    }

    protected function resolveGateway(string $platform): TelegramGateway|DiscordGateway|WhatsAppGateway|SlackGateway|null
    {
        return match ($platform) {
            'telegram' => app(TelegramGateway::class),
            'discord' => app(DiscordGateway::class),
            'whatsapp' => app(WhatsAppGateway::class),
            'slack' => app(SlackGateway::class),
            default => null,
        };
    }
}
