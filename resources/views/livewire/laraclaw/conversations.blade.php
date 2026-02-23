<div class="space-y-6">
    <!-- Header -->
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-100">Conversations</h1>
            <p class="text-gray-400">Browse and manage conversation history</p>
        </div>
        <a href="{{ route('laraclaw.chat.live') }}" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 rounded-lg font-medium transition">
            New Chat
        </a>
    </div>

    <!-- Filters -->
    <div class="flex gap-4">
        <div class="flex-1">
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Search conversations..."
                class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent placeholder-gray-500"
            >
        </div>
        <select
            wire:model.live="gateway"
            class="bg-gray-800 border border-gray-700 rounded-lg px-4 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
        >
            <option value="">All Gateways</option>
            <option value="web">Web</option>
            <option value="telegram">Telegram</option>
            <option value="discord">Discord</option>
            <option value="cli">CLI</option>
        </select>
    </div>

    <!-- Conversations List -->
    <div class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-700/50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Title</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Gateway</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Messages</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Created</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-400 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-700">
                @forelse($this->conversations as $conversation)
                    <tr class="hover:bg-gray-700/30 transition">
                        <td class="px-4 py-3">
                            <span class="text-gray-200">{{ $conversation->title }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 text-xs rounded-full bg-gray-700 text-gray-300 capitalize">
                                {{ $conversation->gateway }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-400">
                            {{ $conversation->messages_count }}
                        </td>
                        <td class="px-4 py-3 text-gray-400 text-sm">
                            {{ $conversation->created_at->diffForHumans() }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button
                                wire:click="delete({{ $conversation->id }})"
                                wire:confirm="Are you sure you want to delete this conversation?"
                                class="text-gray-400 hover:text-red-400 transition"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                            @if($search || $gateway)
                                No conversations match your filters.
                            @else
                                No conversations yet. Start a <a href="{{ route('laraclaw.chat.live') }}" class="text-indigo-400 hover:underline">new chat</a>.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <!-- Pagination -->
        @if($this->conversations->hasPages())
            <div class="px-4 py-3 border-t border-gray-700">
                {{ $this->conversations->links() }}
            </div>
        @endif
    </div>
</div>
