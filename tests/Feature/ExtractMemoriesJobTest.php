<?php

use App\Jobs\ExtractMemoriesJob;
use App\Laraclaw\Memory\MemoryManager;
use App\Models\Conversation;
use App\Models\MemoryFragment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('stores explicit fact memories from remember that requests', function () {
    $conversation = Conversation::factory()->create([
        'user_id' => null,
        'gateway' => 'telegram',
    ]);

    $job = new ExtractMemoriesJob(
        $conversation->id,
        "Remember that my cat's name is Wilma",
        "I've remembered that your cat's name is Wilma.",
    );

    $job->handle(app(MemoryManager::class));

    $memory = MemoryFragment::query()->first();

    expect($memory)->not->toBeNull()
        ->and($memory->conversation_id)->toBe($conversation->id)
        ->and($memory->user_id)->toBeNull()
        ->and($memory->key)->toBe('pet_cat_name')
        ->and($memory->category)->toBe('personal')
        ->and($memory->content)->toBe("The user's cat's name is Wilma.");
});

it('deduplicates anonymous memories per conversation instead of globally', function () {
    $firstConversation = Conversation::factory()->create([
        'user_id' => null,
        'gateway' => 'telegram',
    ]);

    $secondConversation = Conversation::factory()->create([
        'user_id' => null,
        'gateway' => 'telegram',
    ]);

    $job = new ExtractMemoriesJob(
        $firstConversation->id,
        "Remember that my cat's name is Wilma",
        "I've remembered that your cat's name is Wilma.",
    );

    $job->handle(app(MemoryManager::class));

    $secondJob = new ExtractMemoriesJob(
        $secondConversation->id,
        "Remember that my cat's name is Wilma",
        "I've remembered that your cat's name is Wilma.",
    );

    $secondJob->handle(app(MemoryManager::class));

    expect(MemoryFragment::query()->count())->toBe(2)
        ->and(MemoryFragment::query()->where('conversation_id', $firstConversation->id)->count())->toBe(1)
        ->and(MemoryFragment::query()->where('conversation_id', $secondConversation->id)->count())->toBe(1);
});
