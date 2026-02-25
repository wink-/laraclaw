<?php

use App\Laraclaw\Facades\Laraclaw;
use App\Models\Conversation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Rule;
use Livewire\Attributes\Session;
use Livewire\Volt\Component;

new class extends Component {
    #[Session]
    public ?int $conversationId = null;

    #[Rule('required|string|max:4000')]
    public string $message = '';

    public bool $isStreaming = false;

    public string $streamingContent = '';

    public bool $streaming = true;

    public bool $useMultiAgent = false;

    public function mount(): void
    {
        if (! $this->conversationId) {
            $this->startNewConversation();
        }
    }

    #[Computed]
    public function conversation(): ?Conversation
    {
        return Conversation::with('messages')->find($this->conversationId);
    }

    #[Computed]
    public function conversationMessages(): Collection
    {
        return $this->conversation?->messages ?? collect();
    }

    public function sendMessage(): void
    {
        $this->validate();

        $conversation = $this->conversation;

        if (! $conversation) {
            $this->startNewConversation();
            $conversation = $this->conversation;
        }

        $isFirstMessage = $conversation->messages()->count() === 0;

        if ($isFirstMessage) {
            $conversation->update([
                'title' => mb_substr($this->message, 0, 50).(mb_strlen($this->message) > 50 ? '...' : ''),
            ]);
        }

        $userMessage = $this->message;
        $this->message = '';
        $this->isStreaming = true;
        $this->streamingContent = '';

        if ($this->streaming && ! $this->useMultiAgent) {
            $this->dispatch('start-streaming', conversationId: $this->conversationId, message: $userMessage);

            return;
        }

        $this->getAIResponse($userMessage);
    }

    protected function getAIResponse(string $userMessage): void
    {
        try {
            $conversation = $this->conversation;

            Laraclaw::chat($conversation, $userMessage, $this->useMultiAgent);

            $this->isStreaming = false;
        } catch (\Exception $e) {
            $this->conversation->messages()->create([
                'role' => 'assistant',
                'content' => 'Sorry, an error occurred: '.$e->getMessage(),
            ]);
            $this->isStreaming = false;
        }
    }

    #[On('streaming-complete')]
    public function handleStreamingComplete(): void
    {
        $this->isStreaming = false;
        $this->streamingContent = '';
    }

    public function handleStreamingError(string $error): void
    {
        if ($this->conversation) {
            $this->conversation->messages()->create([
                'role' => 'assistant',
                'content' => $error,
            ]);
        }

        $this->isStreaming = false;
        $this->streamingContent = '';
    }

    #[On('streaming-chunk')]
    public function handleStreamingChunk(string $chunk): void
    {
        $this->streamingContent .= $chunk;
    }

    public function startNewConversation(): void
    {
        $conversation = Conversation::create([
            'user_id' => Auth::id(),
            'title' => 'New Chat',
            'gateway' => 'web',
        ]);

        $this->conversationId = $conversation->id;
        $this->reset('message', 'isStreaming', 'streamingContent');
    }

    public function loadConversation(int $id): void
    {
        $this->conversationId = $id;
        $this->reset('message', 'isStreaming', 'streamingContent');
    }

    public function deleteConversation(int $id): void
    {
        if ($this->conversationId === $id) {
            $this->startNewConversation();
        }

        Conversation::destroy($id);
    }

    public function conversations(): Collection
    {
        return Conversation::latest()->limit(10)->get();
    }

    public function with(): array
    {
        return [
            'conversations' => $this->conversations(),
        ];
    }

    public function rendering(View $view): void
    {
        $view->layout('components.laraclaw.layout');
    }
}; ?>

<div class="flex h-full min-h-0 overflow-hidden bg-gray-900 text-gray-100" x-data="window.chatComponent()" @keydown.window.ctrl.s.prevent @keydown.window.ctrl.n.prevent="$wire.startNewConversation()" @keydown.window.escape="$wire.message = ''">
@push('scripts')
<script>
    window.chatComponent = () => ({
        showShortcuts: false,

        init() {
            // Focus input on '/' key
            document.addEventListener('keydown', (e) => {
                if (e.key === '/' && !['INPUT', 'TEXTAREA'].includes(document.activeElement.tagName)) {
                    e.preventDefault();
                    this.$refs.messageInput?.focus();
                }
                if (e.ctrlKey && e.key === '/') {
                    e.preventDefault();
                    this.showShortcuts = true;
                }
            });

            // Handle streaming via SSE
            this.$wire.on('start-streaming', async ({ conversationId, message }) => {
                try {
                    const response = await fetch('/laraclaw/chat/stream-vercel', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'text/plain',
                        },
                        body: JSON.stringify({
                            conversation_id: conversationId,
                            message: message,
                        }),
                    });

                    if (!response.ok) {
                        const errorBody = await response.text();
                        throw new Error(errorBody || `Streaming request failed with status ${response.status}`);
                    }

                    if (!response.body) {
                        throw new Error('Streaming response body was empty.');
                    }

                    const reader = response.body.getReader();
                    const decoder = new TextDecoder();
                    let fullContent = '';
                    let buffer = '';

                    const processLine = (line) => {
                        if (!line.startsWith('0:"')) {
                            return;
                        }

                        try {
                            const text = JSON.parse(line.substring(2));
                            fullContent += text;
                            this.$wire.streamingContent = fullContent;
                        } catch (error) {
                            console.error('Failed to parse streaming chunk:', error, line);
                        }
                    };

                    while (true) {
                        const { done, value } = await reader.read();
                        if (done) {
                            break;
                        }

                        const chunk = decoder.decode(value, { stream: true });
                        buffer += chunk;
                        const lines = buffer.split('\n');
                        buffer = lines.pop() ?? '';

                        for (const line of lines) {
                            processLine(line);
                        }
                    }

                    if (buffer !== '') {
                        processLine(buffer);
                    }

                    if (fullContent.trim() === '') {
                        throw new Error('The selected model returned no content.');
                    }

                    this.$wire.dispatch('streaming-complete', { content: fullContent });
                } catch (error) {
                    console.error('Streaming error:', error);
                    this.$wire.handleStreamingError('Sorry, I could not get a response from the selected model. Verify AI_PROVIDER and AI_MODEL compatibility, then try again.');
                }
            });
        }
    });
</script>
@endpush
    <!-- Sidebar -->
    <div class="w-64 bg-gray-800 border-r border-gray-700 flex flex-col">
        <div class="p-4 border-b border-gray-700">
            <button wire:click="startNewConversation" class="w-full px-4 py-2 bg-indigo-600 hover:bg-indigo-700 rounded-lg font-medium transition">
                + New Chat
            </button>
        </div>

        <div class="flex-1 overflow-y-auto overscroll-contain p-2">
            @foreach($conversations as $conv)
                <div
                    wire:click="loadConversation({{ $conv->id }})"
                    class="w-full text-left px-3 py-2 rounded-lg mb-1 text-sm {{ $conversationId === $conv->id ? 'bg-gray-700' : 'hover:bg-gray-700/50' }} transition group cursor-pointer"
                >
                    <div class="flex justify-between items-center">
                        <span class="truncate">{{ $conv->title }}</span>
                        <button
                            wire:click.stop="deleteConversation({{ $conv->id }})"
                            wire:confirm="Delete this conversation?"
                            class="opacity-0 group-hover:opacity-100 text-red-400 hover:text-red-300 transition"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                        </button>
                    </div>
                    <div class="text-xs text-gray-500 mt-1">{{ $conv->created_at->diffForHumans() }}</div>
                </div>
            @endforeach
        </div>

        <!-- Options -->
        <div class="p-4 border-t border-gray-700 space-y-2">
            <label class="flex items-center gap-2 text-sm cursor-pointer">
                <input type="checkbox" wire:model="streaming" class="rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500">
                <span>Enable streaming</span>
            </label>
            <label class="flex items-center gap-2 text-sm cursor-pointer">
                <input type="checkbox" wire:model="useMultiAgent" class="rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500">
                <span>Use multi-agent mode</span>
            </label>
            @if($useMultiAgent)
                <p class="text-xs text-gray-500">Multi-agent mode sends non-streaming responses for this message.</p>
            @endif
        </div>
    </div>

    <!-- Main Chat Area -->
    <div class="flex-1 flex flex-col min-h-0 overflow-hidden">
        <!-- Input Area -->
        <div class="p-4 bg-gray-800 border-b border-gray-700">
            <form wire:submit="sendMessage" class="flex gap-3">
                <textarea
                    wire:model="message"
                    x-ref="messageInput"
                    placeholder="Type your message... (Shift+Enter for newline)"
                    class="flex-1 bg-gray-700 border border-gray-600 rounded-xl px-4 py-3 resize-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent placeholder-gray-400"
                    rows="1"
                    x-data
                    x-init="
                        $el.style.height = 'auto';
                        $el.addEventListener('input', function() {
                            this.style.height = 'auto';
                            this.style.height = Math.min(this.scrollHeight, 150) + 'px';
                        });
                    "
                    @keydown.enter.prevent="$event.shiftKey ? null : $wire.sendMessage()"
                    {{ $isStreaming ? 'disabled' : '' }}
                ></textarea>
                <button
                    type="submit"
                    class="px-6 py-3 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed rounded-xl font-medium transition flex items-center gap-2"
                    {{ $isStreaming ? 'disabled' : '' }}
                >
                    @if($isStreaming)
                        <svg class="animate-spin h-5 w-5" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                    @else
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                        </svg>
                    @endif
                    Send
                </button>
            </form>

            <!-- Keyboard shortcuts hint -->
            <div class="mt-2 text-xs text-gray-500 flex gap-4 items-center">
                <span><kbd class="px-1.5 py-0.5 bg-gray-700 rounded text-gray-400">Enter</kbd> send</span>
                <span><kbd class="px-1.5 py-0.5 bg-gray-700 rounded text-gray-400">Shift+Enter</kbd> newline</span>
                <span><kbd class="px-1.5 py-0.5 bg-gray-700 rounded text-gray-400">Ctrl+N</kbd> new chat</span>
                <span><kbd class="px-1.5 py-0.5 bg-gray-700 rounded text-gray-400">Esc</kbd> clear</span>
                <button @click="showShortcuts = true" class="ml-auto text-indigo-400 hover:text-indigo-300"><kbd class="px-1.5 py-0.5 bg-gray-700 rounded text-gray-400">Ctrl+/</kbd> shortcuts</button>
            </div>
        </div>

        <!-- Messages -->
        <div class="flex-1 min-h-0 overflow-y-auto overscroll-contain p-4 space-y-4" x-ref="messagesContainer">
            @forelse($this->conversationMessages as $msg)
                <div class="flex {{ $msg->role === 'user' ? 'justify-end' : 'justify-start' }}">
                    <div class="max-w-[80%] {{ $msg->role === 'user' ? 'bg-indigo-600' : 'bg-gray-700' }} rounded-xl px-4 py-3">
                        <div class="text-xs {{ $msg->role === 'user' ? 'text-indigo-200' : 'text-gray-400' }} uppercase mb-1">
                            {{ $msg->role }}
                            @if($msg->role === 'assistant' && filled($msg->metadata['response_mode'] ?? null))
                                <span class="ml-2 px-1.5 py-0.5 rounded bg-gray-600 text-[10px] text-gray-200 normal-case tracking-normal">
                                    {{ ($msg->metadata['response_mode'] ?? 'single') === 'multi' ? 'Multi-Agent' : 'Single-Agent' }}
                                </span>
                            @endif
                        </div>
                        <div class="whitespace-pre-wrap">{{ $msg->content }}</div>
                        <div class="text-xs {{ $msg->role === 'user' ? 'text-indigo-200' : 'text-gray-500' }} mt-2">
                            {{ $msg->created_at->format('M j, g:i A') }}
                        </div>
                    </div>
                </div>
            @empty
                <div class="flex items-center justify-center h-full text-gray-500">
                    <div class="text-center">
                        <svg class="w-16 h-16 mx-auto mb-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                        </svg>
                        <h3 class="text-lg font-medium text-gray-400">Start a conversation</h3>
                        <p class="mt-2">Ask me anything! I can help with time, calculations, web searches, and more.</p>
                    </div>
                </div>
            @endforelse

            <!-- Streaming message placeholder -->
            @if($isStreaming)
                <div class="flex justify-start">
                    <div class="max-w-[80%] bg-gray-700 rounded-xl px-4 py-3">
                        <div class="text-xs text-gray-400 uppercase mb-1">assistant</div>
                        <div class="whitespace-pre-wrap">
                            <span x-text="$wire.streamingContent"></span>
                            <span class="animate-pulse">|</span>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Keyboard Shortcuts Modal -->
    <div x-show="showShortcuts" x-cloak class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="showShortcuts = false" @keydown.escape.window="showShortcuts = false">
        <div class="bg-gray-800 rounded-xl border border-gray-700 p-6 max-w-md w-full mx-4">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-gray-100">Keyboard Shortcuts</h3>
                <button @click="showShortcuts = false" class="text-gray-400 hover:text-gray-300">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between py-2 border-b border-gray-700">
                    <span class="text-gray-400">Send message</span>
                    <kbd class="px-2 py-1 bg-gray-700 rounded text-gray-300">Enter</kbd>
                </div>
                <div class="flex justify-between py-2 border-b border-gray-700">
                    <span class="text-gray-400">New line</span>
                    <span><kbd class="px-2 py-1 bg-gray-700 rounded text-gray-300">Shift</kbd> + <kbd class="px-2 py-1 bg-gray-700 rounded text-gray-300">Enter</kbd></span>
                </div>
                <div class="flex justify-between py-2 border-b border-gray-700">
                    <span class="text-gray-400">New conversation</span>
                    <span><kbd class="px-2 py-1 bg-gray-700 rounded text-gray-300">Ctrl</kbd> + <kbd class="px-2 py-1 bg-gray-700 rounded text-gray-300">N</kbd></span>
                </div>
                <div class="flex justify-between py-2 border-b border-gray-700">
                    <span class="text-gray-400">Clear input</span>
                    <kbd class="px-2 py-1 bg-gray-700 rounded text-gray-300">Escape</kbd>
                </div>
                <div class="flex justify-between py-2 border-b border-gray-700">
                    <span class="text-gray-400">Focus input</span>
                    <kbd class="px-2 py-1 bg-gray-700 rounded text-gray-300">/</kbd>
                </div>
                <div class="flex justify-between py-2">
                    <span class="text-gray-400">Close modal</span>
                    <kbd class="px-2 py-1 bg-gray-700 rounded text-gray-300">Escape</kbd>
                </div>
                <div class="flex justify-between py-2 border-t border-gray-700 pt-3">
                    <span class="text-gray-400">Show shortcuts</span>
                    <span><kbd class="px-2 py-1 bg-gray-700 rounded text-gray-300">Ctrl</kbd> + <kbd class="px-2 py-1 bg-gray-700 rounded text-gray-300">/</kbd></span>
                </div>
            </div>
        </div>
    </div>

</div>
