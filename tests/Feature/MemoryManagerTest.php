<?php

use App\Laraclaw\Memory\MemoryManager;
use App\Models\Conversation;
use App\Models\MemoryFragment;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->memoryManager = app(MemoryManager::class);
});

it('can get conversation history', function () {
    $conversation = Conversation::factory()->create();
    Message::factory()->fromUser()->for($conversation)->create(['content' => 'First']);
    Message::factory()->fromAssistant()->for($conversation)->create(['content' => 'Second']);
    Message::factory()->fromUser()->for($conversation)->create(['content' => 'Third']);

    $history = $this->memoryManager->getConversationHistory($conversation);

    expect($history)->toHaveCount(3)
        ->and($history[0])->toBe(['role' => 'user', 'content' => 'First'])
        ->and($history[1])->toBe(['role' => 'assistant', 'content' => 'Second'])
        ->and($history[2])->toBe(['role' => 'user', 'content' => 'Third']);
});

it('can limit conversation history', function () {
    $conversation = Conversation::factory()->create();
    Message::factory()->count(10)->for($conversation)->create();

    $history = $this->memoryManager->getConversationHistory($conversation, 5);

    expect($history)->toHaveCount(5);
});

it('can get relevant memories', function () {
    $user = User::factory()->create();
    MemoryFragment::factory()->forUser($user)->count(3)->create(['content' => 'test query content']);
    MemoryFragment::factory()->count(2)->create(); // Other user's memories

    $memories = $this->memoryManager->getRelevantMemories('test', $user->id);

    expect($memories)->toHaveCount(3);
});

it('can store a memory fragment', function () {
    $user = User::factory()->create();

    $memory = $this->memoryManager->remember(
        'User prefers dark mode',
        $user->id,
        null,
        'preference'
    );

    expect($memory)->toBeInstanceOf(MemoryFragment::class)
        ->and($memory->content)->toBe('User prefers dark mode')
        ->and($memory->key)->toBe('preference')
        ->and($memory->user_id)->toBe($user->id);
});

it('can forget memories by key', function () {
    $user = User::factory()->create();
    MemoryFragment::factory()->forUser($user)->withKey('preference')->count(3)->create();
    MemoryFragment::factory()->forUser($user)->withKey('other')->count(2)->create();

    $deleted = $this->memoryManager->forget('preference', $user->id);

    expect($deleted)->toBe(3)
        ->and(MemoryFragment::where('key', 'preference')->count())->toBe(0)
        ->and(MemoryFragment::where('key', 'other')->count())->toBe(2);
});

it('can format memories for prompt', function () {
    $memories = [
        MemoryFragment::make(['content' => 'First memory']),
        MemoryFragment::make(['content' => 'Second memory']),
    ];

    $formatted = $this->memoryManager->formatMemoriesForPrompt($memories);

    expect($formatted)->toContain('## Relevant Memories')
        ->and($formatted)->toContain('First memory')
        ->and($formatted)->toContain('Second memory');
});

it('returns empty string when no memories to format', function () {
    $formatted = $this->memoryManager->formatMemoriesForPrompt([]);

    expect($formatted)->toBe('');
});

it('can build system prompt with memories', function () {
    $basePrompt = 'You are a helpful assistant.';
    $memories = [
        MemoryFragment::make(['content' => 'User likes cats']),
    ];

    $systemPrompt = $this->memoryManager->buildSystemPrompt($basePrompt, $memories);

    expect($systemPrompt)->toContain('You are a helpful assistant.')
        ->and($systemPrompt)->toContain('## Relevant Memories')
        ->and($systemPrompt)->toContain('User likes cats');
});

it('returns base prompt when no memories', function () {
    $basePrompt = 'You are a helpful assistant.';

    $systemPrompt = $this->memoryManager->buildSystemPrompt($basePrompt, []);

    expect($systemPrompt)->toBe($basePrompt);
});
