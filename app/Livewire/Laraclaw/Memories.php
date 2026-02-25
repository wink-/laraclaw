<?php

namespace App\Livewire\Laraclaw;

use App\Models\MemoryFragment;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Memories extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $category = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedCategory(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function memories()
    {
        return MemoryFragment::query()
            ->where('user_id', Auth::id())
            ->when($this->category !== '', fn ($q) => $q->where('category', $this->category))
            ->when($this->search, fn ($q) => $q->where('content', 'like', "%{$this->search}%"))
            ->latest()
            ->paginate(20);
    }

    #[Computed]
    public function categories()
    {
        return MemoryFragment::query()
            ->where('user_id', Auth::id())
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');
    }

    public function delete(int $id): void
    {
        MemoryFragment::query()
            ->where('id', $id)
            ->where('user_id', Auth::id())
            ->delete();
    }

    public function render()
    {
        return view('livewire.laraclaw.memories')->layout('components.laraclaw.layout');
    }
}
