<?php

namespace App\Livewire\Laraclaw;

use App\Models\Conversation;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Conversations extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $gateway = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedGateway(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function conversations()
    {
        return Conversation::query()
            ->withCount('messages')
            ->when($this->search, fn ($q) => $q->where('title', 'like', "%{$this->search}%"))
            ->when($this->gateway, fn ($q) => $q->where('gateway', $this->gateway))
            ->latest()
            ->paginate(15);
    }

    public function delete(int $id): void
    {
        Conversation::destroy($id);
    }

    public function render()
    {
        return view('livewire.laraclaw.conversations')->layout('components.laraclaw.layout');
    }
}
