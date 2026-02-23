<?php

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can create a conversation', function () {
    $conversation = Conversation::factory()->create();

    expect($conversation)->toBeInstanceOf(Conversation::class)
        ->and($conversation->exists())->toBeTrue();
});

it('belongs to a user', function () {
    $user = User::factory()->create();
    $conversation = Conversation::factory()->forUser($user)->create();

    expect($conversation->user)->toBeInstanceOf(User::class)
        ->and($conversation->user->id)->toBe($user->id);
});

it('can have many messages', function () {
    $conversation = Conversation::factory()->create();
    Message::factory()->count(3)->for($conversation)->create();

    expect($conversation->messages)->toHaveCount(3);
});

it('can have many memory fragments', function () {
    $conversation = Conversation::factory()->create();

    expect($conversation->memoryFragments()->count())->toBe(0);
});

it('can convert messages to prompt format', function () {
    $conversation = Conversation::factory()->create();
    Message::factory()->fromUser()->for($conversation)->create(['content' => 'Hello']);
    Message::factory()->fromAssistant()->for($conversation)->create(['content' => 'Hi there!']);

    $messages = $conversation->toPromptMessages();

    expect($messages)->toHaveCount(2)
        ->and($messages[0])->toBe(['role' => 'user', 'content' => 'Hello'])
        ->and($messages[1])->toBe(['role' => 'assistant', 'content' => 'Hi there!']);
});

it('casts metadata to array', function () {
    $conversation = Conversation::factory()->create([
        'metadata' => ['foo' => 'bar'],
    ]);

    expect($conversation->metadata)->toBe(['foo' => 'bar']);
});

it('can create conversation without user', function () {
    $conversation = Conversation::factory()->create(['user_id' => null]);

    expect($conversation->user_id)->toBeNull();
});

it('can set gateway', function () {
    $conversation = Conversation::factory()->fromGateway('telegram')->create();

    expect($conversation->gateway)->toBe('telegram');
});
