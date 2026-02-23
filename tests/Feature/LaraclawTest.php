<?php

use App\Laraclaw\Agents\CoreAgent;
use App\Laraclaw\Facades\Laraclaw;
use App\Laraclaw\Memory\MemoryManager;
use App\Laraclaw\Skills\CalculatorSkill;
use App\Laraclaw\Skills\TimeSkill;
use App\Laraclaw\Skills\WebSearchSkill;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can resolve the laraclaw service', function () {
    $laraclaw = app('laraclaw');

    expect($laraclaw)->toBeInstanceOf(\App\Laraclaw\Laraclaw::class);
});

it('can access memory manager', function () {
    $memory = Laraclaw::memory();

    expect($memory)->toBeInstanceOf(MemoryManager::class);
});

it('can access core agent', function () {
    $agent = Laraclaw::agent();

    expect($agent)->toBeInstanceOf(CoreAgent::class);
});

it('can start a new conversation', function () {
    $conversation = Laraclaw::startConversation();

    expect($conversation)->toBeInstanceOf(Conversation::class)
        ->and($conversation->exists())->toBeTrue()
        ->and($conversation->gateway)->toBe('cli');
});

it('can start a conversation for a user', function () {
    $user = User::factory()->create();

    $conversation = Laraclaw::startConversation($user->id, 'telegram', 'My Chat');

    expect($conversation->user_id)->toBe($user->id)
        ->and($conversation->gateway)->toBe('telegram')
        ->and($conversation->title)->toBe('My Chat');
});

it('core agent has skills registered', function () {
    $agent = Laraclaw::agent();

    $tools = $agent->tools();

    expect($tools)->toHaveCount(3);
});

it('time skill can get current time', function () {
    $skill = new TimeSkill;

    $result = $skill->execute(['timezone' => 'UTC']);

    expect($result)->toContain('UTC')
        ->and($result)->toContain('Current time');
});

it('calculator skill can perform calculations', function () {
    $skill = new CalculatorSkill;

    $result = $skill->execute(['expression' => '2 + 2']);

    expect($result)->toContain('4');
});

it('calculator skill rejects invalid expressions', function () {
    $skill = new CalculatorSkill;

    $result = $skill->execute(['expression' => '2 + abc']);

    expect($result)->toContain('Error');
});

it('web search skill returns no results message for empty query', function () {
    $skill = new WebSearchSkill;

    $result = $skill->execute(['query' => '']);

    expect($result)->toContain('Error');
});

it('can create conversation without user', function () {
    $conversation = Laraclaw::startConversation(null, 'discord');

    expect($conversation->user_id)->toBeNull()
        ->and($conversation->gateway)->toBe('discord');
});

it('laraclaw facade is properly registered', function () {
    expect(app('laraclaw'))->toBeInstanceOf(\App\Laraclaw\Laraclaw::class);
});

it('skills are registered as tagged services', function () {
    $skills = app()->tagged('laraclaw.skills');

    $skillClasses = collect($skills)->map(fn ($skill) => get_class($skill))->all();

    expect($skillClasses)->toContain(TimeSkill::class)
        ->and($skillClasses)->toContain(CalculatorSkill::class)
        ->and($skillClasses)->toContain(WebSearchSkill::class);
});
