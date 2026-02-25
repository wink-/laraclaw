<div class="space-y-6">
    <!-- Header -->
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-100">Memory Fragments</h1>
            <p class="text-gray-400">Long-term memories stored for context</p>
        </div>
    </div>

    <!-- Filters -->
    <div class="max-w-2xl grid grid-cols-1 md:grid-cols-2 gap-3">
        <input
            type="text"
            wire:model.live.debounce.300ms="search"
            placeholder="Search memories..."
            class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent placeholder-gray-500"
        >
        <select
            wire:model.live="category"
            class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
        >
            <option value="">All categories</option>
            @foreach($this->categories as $categoryOption)
                <option value="{{ $categoryOption }}">{{ ucfirst($categoryOption) }}</option>
            @endforeach
        </select>
    </div>

    <!-- Memories Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse($this->memories as $memory)
            <div class="bg-gray-800 rounded-xl border border-gray-700 p-4 group">
                <div class="flex justify-between items-start mb-2">
                    <div class="flex items-center gap-2">
                        @if($memory->key)
                            <span class="px-2 py-1 text-xs rounded-full bg-indigo-600/20 text-indigo-400">
                                {{ $memory->key }}
                            </span>
                        @endif
                        @if($memory->category)
                            <span class="px-2 py-1 text-xs rounded-full bg-gray-700 text-gray-300">
                                {{ $memory->category }}
                            </span>
                        @endif
                    </div>
                    <button
                        wire:click="delete({{ $memory->id }})"
                        wire:confirm="Delete this memory?"
                        class="opacity-0 group-hover:opacity-100 text-gray-400 hover:text-red-400 transition"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <p class="text-gray-300 text-sm line-clamp-3">{{ $memory->content }}</p>
                <div class="mt-3 text-xs text-gray-500">
                    {{ $memory->created_at->diffForHumans() }}
                </div>
            </div>
        @empty
            <div class="col-span-full text-center py-12 text-gray-500">
                @if($search)
                    No memories match your search.
                @else
                    No memories stored yet.
                @endif
            </div>
        @endforelse
    </div>

    <!-- Pagination -->
    @if($this->memories->hasPages())
        <div class="mt-6">
            {{ $this->memories->links() }}
        </div>
    @endif
</div>
