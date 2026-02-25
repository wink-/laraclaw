<?php

namespace App\Jobs;

use App\Laraclaw\Memory\MemoryManager;
use App\Models\Conversation;
use App\Models\MemoryFragment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

class ExtractMemoriesJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 30;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $conversationId,
        public string $userMessage,
        public string $assistantResponse,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(MemoryManager $memoryManager): void
    {
        if (! config('laraclaw.memory.auto_extract', true)) {
            return;
        }

        $conversation = Conversation::query()->find($this->conversationId);

        if (! $conversation) {
            return;
        }

        $candidates = $this->extractCandidates($this->userMessage, $this->assistantResponse);

        foreach ($candidates as $candidate) {
            $content = trim($candidate['content']);

            if ($content === '') {
                continue;
            }

            $alreadyStored = MemoryFragment::query()
                ->where('user_id', $conversation->user_id)
                ->where('content', $content)
                ->exists();

            if ($alreadyStored) {
                continue;
            }

            $memoryManager->remember(
                content: $content,
                userId: $conversation->user_id,
                conversationId: $conversation->id,
                key: $candidate['key'],
                category: $candidate['category'],
                metadata: [
                    'source' => 'auto_extract',
                    'assistant_response' => Str::limit($this->assistantResponse, 280),
                ],
            );
        }
    }

    /**
     * @return array<int, array{content: string, key: ?string, category: ?string}>
     */
    protected function extractCandidates(string $userMessage, string $assistantResponse): array
    {
        $message = trim($userMessage);
        $normalized = mb_strtolower($message);
        $candidates = [];

        if (preg_match('/(?:remind me to|remember to|don\'t forget to)\s+(.+)/iu', $message, $matches) === 1) {
            $task = $this->cleanMemoryValue($matches[1]);

            if ($task !== '') {
                $candidates[] = [
                    'content' => "The user wants to {$task}.",
                    'key' => 'reminder_'.Str::slug(Str::limit($task, 40, '')),
                    'category' => $this->detectCategory($task),
                ];
            }
        }

        if (preg_match('/(?:my favorite\s+.+?\s+is|i like|i love|i prefer)\s+(.+)/iu', $message, $matches) === 1) {
            $preference = $this->cleanMemoryValue($matches[1]);

            if ($preference !== '') {
                $candidates[] = [
                    'content' => "The user preference is: {$preference}.",
                    'key' => 'preference_'.Str::slug(Str::limit($preference, 36, '')),
                    'category' => 'personal',
                ];
            }
        }

        if (str_contains($normalized, 'watch ') && preg_match('/watch\s+(.+)/iu', $message, $matches) === 1) {
            $title = $this->cleanMemoryValue($matches[1]);

            if ($title !== '') {
                $candidates[] = [
                    'content' => "The user wants to watch {$title}.",
                    'key' => 'watch_'.Str::slug(Str::limit($title, 40, '')),
                    'category' => 'entertainment',
                ];
            }
        }

        return collect($candidates)
            ->unique('content')
            ->values()
            ->all();
    }

    protected function cleanMemoryValue(string $value): string
    {
        return trim((string) preg_replace('/[\s\.!?,;:]+$/u', '', $value));
    }

    protected function detectCategory(string $value): string
    {
        $candidate = mb_strtolower($value);

        return match (true) {
            str_contains($candidate, 'watch'),
            str_contains($candidate, 'movie'),
            str_contains($candidate, 'series'),
            str_contains($candidate, 'show') => 'entertainment',
            str_contains($candidate, 'buy'),
            str_contains($candidate, 'shop'),
            str_contains($candidate, 'grocery') => 'shopping',
            str_contains($candidate, 'calendar'),
            str_contains($candidate, 'appointment'),
            str_contains($candidate, 'tomorrow') => 'scheduling',
            default => 'personal',
        };
    }
}
