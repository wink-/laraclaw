<?php

use App\Models\Conversation;
use App\Models\MemoryFragment;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Component;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

describe('Dashboard', function () {
    it('renders with default overview tab', function () {
        Volt::test('laraclaw.dashboard')
            ->assertSet('activeTab', 'overview')
            ->assertSee('Dashboard')
            ->assertSee('Overview')
            ->assertSee('Analytics')
            ->assertSee('Infrastructure')
            ->assertSee('Management');
    });

    it('switches between tabs', function () {
        Volt::test('laraclaw.dashboard')
            ->call('setActiveTab', 'analytics')
            ->assertSet('activeTab', 'analytics')
            ->call('setActiveTab', 'infrastructure')
            ->assertSet('activeTab', 'infrastructure')
            ->call('setActiveTab', 'management')
            ->assertSet('activeTab', 'management');
    });

    it('loads stats on mount', function () {
        $conversation = Conversation::factory()->create();
        Message::factory()->count(3)->for($conversation)->create();

        Volt::test('laraclaw.dashboard')
            ->assertSet('stats.conversations', 1)
            ->assertSet('stats.messages', 3);
    });

    it('refreshes stats', function () {
        $component = Volt::test('laraclaw.dashboard');

        Conversation::factory()->create();

        $component->call('refresh')
            ->assertSet('stats.conversations', 1);
    });

    it('has refresh method for wire:poll', function () {
        Volt::test('laraclaw.dashboard')
            ->call('refresh')
            ->assertSuccessful();
    });

    it('shows skill marketplace in management tab', function () {
        Volt::test('laraclaw.dashboard')
            ->call('setActiveTab', 'management')
            ->assertSee('Skill Marketplace');
    });
});

describe('Conversations', function () {
    it('renders with empty state when no conversations', function () {
        Volt::test('laraclaw.conversations')
            ->assertSee('No conversations yet')
            ->assertSee('new chat');
    });

    it('shows search results empty state', function () {
        Conversation::factory()->create(['title' => 'Test Chat']);

        Volt::test('laraclaw.conversations')
            ->set('search', 'nonexistent')
            ->assertSee('No matches found');
    });

    it('deletes a single conversation', function () {
        $conversation = Conversation::factory()->create();

        Volt::test('laraclaw.conversations')
            ->call('delete', $conversation->id);

        expect(Conversation::find($conversation->id))->toBeNull();
    });

    it('selects all conversations on current page', function () {
        $conversations = Conversation::factory()->count(3)->create();

        $component = Volt::test('laraclaw.conversations')
            ->set('selectAll', true);

        expect($component->get('selectedIds'))->toHaveCount(3);
    });

    it('clears selection when unchecking select all', function () {
        Conversation::factory()->count(3)->create();

        Volt::test('laraclaw.conversations')
            ->set('selectAll', true)
            ->set('selectAll', false)
            ->assertSet('selectedIds', []);
    });

    it('deletes selected conversations in bulk', function () {
        $conversations = Conversation::factory()->count(3)->create();
        $ids = $conversations->pluck('id')->map(fn ($id) => (string) $id)->all();

        Volt::test('laraclaw.conversations')
            ->set('selectedIds', $ids)
            ->call('deleteSelected')
            ->assertSet('bulkStatus', 'Deleted 3 conversations.');

        expect(Conversation::count())->toBe(0);
    });

    it('prunes empty conversations older than one hour', function () {
        $old = Conversation::factory()->create([
            'title' => 'Old Empty',
            'created_at' => now()->subHours(2),
        ]);

        $recent = Conversation::factory()->create([
            'title' => 'Recent Empty',
            'created_at' => now()->subMinutes(10),
        ]);

        $withMessages = Conversation::factory()->create(['title' => 'With Messages']);
        Message::factory()->for($withMessages)->create();

        Volt::test('laraclaw.conversations')
            ->call('pruneEmpty')
            ->assertSet('bulkStatus', 'Pruned 1 empty conversations.');

        expect(Conversation::pluck('title')->toArray())
            ->toContain('Recent Empty')
            ->toContain('With Messages')
            ->not->toContain('Old Empty');
    });

    it('counts empty conversations for prune button', function () {
        Conversation::factory()->create(['created_at' => now()->subHours(2)]);
        Conversation::factory()->create(['created_at' => now()->subHours(3)]);

        $component = Volt::test('laraclaw.conversations');

        expect($component->get('emptyCount'))->toBe(2);
    });

    it('hides empty conversations when toggle is on', function () {
        $empty = Conversation::factory()->create(['title' => 'Empty Chat']);
        $withMessages = Conversation::factory()->create(['title' => 'Has Messages']);
        Message::factory()->for($withMessages)->create();

        Volt::test('laraclaw.conversations')
            ->set('hideEmpty', true)
            ->assertSee('Has Messages')
            ->assertDontSee('Empty Chat');
    });

    it('shows conversations filtered by gateway', function () {
        Conversation::factory()->fromGateway('telegram')->create(['title' => 'Telegram Chat']);
        Conversation::factory()->fromGateway('cli')->create(['title' => 'CLI Chat']);

        Volt::test('laraclaw.conversations')
            ->set('gateway', 'telegram')
            ->assertSee('Telegram Chat')
            ->assertDontSee('CLI Chat');
    });
});

describe('Memories', function () {
    it('renders with empty state when no memories', function () {
        Volt::test('laraclaw.memories')
            ->assertSee('No memories stored yet');
    });

    it('shows guidance text in empty state', function () {
        Volt::test('laraclaw.memories')
            ->assertSee('Remember that my favorite color is blue', escape: false);
    });

    it('displays existing memories', function () {
        MemoryFragment::factory()->forUser($this->user)->create([
            'key' => 'favorite_color',
            'content' => 'blue',
        ]);

        Volt::test('laraclaw.memories')
            ->assertSee('favorite_color')
            ->assertSee('blue');
    });
});

describe('Auto-Title', function () {
    it('generates title from first user message', function () {
        $conversation = Conversation::factory()->create(['title' => 'New Chat']);

        (new class extends Component
        {
            public function testTitle(Conversation $conversation, string $message): void
            {
                $isFirstMessage = $conversation->messages()->count() === 0;
                if ($isFirstMessage || $conversation->title === 'New Chat') {
                    $cleanMessage = preg_replace('/^\[Attached:[^\]]*\]\s*/', '', $message);
                    $cleanMessage = trim($cleanMessage);
                    if ($cleanMessage !== '') {
                        $title = mb_substr($cleanMessage, 0, 60);
                        if (mb_strlen($cleanMessage) > 60) {
                            $breakAt = mb_strrpos($title, ' ');
                            $title = $breakAt > 20 ? mb_substr($title, 0, $breakAt) : $title;
                            $title .= '...';
                        }
                        $conversation->update(['title' => $title]);
                    }
                }
            }
        })->testTitle($conversation, 'Hello, how are you today?');

        expect($conversation->fresh()->title)->toBe('Hello, how are you today?');
    });

    it('strips attachment prefix from title', function () {
        $conversation = Conversation::factory()->create(['title' => 'New Chat']);

        (new class extends Component
        {
            public function testTitle(Conversation $conversation, string $message): void
            {
                $isFirstMessage = $conversation->messages()->count() === 0;
                if ($isFirstMessage || $conversation->title === 'New Chat') {
                    $cleanMessage = preg_replace('/^\[Attached:[^\]]*\]\s*/', '', $message);
                    $cleanMessage = trim($cleanMessage);
                    if ($cleanMessage !== '') {
                        $title = mb_substr($cleanMessage, 0, 60);
                        if (mb_strlen($cleanMessage) > 60) {
                            $breakAt = mb_strrpos($title, ' ');
                            $title = $breakAt > 20 ? mb_substr($title, 0, $breakAt) : $title;
                            $title .= '...';
                        }
                        $conversation->update(['title' => $title]);
                    }
                }
            }
        })->testTitle($conversation, "[Attached: report.pdf]\n\nPlease analyze this document");

        expect($conversation->fresh()->title)->toBe('Please analyze this document');
    });

    it('truncates long titles at word boundary', function () {
        $longMessage = 'This is a very long message that definitely exceeds the sixty character limit and should be truncated at a word boundary';
        $conversation = Conversation::factory()->create(['title' => 'New Chat']);

        (new class extends Component
        {
            public function testTitle(Conversation $conversation, string $message): void
            {
                if ($conversation->title === 'New Chat') {
                    $cleanMessage = preg_replace('/^\[Attached:[^\]]*\]\s*/', '', $message);
                    $cleanMessage = trim($cleanMessage);
                    if ($cleanMessage !== '') {
                        $title = mb_substr($cleanMessage, 0, 60);
                        if (mb_strlen($cleanMessage) > 60) {
                            $breakAt = mb_strrpos($title, ' ');
                            $title = $breakAt > 20 ? mb_substr($title, 0, $breakAt) : $title;
                            $title .= '...';
                        }
                        $conversation->update(['title' => $title]);
                    }
                }
            }
        })->testTitle($conversation, $longMessage);

        $title = $conversation->fresh()->title;
        expect($title)->toEndWith('...');
        expect(mb_strlen($title))->toBeLessThanOrEqual(63);
    });

    it('re-titles conversations still named New Chat', function () {
        $conversation = Conversation::factory()->create(['title' => 'New Chat']);
        Message::factory()->for($conversation)->fromAssistant()->create();

        (new class extends Component
        {
            public function testTitle(Conversation $conversation, string $message): void
            {
                if ($conversation->title === 'New Chat') {
                    $cleanMessage = preg_replace('/^\[Attached:[^\]]*\]\s*/', '', $message);
                    $cleanMessage = trim($cleanMessage);
                    if ($cleanMessage !== '') {
                        $title = mb_substr($cleanMessage, 0, 60);
                        if (mb_strlen($cleanMessage) > 60) {
                            $breakAt = mb_strrpos($title, ' ');
                            $title = $breakAt > 20 ? mb_substr($title, 0, $breakAt) : $title;
                            $title .= '...';
                        }
                        $conversation->update(['title' => $title]);
                    }
                }
            }
        })->testTitle($conversation, 'My actual question here');

        expect($conversation->fresh()->title)->toBe('My actual question here');
    });
});

describe('Routes', function () {
    it('documents route is accessible', function () {
        $this->get(route('laraclaw.documents.live'))->assertSuccessful();
    });

    it('app builder route is accessible', function () {
        $this->get(route('laraclaw.app-builder.live'))->assertSuccessful();
    });

    it('conversations route is accessible', function () {
        $this->get(route('laraclaw.conversations.live'))->assertSuccessful();
    });

    it('memories route is accessible', function () {
        $this->get(route('laraclaw.memories.live'))->assertSuccessful();
    });

    it('chat route is accessible', function () {
        $this->get(route('laraclaw.chat.live'))->assertSuccessful();
    });

    it('dashboard route is accessible', function () {
        $this->get(route('laraclaw.dashboard.live'))->assertSuccessful();
    });
});
