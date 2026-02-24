<?php

use App\Laraclaw\Skills\SchedulerSkill;
use App\Laraclaw\Skills\TimeSkill;
use App\Livewire\Laraclaw\Dashboard;
use App\Models\SkillPlugin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('can pause and resume a scheduled task from dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $taskId = DB::table('laraclaw_scheduled_tasks')->insertGetId([
        'user_id' => $user->id,
        'action' => 'Send daily summary',
        'cron_expression' => '0 9 * * *',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Livewire::test(Dashboard::class)
        ->call('toggleScheduledTask', $taskId)
        ->assertSet('schedulerStatus', 'Scheduled task paused.');

    expect(DB::table('laraclaw_scheduled_tasks')->where('id', $taskId)->value('is_active'))
        ->toBe(0);

    Livewire::test(Dashboard::class)
        ->call('toggleScheduledTask', $taskId)
        ->assertSet('schedulerStatus', 'Scheduled task resumed.');

    expect(DB::table('laraclaw_scheduled_tasks')->where('id', $taskId)->value('is_active'))
        ->toBe(1);
});

it('can remove a scheduled task from dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $taskId = DB::table('laraclaw_scheduled_tasks')->insertGetId([
        'user_id' => $user->id,
        'action' => 'Archive notes weekly',
        'cron_expression' => '0 1 * * 0',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Livewire::test(Dashboard::class)
        ->call('removeScheduledTask', $taskId)
        ->assertSet('schedulerStatus', 'Scheduled task removed.');

    expect(DB::table('laraclaw_scheduled_tasks')->where('id', $taskId)->exists())
        ->toBeFalse();
});

it('prevents disabling required core skills from dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(Dashboard::class)
        ->call('setSkillEnabled', TimeSkill::class, false)
        ->assertSet('marketplaceStatus', 'This skill is required and cannot be disabled.');

    expect(
        SkillPlugin::query()->where('class_name', TimeSkill::class)->value('enabled')
    )->toBeTrue();
});

it('allows disabling non-required skills from dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(Dashboard::class)
        ->call('setSkillEnabled', SchedulerSkill::class, false)
        ->assertSet('marketplaceStatus', 'Skill disabled successfully.');

    expect(
        SkillPlugin::query()->where('class_name', SchedulerSkill::class)->value('enabled')
    )->toBeFalse();
});
