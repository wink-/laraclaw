<?php

namespace App\Laraclaw\Skills;

use App\Laraclaw\Memory\MemoryManager;
use App\Laraclaw\Skills\Contracts\SkillInterface;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class MemorySkill implements SkillInterface, Tool
{
    protected ?int $userId = null;

    protected ?int $conversationId = null;

    public function __construct(
        protected MemoryManager $memoryManager
    ) {}

    public function name(): string
    {
        return 'memory';
    }

    public function description(): Stringable|string
    {
        return 'Store, recall, or forget information across conversations. Use this to remember important details about the user or retrieve previously stored memories.';
    }

    public function execute(array $parameters): string
    {
        $action = $parameters['action'] ?? 'recall';

        return match ($action) {
            'remember' => $this->remember($parameters),
            'recall' => $this->recall($parameters),
            'forget' => $this->forget($parameters),
            'list' => $this->listMemories(),
            default => "Unknown action: {$action}. Use 'remember', 'recall', 'forget', or 'list'.",
        };
    }

    protected function remember(array $parameters): string
    {
        $content = $parameters['content'] ?? null;
        $key = $parameters['key'] ?? null;

        if (! $content) {
            return 'Error: No content provided to remember.';
        }

        $fragment = $this->memoryManager->remember(
            content: $content,
            userId: $this->userId,
            conversationId: $this->conversationId,
            key: $key
        );

        if ($key) {
            return "Successfully remembered under key '{$key}': {$content}";
        }

        return "Successfully remembered: {$content}";
    }

    protected function recall(array $parameters): string
    {
        $query = $parameters['query'] ?? null;
        $key = $parameters['key'] ?? null;
        $limit = $parameters['limit'] ?? 5;

        // If searching by key
        if ($key) {
            $memories = \App\Models\MemoryFragment::query()
                ->where('key', $key)
                ->when($this->userId, fn ($q) => $q->where('user_id', $this->userId))
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();

            if ($memories->isEmpty()) {
                return "No memories found with key '{$key}'.";
            }

            return "Memories with key '{$key}':\n".
                $memories->map(fn ($m) => "- {$m->content}")->join("\n");
        }

        // If searching by query
        if ($query) {
            $memories = $this->memoryManager->getRelevantMemories(
                query: $query,
                userId: $this->userId,
                limit: $limit
            );

            if (empty($memories)) {
                return "No memories found matching '{$query}'.";
            }

            return "Memories matching '{$query}':\n".
                collect($memories)->map(fn ($m) => "- {$m->content}")->join("\n");
        }

        // Return recent memories
        $memories = \App\Models\MemoryFragment::query()
            ->when($this->userId, fn ($q) => $q->where('user_id', $this->userId))
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        if ($memories->isEmpty()) {
            return 'No memories stored yet.';
        }

        return "Recent memories:\n".
            $memories->map(fn ($m) => '- '.($m->key ? "[{$m->key}] " : '').$m->content)->join("\n");
    }

    protected function forget(array $parameters): string
    {
        $key = $parameters['key'] ?? null;

        if (! $key) {
            return 'Error: No key provided to forget.';
        }

        $deleted = $this->memoryManager->forget(
            key: $key,
            userId: $this->userId
        );

        if ($deleted > 0) {
            return "Successfully forgot {$deleted} memory(ies) with key '{$key}'.";
        }

        return "No memories found with key '{$key}'.";
    }

    protected function listMemories(): string
    {
        $memories = \App\Models\MemoryFragment::query()
            ->when($this->userId, fn ($q) => $q->where('user_id', $this->userId))
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        if ($memories->isEmpty()) {
            return 'No memories stored yet.';
        }

        $keys = $memories->whereNotNull('key')->pluck('key')->unique();

        $output = "Total memories: {$memories->count()}\n";

        if ($keys->isNotEmpty()) {
            $output .= 'Keys: '.$keys->join(', ')."\n";
        }

        $output .= "\nRecent:\n".
            $memories->take(5)->map(fn ($m) => '- '.($m->key ? "[{$m->key}] " : '').
                \Illuminate\Support\Str::limit($m->content, 50))->join("\n");

        return $output;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->enum(['remember', 'recall', 'forget', 'list'])
                ->description('The memory action to perform'),
            'content' => $schema->string()
                ->description('The content to remember (required for "remember" action)'),
            'key' => $schema->string()
                ->description('A key to tag the memory with (optional, helps organize memories)'),
            'query' => $schema->string()
                ->description('Search query to find relevant memories (for "recall" action)'),
            'limit' => $schema->integer()
                ->description('Maximum number of memories to return (default: 5)'),
        ];
    }

    public function toTool(): Tool
    {
        return $this;
    }

    public function handle(Request $request): Stringable|string
    {
        return $this->execute($request->all());
    }

    public function forUser(?int $userId): self
    {
        $this->userId = $userId;

        return $this;
    }

    public function forConversation(?int $conversationId): self
    {
        $this->conversationId = $conversationId;

        return $this;
    }
}
