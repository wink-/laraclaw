<?php

use App\Models\Conversation;
use App\Models\MemoryFragment;
use App\Models\User;
use Livewire\Volt\Volt;

it('shows anonymous telegram memories alongside the signed-in users memories', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $this->actingAs($user);

    MemoryFragment::query()->create([
        'user_id' => $user->id,
        'content' => 'The user prefers dark mode.',
        'category' => 'personal',
    ]);

    $telegramConversation = Conversation::factory()->create([
        'user_id' => null,
        'gateway' => 'telegram',
        'title' => 'Telegram: Bob',
    ]);

    MemoryFragment::query()->create([
        'user_id' => null,
        'conversation_id' => $telegramConversation->id,
        'key' => 'pet_cat_name',
        'content' => "The user's cat's name is Wilma.",
        'category' => 'personal',
    ]);

    MemoryFragment::query()->create([
        'user_id' => $otherUser->id,
        'content' => 'This should stay hidden.',
        'category' => 'personal',
    ]);

    Volt::test('laraclaw.memories')
        ->assertSee('The user prefers dark mode.')
        ->assertSee("The user's cat's name is Wilma.")
        ->assertSee('telegram')
        ->assertDontSee('This should stay hidden.');
});
