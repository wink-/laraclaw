<?php

namespace App\Laraclaw\Skills;

use App\Laraclaw\Memory\MemoryManager;
use App\Laraclaw\Skills\Contracts\SkillInterface;
use App\Models\MemoryFragment;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ShoppingListSkill implements SkillInterface, Tool
{
    protected ?int $userId = null;

    protected ?int $conversationId = null;

    public function __construct(
        protected MemoryManager $memoryManager
    ) {}

    public function name(): string
    {
        return 'shopping_list';
    }

    public function description(): Stringable|string
    {
        return 'Manage shopping lists by adding, viewing, removing, and clearing items.';
    }

    public function execute(array $parameters): string
    {
        $action = $parameters['action'] ?? 'view';

        return match ($action) {
            'add' => $this->addItem($parameters),
            'view' => $this->viewList($parameters),
            'remove' => $this->removeItem($parameters),
            'clear' => $this->clearList($parameters),
            default => "Unknown action: {$action}. Use 'add', 'view', 'remove', or 'clear'.",
        };
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->enum(['add', 'view', 'remove', 'clear'])
                ->description('Action to perform on shopping list'),
            'list_name' => $schema->string()
                ->description('Shopping list name (default: groceries)'),
            'item' => $schema->string()
                ->description('Item to add or remove from the list'),
            'quantity' => $schema->string()
                ->description('Optional quantity (e.g. 2, 1kg, 3 packs)'),
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

    protected function addItem(array $parameters): string
    {
        $item = trim((string) ($parameters['item'] ?? ''));

        if ($item === '') {
            return 'Error: item is required for add action.';
        }

        $listName = $this->resolveListName($parameters);
        $quantity = trim((string) ($parameters['quantity'] ?? ''));

        $alreadyExists = MemoryFragment::query()
            ->where('category', 'shopping')
            ->where('key', $listName)
            ->when($this->userId, fn ($query) => $query->where('user_id', $this->userId))
            ->whereRaw('LOWER(content) = ?', [mb_strtolower($item)])
            ->exists();

        if ($alreadyExists) {
            return "'{$item}' is already in '{$listName}'.";
        }

        $this->memoryManager->remember(
            content: $item,
            userId: $this->userId,
            conversationId: $this->conversationId,
            key: $listName,
            category: 'shopping',
            metadata: [
                'list_name' => $listName,
                'quantity' => $quantity !== '' ? $quantity : null,
            ],
        );

        $suffix = $quantity !== '' ? " ({$quantity})" : '';

        return "Added '{$item}{$suffix}' to '{$listName}'.";
    }

    protected function viewList(array $parameters): string
    {
        $listName = $this->resolveListName($parameters);

        $items = MemoryFragment::query()
            ->where('category', 'shopping')
            ->where('key', $listName)
            ->when($this->userId, fn ($query) => $query->where('user_id', $this->userId))
            ->orderBy('created_at', 'asc')
            ->get();

        if ($items->isEmpty()) {
            return "'{$listName}' is empty.";
        }

        $lines = $items->map(function (MemoryFragment $item, int $index): string {
            $quantity = data_get($item->metadata, 'quantity');
            $quantityText = $quantity ? " ({$quantity})" : '';

            return sprintf('%d. %s%s', $index + 1, $item->content, $quantityText);
        })->implode("\n");

        return "Shopping list '{$listName}':\n{$lines}";
    }

    protected function removeItem(array $parameters): string
    {
        $item = trim((string) ($parameters['item'] ?? ''));

        if ($item === '') {
            return 'Error: item is required for remove action.';
        }

        $listName = $this->resolveListName($parameters);

        $deleted = MemoryFragment::query()
            ->where('category', 'shopping')
            ->where('key', $listName)
            ->when($this->userId, fn ($query) => $query->where('user_id', $this->userId))
            ->whereRaw('LOWER(content) = ?', [mb_strtolower($item)])
            ->delete();

        if ($deleted === 0) {
            return "'{$item}' is not in '{$listName}'.";
        }

        return "Removed '{$item}' from '{$listName}'.";
    }

    protected function clearList(array $parameters): string
    {
        $listName = $this->resolveListName($parameters);

        $deleted = MemoryFragment::query()
            ->where('category', 'shopping')
            ->where('key', $listName)
            ->when($this->userId, fn ($query) => $query->where('user_id', $this->userId))
            ->delete();

        return "Cleared '{$listName}' ({$deleted} item(s) removed).";
    }

    protected function resolveListName(array $parameters): string
    {
        $name = trim((string) ($parameters['list_name'] ?? 'groceries'));

        return $name !== '' ? mb_strtolower($name) : 'groceries';
    }
}
