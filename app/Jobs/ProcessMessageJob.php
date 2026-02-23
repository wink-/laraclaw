<?php

namespace App\Jobs;

use App\Events\MessageProcessed;
use App\Events\MessageProcessingFailed;
use App\Laraclaw\Facades\Laraclaw;
use App\Models\Conversation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessMessageJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 10;

    public int $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $conversationId,
        public string $message,
        public string $gateway,
        public array $metadata = []
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
            Log::error('ProcessMessageJob: Conversation not found', [
                'conversation_id' => $this->conversationId,
            ]);

            return;
        }

        try {
            $response = Laraclaw::chat($conversation, $this->message);

            MessageProcessed::dispatch(
                $conversation,
                $response,
                $this->gateway
            );
        } catch (Throwable $e) {
            Log::error('ProcessMessageJob: Processing failed', [
                'conversation_id' => $this->conversationId,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            MessageProcessingFailed::dispatch(
                $conversation,
                $e->getMessage(),
                $this->attempts()
            );

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('ProcessMessageJob: Job failed permanently', [
            'conversation_id' => $this->conversationId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
