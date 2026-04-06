<?php

use App\Laraclaw\Facades\Laraclaw;
use App\Laraclaw\Skills\CalculatorSkill;
use App\Laraclaw\Skills\HttpRequestSkill;
use App\Laraclaw\Skills\PluginManager;
use App\Laraclaw\Skills\TimeSkill;
use App\Models\SkillPlugin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

describe('PluginManager', function () {
    it('can update skill description', function () {
        $manager = app(PluginManager::class);
        $manager->listSkills();

        $manager->updateSkill(CalculatorSkill::class, 'Custom calculator description');

        $plugin = SkillPlugin::where('class_name', CalculatorSkill::class)->first();
        expect($plugin->description)->toBe('Custom calculator description');
    });

    it('can update skill metadata', function () {
        $manager = app(PluginManager::class);
        $manager->listSkills();

        $manager->updateSkill(CalculatorSkill::class, null, ['custom_key' => 'custom_value']);

        $plugin = SkillPlugin::where('class_name', CalculatorSkill::class)->first();
        expect($plugin->metadata)->toBe(['custom_key' => 'custom_value']);
        expect($plugin->description)->toBeNull();
    });

    it('can reset skill to defaults', function () {
        $manager = app(PluginManager::class);
        $manager->listSkills();

        $manager->updateSkill(CalculatorSkill::class, 'Custom desc', ['key' => 'val']);
        $manager->resetSkill(CalculatorSkill::class);

        $plugin = SkillPlugin::where('class_name', CalculatorSkill::class)->first();
        expect($plugin->description)->toBeNull();
        expect($plugin->metadata)->toBeNull();
    });

    it('can get skill detail', function () {
        $manager = app(PluginManager::class);
        $manager->listSkills();

        $detail = $manager->getSkillDetail(CalculatorSkill::class);

        expect($detail)->not->toBeNull()
            ->and($detail['name'])->toBe('CalculatorSkill')
            ->and($detail['class_name'])->toBe(CalculatorSkill::class)
            ->and($detail['default_description'])->toBeString()
            ->and($detail['enabled'])->toBeTrue()
            ->and($detail['is_required'])->toBeTrue()
            ->and($detail['schema_fields'])->toBeArray();
    });

    it('returns null for non-existent skill detail', function () {
        $manager = app(PluginManager::class);

        $detail = $manager->getSkillDetail('App\\NonExistent\\Skill');

        expect($detail)->toBeNull();
    });

    it('updateSkill does nothing with no updates', function () {
        $manager = app(PluginManager::class);
        $manager->listSkills();

        $before = SkillPlugin::where('class_name', CalculatorSkill::class)->first();

        $manager->updateSkill(CalculatorSkill::class, null, null);

        $after = SkillPlugin::where('class_name', CalculatorSkill::class)->first();
        expect($after->description)->toBe($before->description);
        expect($after->metadata)->toBe($before->metadata);
    });
});

describe('Laraclaw facade', function () {
    it('can update skill description', function () {
        Laraclaw::listSkills();
        Laraclaw::updateSkill(CalculatorSkill::class, 'Facade description');

        $plugin = SkillPlugin::where('class_name', CalculatorSkill::class)->first();
        expect($plugin->description)->toBe('Facade description');
    });

    it('can reset skill', function () {
        Laraclaw::listSkills();
        Laraclaw::updateSkill(CalculatorSkill::class, 'Will be reset');
        Laraclaw::resetSkill(CalculatorSkill::class);

        $plugin = SkillPlugin::where('class_name', CalculatorSkill::class)->first();
        expect($plugin->description)->toBeNull();
    });

    it('can get skill detail', function () {
        Laraclaw::listSkills();

        $detail = Laraclaw::getSkillDetail(TimeSkill::class);

        expect($detail)->not->toBeNull()
            ->and($detail['name'])->toBe('TimeSkill')
            ->and($detail['is_required'])->toBeTrue();
    });
});

describe('Volt skill editor', function () {
    beforeEach(function () {
        $this->actingAs(User::factory()->create());
    });

    it('can open skill editor', function () {
        Volt::test('laraclaw.dashboard')
            ->call('setActiveTab', 'management')
            ->assertSee('Skill Marketplace')
            ->call('selectSkill', CalculatorSkill::class)
            ->assertSet('showSkillEditor', true)
            ->assertSet('editingSkillName', 'CalculatorSkill')
            ->assertSet('editingSkillEnabled', true);
    });

    it('can save custom description', function () {
        Volt::test('laraclaw.dashboard')
            ->call('selectSkill', CalculatorSkill::class)
            ->set('editingSkillDescription', 'My custom calc description')
            ->call('saveSkillDescription')
            ->assertSet('skillEditorStatus', 'Description saved successfully.');

        $plugin = SkillPlugin::where('class_name', CalculatorSkill::class)->first();
        expect($plugin->description)->toBe('My custom calc description');
    });

    it('can reset skill to default', function () {
        Laraclaw::listSkills();
        Laraclaw::updateSkill(CalculatorSkill::class, 'Will be cleared');

        Volt::test('laraclaw.dashboard')
            ->call('selectSkill', CalculatorSkill::class)
            ->call('resetSkillToDefault')
            ->assertSet('skillEditorStatus', 'Skill reset to defaults.')
            ->assertSet('editingSkillDescription', null);

        $plugin = SkillPlugin::where('class_name', CalculatorSkill::class)->first();
        expect($plugin->description)->toBeNull();
    });

    it('prevents disabling required skill', function () {
        Volt::test('laraclaw.dashboard')
            ->call('selectSkill', CalculatorSkill::class)
            ->call('toggleEditingSkillEnabled')
            ->assertSet('skillEditorStatus', 'This skill is required and cannot be disabled.');
    });

    it('allows toggling non-required skill', function () {
        Volt::test('laraclaw.dashboard')
            ->call('selectSkill', HttpRequestSkill::class)
            ->assertSet('editingSkillEnabled', true)
            ->call('toggleEditingSkillEnabled')
            ->assertSet('editingSkillEnabled', false)
            ->assertSet('skillEditorStatus', 'Skill disabled.');
    });

    it('can close skill editor', function () {
        Volt::test('laraclaw.dashboard')
            ->call('selectSkill', CalculatorSkill::class)
            ->assertSet('showSkillEditor', true)
            ->call('closeSkillEditor')
            ->assertSet('showSkillEditor', false)
            ->assertSet('editingSkillClass', null);
    });

    it('selectSkill shows error for missing skill', function () {
        Volt::test('laraclaw.dashboard')
            ->call('selectSkill', 'App\\NonExistent\\Skill')
            ->assertSet('showSkillEditor', false);
    });
});
