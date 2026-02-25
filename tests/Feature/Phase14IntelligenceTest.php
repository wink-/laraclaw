<?php

use App\Laraclaw\Agents\IntentRouter;
use App\Laraclaw\Memory\MemoryManager;
use App\Laraclaw\Skills\MemorySkill;
use App\Laraclaw\Skills\SchedulerSkill;
use App\Laraclaw\Skills\ShoppingListSkill;
use App\Models\MemoryFragment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('routes builder intent from natural language request', function () {
    $router = new IntentRouter;

    $result = $router->route('Build me a blog about gardening tips');

    expect($result['intent'])->toBe('builder')
        ->and($result['specialist_prompt'])->toBeString();
});

it('routes entertainment intent for watchlist questions', function () {
    $router = new IntentRouter;

    $result = $router->route('What shows should I watch tonight?');

    expect($result['intent'])->toBe('entertainment');
});

it('stores memories with explicit category', function () {
    $user = User::factory()->create();
    $memoryManager = app(MemoryManager::class);

    $fragment = $memoryManager->remember(
        content: 'Watch Severance season 2',
        userId: $user->id,
        key: 'watchlist',
        category: 'entertainment',
    );

    expect($fragment->category)->toBe('entertainment')
        ->and(MemoryFragment::query()->where('category', 'entertainment')->count())->toBe(1);
});

it('auto-categorizes memory entries in memory skill', function () {
    $user = User::factory()->create();

    $skill = app(MemorySkill::class)->forUser($user->id);
    $response = $skill->execute([
        'action' => 'remember',
        'content' => 'Remember I need to watch The Last of Us this weekend',
    ]);

    expect($response)->toContain("category 'entertainment'");

    $stored = MemoryFragment::query()->where('user_id', $user->id)->latest()->first();

    expect($stored)->not->toBeNull()
        ->and($stored->category)->toBe('entertainment');
});

it('recalls memories filtered by category', function () {
    $user = User::factory()->create();

    MemoryFragment::query()->create([
        'user_id' => $user->id,
        'content' => 'Buy milk and eggs',
        'category' => 'shopping',
    ]);

    MemoryFragment::query()->create([
        'user_id' => $user->id,
        'content' => 'Watch Silo season 2',
        'category' => 'entertainment',
    ]);

    $skill = app(MemorySkill::class)->forUser($user->id);

    $response = $skill->execute([
        'action' => 'recall',
        'query' => 'watch',
        'category' => 'entertainment',
    ]);

    expect($response)->toContain('Silo')
        ->and($response)->not->toContain('milk');
});

it('lists known memory categories', function () {
    $user = User::factory()->create();
    $memoryManager = app(MemoryManager::class);

    $memoryManager->remember('Buy fruit', $user->id, key: 'grocery', category: 'shopping');
    $memoryManager->remember('Watch Foundation', $user->id, key: 'watchlist', category: 'entertainment');

    $categories = $memoryManager->listCategories($user->id);

    expect($categories)->toContain('shopping')
        ->and($categories)->toContain('entertainment');
});

it('manages shopping list items with shopping list skill', function () {
    $user = User::factory()->create();
    $skill = app(ShoppingListSkill::class)->forUser($user->id);

    expect($skill->execute([
        'action' => 'add',
        'list_name' => 'groceries',
        'item' => 'Milk',
        'quantity' => '2',
    ]))->toContain('Added');

    $view = $skill->execute([
        'action' => 'view',
        'list_name' => 'groceries',
    ]);

    expect($view)->toContain('Milk')
        ->and($view)->toContain('2');

    expect($skill->execute([
        'action' => 'remove',
        'list_name' => 'groceries',
        'item' => 'Milk',
    ]))->toContain('Removed');
});

it('parses natural language when in scheduler skill', function () {
    $user = User::factory()->create();
    $skill = app(SchedulerSkill::class);

    $response = $skill->execute([
        'user_id' => $user->id,
        'action' => 'Send my daily summary',
        'when' => 'every weekday at 8am',
    ]);

    expect($response)->toContain('Task scheduled successfully')
        ->and(DB::table('laraclaw_scheduled_tasks')->count())->toBe(1)
        ->and(DB::table('laraclaw_scheduled_tasks')->value('cron_expression'))->toBe('0 8 * * 1-5');
});
