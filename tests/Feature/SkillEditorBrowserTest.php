<?php

use App\Laraclaw\Facades\Laraclaw;
use App\Laraclaw\Skills\CalculatorSkill;
use App\Laraclaw\Skills\HttpRequestSkill;
use App\Laraclaw\Skills\TimeSkill;
use App\Models\SkillPlugin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

describe('Skill Marketplace - Management Tab', function () {
    it('renders skill marketplace with clickable rows', function () {
        Volt::test('laraclaw.dashboard')
            ->call('setActiveTab', 'management')
            ->assertSee('Skill Marketplace')
            ->assertSee('CalculatorSkill')
            ->assertSee('TimeSkill')
            ->assertSee('selectSkill');
    });

    it('shows status badges on skill rows', function () {
        Volt::test('laraclaw.dashboard')
            ->call('setActiveTab', 'management')
            ->assertSee('Required')
            ->assertSee('Active');
    });

    it('shows disabled badge for disabled skills', function () {
        Laraclaw::listSkills();
        SkillPlugin::where('class_name', HttpRequestSkill::class)->update(['enabled' => false]);

        Volt::test('laraclaw.dashboard')
            ->call('setActiveTab', 'management')
            ->assertSee('Disabled');
    });

    it('renders chevron arrows on skill rows', function () {
        $html = Volt::test('laraclaw.dashboard')
            ->call('setActiveTab', 'management')
            ->html();

        expect($html)->toContain('M9 5l7 7-7 7');
    });

    it('renders hover effect on skill rows', function () {
        $html = Volt::test('laraclaw.dashboard')
            ->call('setActiveTab', 'management')
            ->html();

        expect($html)->toContain('hover:bg-gray-700/70');
        expect($html)->toContain('cursor-pointer');
    });
});

describe('Skill Editor Panel - Opening', function () {
    it('renders slide-in panel markup when skill is selected', function () {
        $html = Volt::test('laraclaw.dashboard')
            ->call('selectSkill', CalculatorSkill::class)
            ->html();

        expect($html)->toContain('translate-x-full');
        expect($html)->toContain('Custom Description');
        expect($html)->toContain('saveSkillDescription');
        expect($html)->toContain('resetSkillToDefault');
        expect($html)->toContain('closeSkillEditor');
    });

    it('renders the dark backdrop overlay', function () {
        $html = Volt::test('laraclaw.dashboard')
            ->call('selectSkill', CalculatorSkill::class)
            ->html();

        expect($html)->toContain('bg-black/50');
    });

    it('renders close button with X icon', function () {
        $html = Volt::test('laraclaw.dashboard')
            ->call('selectSkill', CalculatorSkill::class)
            ->html();

        expect($html)->toContain('M6 18L18 6M6 6l12 12');
    });

    it('shows default description from skill class', function () {
        Volt::test('laraclaw.dashboard')
            ->call('selectSkill', CalculatorSkill::class)
            ->assertSee('Perform mathematical calculations');
    });

    it('shows schema parameters for the skill', function () {
        Volt::test('laraclaw.dashboard')
            ->call('selectSkill', CalculatorSkill::class)
            ->assertSee('expression');
    });

    it('shows enabled status and class name in editor', function () {
        $html = Volt::test('laraclaw.dashboard')
            ->call('selectSkill', CalculatorSkill::class)
            ->html();

        expect($html)->toContain('Enabled');
        expect($html)->toContain(CalculatorSkill::class);
    });
});

describe('Skill Editor Panel - Editing Description', function () {
    it('can type and save a custom description', function () {
        Volt::test('laraclaw.dashboard')
            ->call('selectSkill', CalculatorSkill::class)
            ->set('editingSkillDescription', 'My custom calculator')
            ->call('saveSkillDescription')
            ->assertSet('skillEditorStatus', 'Description saved successfully.');

        $plugin = SkillPlugin::where('class_name', CalculatorSkill::class)->first();
        expect($plugin->description)->toBe('My custom calculator');
    });

    it('shows custom description in marketplace after saving', function () {
        Volt::test('laraclaw.dashboard')
            ->call('selectSkill', CalculatorSkill::class)
            ->set('editingSkillDescription', 'Custom override desc')
            ->call('saveSkillDescription')
            ->call('closeSkillEditor')
            ->call('setActiveTab', 'management')
            ->assertSee('Custom override desc');
    });

    it('validates description max length', function () {
        $longDescription = str_repeat('a', 501);

        Volt::test('laraclaw.dashboard')
            ->call('selectSkill', CalculatorSkill::class)
            ->set('editingSkillDescription', $longDescription)
            ->call('saveSkillDescription')
            ->assertHasErrors(['editingSkillDescription' => 'max']);
    });

    it('allows clearing custom description', function () {
        Laraclaw::listSkills();
        Laraclaw::updateSkill(CalculatorSkill::class, 'Old desc');

        Volt::test('laraclaw.dashboard')
            ->call('selectSkill', CalculatorSkill::class)
            ->set('editingSkillDescription', '')
            ->call('saveSkillDescription');

        $plugin = SkillPlugin::where('class_name', CalculatorSkill::class)->first();
        expect($plugin->description)->toBeNull();
    });
});

describe('Skill Editor Panel - Reset to Default', function () {
    it('resets description and metadata to null', function () {
        Laraclaw::listSkills();
        SkillPlugin::where('class_name', CalculatorSkill::class)->update([
            'description' => 'Custom desc',
            'metadata' => ['custom' => 'data'],
        ]);

        Volt::test('laraclaw.dashboard')
            ->call('selectSkill', CalculatorSkill::class)
            ->call('resetSkillToDefault')
            ->assertSet('skillEditorStatus', 'Skill reset to defaults.')
            ->assertSet('editingSkillDescription', null);

        $plugin = SkillPlugin::where('class_name', CalculatorSkill::class)->first();
        expect($plugin->description)->toBeNull();
        expect($plugin->metadata)->toBeNull();
    });
});

describe('Skill Editor Panel - Enable/Disable Toggle', function () {
    it('shows enabled status with toggle button for non-required skills', function () {
        $html = Volt::test('laraclaw.dashboard')
            ->call('selectSkill', HttpRequestSkill::class)
            ->html();

        expect($html)->toContain('toggleEditingSkillEnabled');
    });

    it('shows Required badge instead of toggle for required skills', function () {
        $html = Volt::test('laraclaw.dashboard')
            ->call('selectSkill', TimeSkill::class)
            ->html();

        expect($html)->toContain('Required');
    });

    it('can disable a non-required skill', function () {
        Volt::test('laraclaw.dashboard')
            ->call('selectSkill', HttpRequestSkill::class)
            ->assertSet('editingSkillEnabled', true)
            ->call('toggleEditingSkillEnabled')
            ->assertSet('editingSkillEnabled', false)
            ->assertSet('skillEditorStatus', 'Skill disabled.');
    });

    it('can re-enable a disabled skill', function () {
        Laraclaw::listSkills();
        SkillPlugin::where('class_name', HttpRequestSkill::class)->update(['enabled' => false]);

        Volt::test('laraclaw.dashboard')
            ->call('selectSkill', HttpRequestSkill::class)
            ->assertSet('editingSkillEnabled', false)
            ->call('toggleEditingSkillEnabled')
            ->assertSet('editingSkillEnabled', true)
            ->assertSet('skillEditorStatus', 'Skill enabled.');
    });

    it('prevents disabling required skills', function () {
        Volt::test('laraclaw.dashboard')
            ->call('selectSkill', CalculatorSkill::class)
            ->call('toggleEditingSkillEnabled')
            ->assertSet('skillEditorStatus', 'This skill is required and cannot be disabled.');

        expect(SkillPlugin::where('class_name', CalculatorSkill::class)->value('enabled'))->toBeTrue();
    });
});

describe('Skill Editor Panel - Closing', function () {
    it('clears all editor state on close', function () {
        Volt::test('laraclaw.dashboard')
            ->call('selectSkill', CalculatorSkill::class)
            ->assertSet('showSkillEditor', true)
            ->assertSet('editingSkillName', 'CalculatorSkill')
            ->call('closeSkillEditor')
            ->assertSet('showSkillEditor', false)
            ->assertSet('editingSkillClass', null)
            ->assertSet('editingSkillName', null)
            ->assertSet('editingSkillEnabled', false)
            ->assertSet('skillEditorStatus', null);
    });
});

describe('Skill Editor Panel - Error Handling', function () {
    it('handles selecting a non-existent skill gracefully', function () {
        Volt::test('laraclaw.dashboard')
            ->call('selectSkill', 'App\\NonExistent\\Skill')
            ->assertSet('showSkillEditor', false)
            ->assertSet('marketplaceStatus', 'Skill not found.');
    });

    it('save does nothing when no skill is selected', function () {
        Volt::test('laraclaw.dashboard')
            ->call('saveSkillDescription')
            ->assertSet('skillEditorStatus', null);
    });

    it('reset does nothing when no skill is selected', function () {
        Volt::test('laraclaw.dashboard')
            ->call('resetSkillToDefault')
            ->assertSet('skillEditorStatus', null);
    });
});

describe('Skill Editor Panel - Full User Journey', function () {
    it('complete workflow: open, edit, save, close, reopen with persisted state', function () {
        Volt::test('laraclaw.dashboard')
            ->call('selectSkill', CalculatorSkill::class)
            ->assertSet('showSkillEditor', true)
            ->assertSet('editingSkillName', 'CalculatorSkill')
            ->set('editingSkillDescription', 'Updated description for testing')
            ->call('saveSkillDescription')
            ->assertSet('skillEditorStatus', 'Description saved successfully.')
            ->call('closeSkillEditor')
            ->assertSet('showSkillEditor', false);

        Volt::test('laraclaw.dashboard')
            ->call('selectSkill', CalculatorSkill::class)
            ->assertSet('editingSkillDescription', 'Updated description for testing');

        Volt::test('laraclaw.dashboard')
            ->call('selectSkill', CalculatorSkill::class)
            ->call('resetSkillToDefault')
            ->assertSet('editingSkillDescription', null);

        expect(SkillPlugin::where('class_name', CalculatorSkill::class)->value('description'))->toBeNull();
    });
});
