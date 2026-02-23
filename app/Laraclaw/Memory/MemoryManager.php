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
     * Get relevant memories for a given query using FTS5 full-text search.
     *
     * @return array<int, MemoryFragment>
     */
    public function getRelevantMemories(string $query, ?int $userId = null, int $limit = 10): array
    {
        $driver = DB::connection()->getDriverName();

        // Use FTS5 for SQLite, fallback to LIKE for other databases
        if ($driver === 'sqlite' && $this->ftsTableExists()) {
            return $this->searchWithFts($query, $userId, $limit);
        }

        return $this->searchWithLike($query, $userId, $limit);
    }

    /**
     * Search memories using FTS5 full-text search.
     *
     * @return array<int, MemoryFragment>
     */
    protected function searchWithFts(string $query, ?int $userId, int $limit): array
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
    protected function searchWithLike(string $query, ?int $userId, int $limit): array
    {
        $queryBuilder = MemoryFragment::query()
            ->where('content', 'LIKE', '%'.$query.'%')
            ->orderBy('created_at', 'desc')
            ->limit($limit);

        if ($userId) {
            $queryBuilder->where('user_id', $userId);
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
