<?php

namespace App\Jobs;

use App\Models\Conversation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendMessageJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 5;

    public int $timeout = 30;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $conversationId,
        public string $message,
        public string $gateway
    ) {
        $this->onQueue(config('laraclaw.queues.queue_name', 'laraclaw'));
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $conversation = Conversation::find($this->conversationId);

        if (! $conversation) {
            Log::error('SendMessageJob: Conversation not found', [
                'conversation_id' => $this->conversationId,
            ]);

            return;
        }

        try {
            $gateway = match ($this->gateway) {
                'telegram' => app(\App\Laraclaw\Gateways\TelegramGateway::class),
                'discord' => app(\App\Laraclaw\Gateways\DiscordGateway::class),
                'cli' => app(\App\Laraclaw\Gateways\CliGateway::class),
                default => throw new \RuntimeException("Unknown gateway: {$this->gateway}"),
            };

            $gateway->sendMessage($conversation, $this->message);

            Log::info('SendMessageJob: Message sent', [
                'conversation_id' => $this->conversationId,
                'gateway' => $this->gateway,
            ]);
        } catch (Throwable $e) {
            Log::error('SendMessageJob: Failed to send message', [
                'conversation_id' => $this->conversationId,
                'gateway' => $this->gateway,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            throw $e;
        }
    }
}
