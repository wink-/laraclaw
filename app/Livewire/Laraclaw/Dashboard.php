<?php

namespace App\Livewire\Laraclaw;

use App\Models\Conversation;
use App\Models\MemoryFragment;
use Livewire\Component;

class Dashboard extends Component
{
    public array $stats = [];

    public array $health = [];

    public function mount(): void
    {
        $this->loadStats();
        $this->loadHealth();
    }

    protected function loadStats(): void
    {
        $this->stats = [
            'conversations' => Conversation::count(),
            'messages' => \App\Models\Message::count(),
            'memories' => MemoryFragment::count(),
            'today_conversations' => Conversation::whereDate('created_at', today())->count(),
        ];
    }

    protected function loadHealth(): void
    {
        $this->health = [
            'database' => 'healthy',
            'ai_provider' => config('laraclaw.ai.provider'),
            'memory' => 'enabled',
        ];
    }

    public function recentConversations()
    {
        return Conversation::withCount('messages')
            ->latest()
            ->limit(5)
            ->get();
    }

    public function render()
    {
        return view('livewire.laraclaw.dashboard', [
            'stats' => $this->stats,
            'health' => $this->health,
            'recentConversations' => $this->recentConversations(),
        ])->layout('components.laraclaw.layout');
    }
}
