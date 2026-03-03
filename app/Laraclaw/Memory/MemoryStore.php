<?php

namespace App\Laraclaw\Memory;

use App\Laraclaw\Storage\VectorStoreService;
use App\Models\Memory;
use Illuminate\Support\Facades\DB;

class MemoryStore
{
    public function __construct(
        protected VectorStoreService $vectors,
    ) {}

    public function capture(
        string $platform,
        string $content,
        ?int $userId = null,
        ?int $conversationId = null,
        array $metadata = [],
    ): Memory {
        $embedding = $this->generateEmbeddingSafely($content);
        $connection = $this->connectionName();
        $driver = DB::connection($connection)->getDriverName();

        if ($driver === 'pgsql' && ! empty($embedding)) {
            $id = DB::connection($connection)->table('memories')->insertGetId([
                'conversation_id' => $conversationId,
                'user_id' => $userId,
                'platform_source' => $platform,
                'content' => $content,
                'embedding' => $this->toVectorLiteral($embedding),
                'metadata' => json_encode($metadata),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return Memory::on($connection)->findOrFail($id);
        }

        return Memory::on($connection)->create([
            'conversation_id' => $conversationId,
            'user_id' => $userId,
            'platform_source' => $platform,
            'content' => $content,
            'embedding' => empty($embedding) ? null : $embedding,
            'metadata' => $metadata,
        ]);
    }

    /**
     * @return array<int, Memory>
     */
    public function semanticSearch(string $query, int $limit = 5, ?int $userId = null): array
    {
        $connection = $this->connectionName();
        $queryEmbedding = $this->generateEmbeddingSafely($query);
        $driver = DB::connection($connection)->getDriverName();

        if ($driver === 'pgsql' && ! empty($queryEmbedding)) {
            $vector = $this->toVectorLiteral($queryEmbedding);

            try {
                $builder = Memory::on($connection)
                    ->selectRaw('memories.*, 1 - (embedding <=> ?::vector) as similarity', [$vector])
                    ->when($userId, fn ($query) => $query->where('user_id', $userId))
                    ->orderByRaw('embedding <=> ?::vector asc', [$vector])
                    ->limit($limit);

                return $builder->get()->all();
            } catch (\Throwable $e) {
                // Fall back to lexical search if vector query is not available.
            }
        }

        return Memory::on($connection)
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->where('content', 'LIKE', '%'.$query.'%')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->all();
    }

    /**
     * @return array<int, Memory>
     */
    public function listRecent(int $days = 7, int $limit = 50, ?int $userId = null): array
    {
        return Memory::on($this->connectionName())
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->where('created_at', '>=', now()->subDays($days))
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function stats(?int $userId = null, int $days = 30): array
    {
        $connection = $this->connectionName();

        $base = Memory::on($connection)
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->where('created_at', '>=', now()->subDays($days));

        $total = (clone $base)->count();

        $platformBreakdown = (clone $base)
            ->selectRaw('platform_source, count(*) as count')
            ->groupBy('platform_source')
            ->orderByDesc('count')
            ->get()
            ->map(fn (Memory $memory) => [
                'platform' => $memory->platform_source,
                'count' => (int) ($memory->count ?? 0),
            ])
            ->all();

        return [
            'total_memories' => $total,
            'date_window_days' => $days,
            'platform_breakdown' => $platformBreakdown,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function extractMetadata(string $content, string $platform): array
    {
        $words = collect(preg_split('/\s+/u', $content) ?: [])
            ->filter(fn (string $word) => mb_strlen($word) > 3)
            ->map(fn (string $word) => trim(mb_strtolower($word), ",.!?;:\"'()[]{}"))
            ->filter()
            ->values();

        $topics = $words->countBy()->sortDesc()->keys()->take(5)->values()->all();

        preg_match_all('/\b[A-Z][a-z]+\b/u', $content, $matches);
        $people = collect($matches[0] ?? [])->unique()->values()->all();

        preg_match_all('/\b(?:todo|need to|follow up|action|remind me)\b.*$/imu', $content, $actionMatches);
        $actionItems = collect($actionMatches[0] ?? [])->map(fn (string $item) => trim($item))->values()->all();

        return [
            'platform' => $platform,
            'topics' => $topics,
            'people' => $people,
            'action_items' => $actionItems,
        ];
    }

    protected function connectionName(): string
    {
        return (new Memory)->getConnectionName() ?? config('database.default');
    }

    /**
     * @return array<int, float>
     */
    protected function generateEmbeddingSafely(string $text): array
    {
        try {
            $embedding = $this->vectors->generateEmbeddings($text);

            return collect($embedding)
                ->map(fn ($value) => (float) $value)
                ->values()
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @param  array<int, float>  $embedding
     */
    protected function toVectorLiteral(array $embedding): string
    {
        return '['.collect($embedding)
            ->map(fn (float $value) => number_format($value, 8, '.', ''))
            ->implode(',').']';
    }
}
