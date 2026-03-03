<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\McpSemanticSearchRequest;
use App\Laraclaw\Memory\MemoryStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class McpMemoryController extends Controller
{
    public function __construct(
        protected MemoryStore $memoryStore,
    ) {}

    public function search(McpSemanticSearchRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $results = $this->memoryStore->semanticSearch(
            query: $validated['query'],
            limit: (int) ($validated['limit'] ?? 5),
            userId: optional($request->user())->id,
        );

        return response()->json([
            'tool' => 'semantic_search',
            'query' => $validated['query'],
            'results' => collect($results)->map(fn ($memory) => [
                'id' => $memory->id,
                'content' => $memory->content,
                'platform_source' => $memory->platform_source,
                'metadata' => $memory->metadata,
                'created_at' => optional($memory->created_at)?->toIso8601String(),
                'similarity' => isset($memory->similarity) ? (float) $memory->similarity : null,
            ])->values()->all(),
        ]);
    }

    public function recent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $results = $this->memoryStore->listRecent(
            days: (int) ($validated['days'] ?? 7),
            limit: (int) ($validated['limit'] ?? 20),
            userId: optional($request->user())->id,
        );

        return response()->json([
            'tool' => 'list_recent',
            'results' => collect($results)->map(fn ($memory) => [
                'id' => $memory->id,
                'content' => $memory->content,
                'platform_source' => $memory->platform_source,
                'metadata' => $memory->metadata,
                'created_at' => optional($memory->created_at)?->toIso8601String(),
            ])->values()->all(),
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        return response()->json([
            'tool' => 'stats',
            'stats' => $this->memoryStore->stats(
                userId: optional($request->user())->id,
                days: (int) ($validated['days'] ?? 30),
            ),
        ]);
    }
}
