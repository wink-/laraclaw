<?php

namespace App\Livewire\Laraclaw;

use App\Models\MemoryFragment;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Memories extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function memories()
    {
        return MemoryFragment::query()
            ->when($this->search, fn ($q) => $q->where('content', 'like', "%{$this->search}%"))
            ->latest()
            ->paginate(20);
    }

    public function delete(int $id): void
    {
        MemoryFragment::destroy($id);
    }

    public function render()
    {
        return view('livewire.laraclaw.memories')->layout('components.laraclaw.layout');
    }
}
