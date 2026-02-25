<?php

use App\Models\Conversation;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

new class extends Component {
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

    public function exportMarkdown(int $id): StreamedResponse
    {
        $conversation = Conversation::with('messages')->findOrFail($id);

        $markdown = "# {$conversation->title}\n\n";
        $markdown .= "> Gateway: {$conversation->gateway}\n";
        $markdown .= "> Created: {$conversation->created_at->format('Y-m-d H:i:s')}\n\n";
        $markdown .= "---\n\n";

        foreach ($conversation->messages as $message) {
            $role = ucfirst($message->role);
            $time = $message->created_at->format('H:i:s');
            $markdown .= "### {$role} ({$time})\n\n";
            $markdown .= $message->content."\n\n";
        }

        $filename = str_replace(' ', '-', strtolower($conversation->title)).'.md';

        return response()->streamDownload(function () use ($markdown): void {
            echo $markdown;
        }, $filename, [
            'Content-Type' => 'text/markdown',
        ]);
    }

    public function exportJson(int $id): StreamedResponse
    {
        $conversation = Conversation::with('messages')->findOrFail($id);

        $data = [
            'id' => $conversation->id,
            'title' => $conversation->title,
            'gateway' => $conversation->gateway,
            'created_at' => $conversation->created_at->toIso8601String(),
            'messages' => $conversation->messages->map(fn ($m) => [
                'role' => $m->role,
                'content' => $m->content,
                'created_at' => $m->created_at->toIso8601String(),
            ]),
        ];

        $filename = str_replace(' ', '-', strtolower($conversation->title)).'.json';

        return response()->streamDownload(function () use ($data): void {
            echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }, $filename, [
            'Content-Type' => 'application/json',
        ]);
    }

    public function rendering(View $view): void
    {
        $view->layout('components.laraclaw.layout');
    }
}; ?>

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
                            <div class="flex justify-end gap-2">
                                <!-- Export Dropdown -->
                                <div class="relative" x-data="{ open: false }">
                                    <button @click="open = !open" class="text-gray-400 hover:text-indigo-400 transition">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                                        </svg>
                                    </button>
                                    <div x-show="open" @click.away="open = false" x-cloak class="absolute right-0 mt-2 w-40 bg-gray-700 rounded-lg shadow-lg border border-gray-600 py-1 z-10">
                                        <button wire:click="exportMarkdown({{ $conversation->id }})" class="w-full text-left px-4 py-2 text-sm text-gray-300 hover:bg-gray-600 transition">
                                            Export as Markdown
                                        </button>
                                        <button wire:click="exportJson({{ $conversation->id }})" class="w-full text-left px-4 py-2 text-sm text-gray-300 hover:bg-gray-600 transition">
                                            Export as JSON
                                        </button>
                                    </div>
                                </div>
                                <!-- Delete -->
                                <button
                                    wire:click="delete({{ $conversation->id }})"
                                    wire:confirm="Are you sure you want to delete this conversation?"
                                    class="text-gray-400 hover:text-red-400 transition"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                </button>
                            </div>
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
