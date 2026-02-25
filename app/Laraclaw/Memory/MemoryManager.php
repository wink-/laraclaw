<?php

namespace App\Laraclaw\Memory;

use App\Models\Conversation;
use App\Models\MemoryFragment;
use App\Models\Message;
use Illuminate\Support\Facades\DB;

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
     * Build conversation context using a token budget and summary fallback.
     *
     * @return array<int, array{role: string, content: string}>
     */
    public function getConversationContextWithBudget(Conversation $conversation): array
    {
        $historyLimit = (int) config('laraclaw.context.history_limit', 50);
        $budget = (int) config('laraclaw.context.budget_tokens', 3000);
        $summaryEnabled = (bool) config('laraclaw.context.summary_enabled', true);

        $messages = $this->getConversationHistory($conversation, $historyLimit);
        $totalTokens = 0;
        $selected = [];

        for ($index = count($messages) - 1; $index >= 0; $index--) {
            $message = $messages[$index];
            $messageTokens = (int) max(1, ceil(mb_strlen($message['content']) / 4));

            if (($totalTokens + $messageTokens) > $budget) {
                break;
            }

            $selected[] = $message;
            $totalTokens += $messageTokens;
        }

        $selected = array_reverse($selected);

        if (! $summaryEnabled || count($selected) === count($messages)) {
            return $selected;
        }

        $olderMessages = array_slice($messages, 0, max(0, count($messages) - count($selected)));
        if (empty($olderMessages)) {
            return $selected;
        }

        $summary = $this->summarizeMessages($olderMessages);

        return array_merge([
            [
                'role' => 'system',
                'content' => $summary,
            ],
        ], $selected);
    }

    /**
     * Get relevant memories for a given query using FTS5 full-text search.
     *
     * @return array<int, MemoryFragment>
     */
    public function getRelevantMemories(string $query, ?int $userId = null, int $limit = 10, ?string $category = null): array
    {
        $driver = DB::connection()->getDriverName();

        // Use FTS5 for SQLite, fallback to LIKE for other databases
        if ($driver === 'sqlite' && $this->ftsTableExists()) {
            $results = $this->searchWithFts($query, $userId, $limit * 2, $category);

            return $this->rerankMemories($results, $query, $limit);
        }

        $results = $this->searchWithLike($query, $userId, $limit * 2, $category);

        return $this->rerankMemories($results, $query, $limit);
    }

    /**
     * @param  array<int, MemoryFragment>  $memories
     * @return array<int, MemoryFragment>
     */
    protected function rerankMemories(array $memories, string $query, int $limit): array
    {
        if (! config('laraclaw.context.rerank_enabled', true)) {
            return array_slice($memories, 0, $limit);
        }

        $terms = collect(preg_split('/\s+/u', mb_strtolower(trim($query))) ?: [])
            ->filter(fn (string $term) => $term !== '')
            ->values();

        if ($terms->isEmpty()) {
            return array_slice($memories, 0, $limit);
        }

        $scored = collect($memories)->map(function (MemoryFragment $memory) use ($terms) {
            $content = mb_strtolower($memory->content);
            $score = $terms->sum(fn (string $term) => str_contains($content, $term) ? 1 : 0);

            return [
                'memory' => $memory,
                'score' => $score,
                'created_at' => $memory->created_at,
            ];
        })->sortByDesc(fn (array $row) => [$row['score'], $row['created_at']]);

        return $scored
            ->take($limit)
            ->pluck('memory')
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    protected function summarizeMessages(array $messages): string
    {
        $lines = collect($messages)
            ->take(12)
            ->map(function (array $message): string {
                $content = trim($message['content']);
                $content = mb_strlen($content) > 120 ? mb_substr($content, 0, 117).'...' : $content;

                return strtoupper($message['role']).": {$content}";
            })
            ->implode("\n- ");

        return "Summary of earlier context:\n- {$lines}";
    }

    /**
     * Search memories using FTS5 full-text search.
     *
     * @return array<int, MemoryFragment>
     */
    protected function searchWithFts(string $query, ?int $userId, int $limit, ?string $category = null): array
    {
        // Prepare query for FTS5 - escape special characters and add prefix matching
        $ftsQuery = $this->prepareFtsQuery($query);

        if ($ftsQuery === '') {
            return [];
        }

        $sql = '
            SELECT mf.*, bm25(memory_fragments_fts) as relevance
            FROM memory_fragments_fts
            JOIN memory_fragments mf ON mf.id = memory_fragments_fts.rowid
            WHERE memory_fragments_fts MATCH ?
        ';

        $bindings = [$ftsQuery];

        if ($userId) {
            $sql .= ' AND mf.user_id = ?';
            $bindings[] = $userId;
        }

        if ($category) {
            $sql .= ' AND mf.category = ?';
            $bindings[] = $category;
        }

        $sql .= ' ORDER BY relevance ASC LIMIT ?';
        $bindings[] = $limit;

        $results = DB::select($sql, $bindings);

        return collect($results)->map(function ($row) {
            $fragment = MemoryFragment::find($row->id);
            $fragment->relevance = $row->relevance;

            return $fragment;
        })->filter()->all();
    }

    /**
     * Search memories using LIKE for non-SQLite databases.
     *
     * @return array<int, MemoryFragment>
     */
    protected function searchWithLike(string $query, ?int $userId, int $limit, ?string $category = null): array
    {
        $queryBuilder = MemoryFragment::query()
            ->where('content', 'LIKE', '%'.$query.'%')
            ->orderBy('created_at', 'desc')
            ->limit($limit);

        if ($userId) {
            $queryBuilder->where('user_id', $userId);
        }

        if ($category) {
            $queryBuilder->where('category', $category);
        }

        return $queryBuilder->get()->all();
    }

    /**
     * Prepare a query string for FTS5.
     */
    protected function prepareFtsQuery(string $query): string
    {
        $words = preg_split('/\s+/u', trim($query)) ?: [];

        $sanitizedWords = array_values(array_filter(array_map(function (string $word): string {
            return preg_replace('/[^\p{L}\p{N}_]+/u', '', $word) ?? '';
        }, $words)));

        $ftsTerms = array_map(fn (string $word): string => $word.'*', $sanitizedWords);

        return implode(' ', $ftsTerms);
    }

    /**
     * Check if FTS table exists.
     */
    protected function ftsTableExists(): bool
    {
        try {
            DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name='memory_fragments_fts'");

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Store a memory fragment.
     */
    public function remember(
        string $content,
        ?int $userId = null,
        ?int $conversationId = null,
        ?string $key = null,
        ?string $category = null,
        ?array $metadata = null,
    ): MemoryFragment {
        return MemoryFragment::create([
            'user_id' => $userId,
            'conversation_id' => $conversationId,
            'key' => $key,
            'category' => $category,
            'content' => $content,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Get recent memories for a user, optionally filtered by category.
     *
     * @return array<int, MemoryFragment>
     */
    public function getRecentMemories(?int $userId = null, int $limit = 10, ?string $category = null): array
    {
        return MemoryFragment::query()
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->when($category, fn ($query) => $query->where('category', $category))
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function listCategories(?int $userId = null): array
    {
        return MemoryFragment::query()
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->all();
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
