<?php

namespace App\Laraclaw\Memory;

use App\Models\Conversation;
use App\Models\MemoryFragment;
use App\Models\Message;

class MemoryManager
{
    /**
     * Get the conversation history formatted for the LLM.
     *
     * @return array<int, array{role: string, content: string}>
     */
    public function getConversationHistory(Conversation $conversation, int $limit = 50): array
    {
        return $conversation->messages()
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (Message $message) => [
                'role' => $message->role,
                'content' => $message->content,
            ])
            ->all();
    }

    /**
     * Get relevant memories for a given query.
     *
     * @return array<int, MemoryFragment>
     */
    public function getRelevantMemories(string $query, ?int $userId = null, int $limit = 10): array
    {
        $queryBuilder = MemoryFragment::query()
            ->orderBy('created_at', 'desc')
            ->limit($limit);

        if ($userId) {
            $queryBuilder->where('user_id', $userId);
        }

        // For now, use simple keyword matching
        // Future: use vector similarity search
        return $queryBuilder->get()->all();
    }

    /**
     * Store a memory fragment.
     */
    public function remember(string $content, ?int $userId = null, ?int $conversationId = null, ?string $key = null): MemoryFragment
    {
        return MemoryFragment::create([
            'user_id' => $userId,
            'conversation_id' => $conversationId,
            'key' => $key,
            'content' => $content,
        ]);
    }

    /**
     * Forget memories by key.
     */
    public function forget(string $key, ?int $userId = null): int
    {
        $query = MemoryFragment::query()->where('key', $key);

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->delete();
    }

    /**
     * Format memories for inclusion in the prompt.
     */
    public function formatMemoriesForPrompt(array $memories): string
    {
        if (empty($memories)) {
            return '';
        }

        $formatted = "## Relevant Memories\n\n";

        foreach ($memories as $memory) {
            $formatted .= "- {$memory->content}\n";
        }

        return $formatted."\n";
    }

    /**
     * Build the system prompt with memories.
     */
    public function buildSystemPrompt(string $basePrompt, array $memories = []): string
    {
        $memoryContext = $this->formatMemoriesForPrompt($memories);

        if ($memoryContext) {
            return $basePrompt."\n\n".$memoryContext;
        }

        return $basePrompt;
    }
}
