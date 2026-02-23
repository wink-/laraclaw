<?php

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can create a message', function () {
    $message = Message::factory()->create();

    expect($message)->toBeInstanceOf(Message::class)
        ->and($message->exists())->toBeTrue();
});

it('belongs to a conversation', function () {
    $conversation = Conversation::factory()->create();
    $message = Message::factory()->for($conversation)->create();

    expect($message->conversation)->toBeInstanceOf(Conversation::class)
        ->and($message->conversation->id)->toBe($conversation->id);
});

it('can identify user messages', function () {
    $message = Message::factory()->fromUser()->create();

    expect($message->isUser())->toBeTrue()
        ->and($message->isAssistant())->toBeFalse()
        ->and($message->isToolResult())->toBeFalse();
});

it('can identify assistant messages', function () {
    $message = Message::factory()->fromAssistant()->create();

    expect($message->isAssistant())->toBeTrue()
        ->and($message->isUser())->toBeFalse()
        ->and($message->isToolResult())->toBeFalse();
});

it('can identify tool result messages', function () {
    $message = Message::factory()->asToolResult('calculator', ['expression' => '2+2'])->create();

    expect($message->isToolResult())->toBeTrue()
        ->and($message->tool_name)->toBe('calculator')
        ->and($message->isUser())->toBeFalse()
        ->and($message->isAssistant())->toBeFalse();
});

it('casts tool arguments to array', function () {
    $message = Message::factory()->asToolResult('test', ['foo' => 'bar'])->create();

    expect($message->tool_arguments)->toBe(['foo' => 'bar']);
});

it('casts metadata to array', function () {
    $message = Message::factory()->create([
        'metadata' => ['key' => 'value'],
    ]);

    expect($message->metadata)->toBe(['key' => 'value']);
});
