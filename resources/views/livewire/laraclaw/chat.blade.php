<?php

use App\Laraclaw\Facades\Laraclaw;
use App\Models\Conversation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Rule;
use Livewire\Attributes\Session;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;
    #[Session]
    public ?int $conversationId = null;

    #[Rule('required_without:attachments|string|max:4000')]
    public string $message = '';

    #[Rule('array|max:3')]
    #[Rule('attachments.*|file|max:10240')]
    public array $attachments = [];

    public bool $isStreaming = false;

    public string $streamingContent = '';

    public array $streamTools = [];

    public string $streamIntent = '';

    public bool $streaming = true;

    public bool $useMultiAgent = false;

    #[Session]
    public string $aiProvider = '';

    #[Session]
    public string $aiModel = '';

    #[Session]
    public float $aiTemperature = 0.7;

    #[Session]
    public int $aiMaxTokens = 4096;

    public bool $showSettings = false;

    public function mount(): void
    {
        if (! $this->conversationId) {
            $this->startNewConversation();
        }

        $this->aiProvider = $this->aiProvider ?: config('laraclaw.ai.provider', 'openai');
        $this->aiModel = $this->aiModel ?: config('laraclaw.ai.model', 'gpt-4o-mini');
        $this->aiTemperature = $this->aiTemperature ?: (float) config('laraclaw.ai.temperature', 0.7);
        $this->aiMaxTokens = $this->aiMaxTokens ?: (int) config('laraclaw.ai.max_tokens', 4096);
    }

    public function getAvailableProviders(): array
    {
        return [
            ['value' => 'openai', 'label' => 'OpenAI'],
            ['value' => 'anthropic', 'label' => 'Anthropic'],
            ['value' => 'gemini', 'label' => 'Gemini'],
            ['value' => 'ollama', 'label' => 'Ollama'],
            ['value' => 'groq', 'label' => 'Groq'],
            ['value' => 'mistral', 'label' => 'Mistral'],
            ['value' => 'deepseek', 'label' => 'DeepSeek'],
            ['value' => 'xai', 'label' => 'xAI'],
            ['value' => 'openrouter', 'label' => 'OpenRouter'],
            ['value' => 'zai', 'label' => 'ZAI (OpenAI)'],
            ['value' => 'zai-anthropic', 'label' => 'ZAI (Anthropic)'],
        ];
    }

    public function getAvailableModels(): array
    {
        return match ($this->aiProvider) {
            'openai' => [
                ['value' => 'gpt-4o-mini', 'label' => 'GPT-4o Mini'],
                ['value' => 'gpt-4o', 'label' => 'GPT-4o'],
                ['value' => 'o1-mini', 'label' => 'o1 Mini'],
                ['value' => 'o3-mini', 'label' => 'o3 Mini'],
            ],
            'anthropic' => [
                ['value' => 'claude-sonnet-4-20250514', 'label' => 'Claude Sonnet 4'],
                ['value' => 'claude-3-5-haiku-20241022', 'label' => 'Claude 3.5 Haiku'],
            ],
            'gemini' => [
                ['value' => 'gemini-2.0-flash', 'label' => 'Gemini 2.0 Flash'],
                ['value' => 'gemini-1.5-pro', 'label' => 'Gemini 1.5 Pro'],
            ],
            'groq' => [
                ['value' => 'llama-3.3-70b-versatile', 'label' => 'Llama 3.3 70B'],
                ['value' => 'mixtral-8x7b-32768', 'label' => 'Mixtral 8x7B'],
            ],
            'mistral' => [
                ['value' => 'mistral-large-latest', 'label' => 'Mistral Large'],
                ['value' => 'mistral-small-latest', 'label' => 'Mistral Small'],
            ],
            'deepseek' => [
                ['value' => 'deepseek-chat', 'label' => 'DeepSeek Chat'],
                ['value' => 'deepseek-reasoner', 'label' => 'DeepSeek Reasoner'],
            ],
            'zai' => [
                ['value' => 'glm-4-flash', 'label' => 'GLM-4 Flash'],
                ['value' => 'glm-4-plus', 'label' => 'GLM-4 Plus'],
                ['value' => 'glm-4-air', 'label' => 'GLM-4 Air'],
            ],
            'zai-anthropic' => [
                ['value' => 'claude-sonnet-4-20250514', 'label' => 'Claude Sonnet 4'],
                ['value' => 'claude-3-5-haiku-20241022', 'label' => 'Claude 3.5 Haiku'],
            ],
            default => [
                ['value' => $this->aiModel, 'label' => $this->aiModel],
            ],
        };
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

        // Build message text (include attachment names if present)
        $attachmentNames = collect($this->attachments)->map(fn ($f) => $f->getClientOriginalName())->implode(', ');
        $userMessage = $this->message;
        if ($attachmentNames !== '') {
            $userMessage = trim("[Attached: {$attachmentNames}]\n\n{$userMessage}");
        }

        if ($isFirstMessage) {
            $conversation->update([
                'title' => mb_substr($userMessage, 0, 50).(mb_strlen($userMessage) > 50 ? '...' : ''),
            ]);
        }

        // Store attachment metadata
        $attachmentMeta = collect($this->attachments)->map(fn ($f) => [
            'name' => $f->getClientOriginalName(),
            'size' => $f->getSize(),
            'mime' => $f->getMimeType(),
        ])->all();

        $this->message = '';
        $this->attachments = [];
        $this->isStreaming = true;
        $this->streamingContent = '';
        $this->streamTools = [];
        $this->streamIntent = '';

        // Compute intent for display (lightweight pattern matching, no AI call)
        if (config('laraclaw.intent_routing.enabled', true)) {
            $intentResult = app(\App\Laraclaw\Agents\IntentRouter::class)->route($userMessage);
            $this->streamIntent = ucfirst($intentResult['intent'] ?? 'general');
        }

        if ($this->streaming && ! $this->useMultiAgent) {
            $this->dispatch('start-streaming', conversationId: $this->conversationId, message: $userMessage, attachments: $attachmentMeta);

            return;
        }

        $this->getAIResponse($userMessage);
    }

    public function removeAttachment(int $index): void
    {
        unset($this->attachments[$index]);
        $this->attachments = array_values($this->attachments);
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
        $this->streamTools = [];
        $this->streamIntent = '';
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
        $this->streamTools = [];
        $this->streamIntent = '';
    }

    #[On('streaming-chunk')]
    public function handleStreamingChunk(string $chunk): void
    {
        $this->streamingContent .= $chunk;
    }

    public function formatToolName(string $name): string
    {
        return match ($name) {
            'web_search' => 'Searching the web...',
            'calculator' => 'Running calculation...',
            'memory' => 'Accessing memory...',
            'time' => 'Checking time...',
            'calendar' => 'Checking calendar...',
            'app_builder' => 'Building app module...',
            'file_system' => 'Reading files...',
            'shopping_list' => 'Managing shopping list...',
            'email' => 'Handling email...',
            'notification' => 'Sending notification...',
            'http_request' => 'Making HTTP request...',
            'web_fetch' => 'Fetching web content...',
            'scheduler' => 'Scheduling task...',
            'execute' => 'Executing command...',
            default => Str::headline(str_replace(['_', 'Skill'], [' ', ''], $name)),
        };
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
            'providers' => $this->getAvailableProviders(),
            'models' => $this->getAvailableModels(),
        ];
    }

    public function resetSettings(): void
    {
        $this->aiProvider = config('laraclaw.ai.provider', 'openai');
        $this->aiModel = config('laraclaw.ai.model', 'gpt-4o-mini');
        $this->aiTemperature = (float) config('laraclaw.ai.temperature', 0.7);
        $this->aiMaxTokens = (int) config('laraclaw.ai.max_tokens', 4096);
        $this->streaming = true;
        $this->useMultiAgent = false;
    }

    public function exportMarkdown()
    {
        $conversation = $this->conversation;
        if (! $conversation) {
            return;
        }

        $lines = ["# {$conversation->title}", ''];
        foreach ($conversation->messages as $msg) {
            $lines[] = "**{$msg->role}** ({$msg->created_at->format('Y-m-d H:i')})";
            $lines[] = $msg->content;
            $lines[] = '';
        }

        return response()->streamDownload(function () use ($lines) {
            echo implode("\n", $lines);
        }, Str::slug($conversation->title).'.md', ['Content-Type' => 'text/markdown']);
    }

    public function exportJson()
    {
        $conversation = $this->conversation;
        if (! $conversation) {
            return;
        }

        $data = [
            'title' => $conversation->title,
            'created_at' => $conversation->created_at->toIso8601String(),
            'messages' => $conversation->messages->map(fn ($msg) => [
                'role' => $msg->role,
                'content' => $msg->content,
                'created_at' => $msg->created_at->toIso8601String(),
            ]),
        ];

        return response()->streamDownload(function () use ($data) {
            echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }, Str::slug($conversation->title).'.json', ['Content-Type' => 'application/json']);
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

        renderMd(text) {
            if (!text) return '';
            try { return marked.parse(text); } catch { return text; }
        },

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
                            provider: this.$wire.aiProvider,
                            model: this.$wire.aiModel,
                            temperature: this.$wire.aiTemperature,
                            max_tokens: this.$wire.aiMaxTokens,
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
                        const trimmed = line.trim();
                        if (!trimmed || trimmed === 'data: [DONE]') return;

                        const payload = trimmed.startsWith('data:')
                            ? trimmed.substring(5).trimStart()
                            : trimmed;

                        if (payload === '[DONE]') return;

                        // Fallback: old 0: prefix format for text deltas
                        if (payload.startsWith('0:')) {
                            try {
                                const text = JSON.parse(payload.substring(2));
                                if (typeof text === 'string') {
                                    fullContent += text;
                                    this.$wire.streamingContent = fullContent;
                                }
                            } catch (e) {}
                            return;
                        }

                        // Parse JSON event (Vercel UI Message Stream Protocol)
                        let event;
                        try { event = JSON.parse(payload); } catch { return; }
                        if (!event.type) return;

                        switch (event.type) {
                            case 'text-delta':
                                if (typeof event.delta === 'string') {
                                    fullContent += event.delta;
                                    this.$wire.streamingContent = fullContent;
                                }
                                break;
                            case 'tool-input-available':
                                this.$wire.streamTools = [...this.$wire.streamTools, {
                                    id: event.toolCallId,
                                    name: event.toolName || 'unknown',
                                    status: 'running',
                                }];
                                break;
                            case 'tool-output-available': {
                                const tools = [...this.$wire.streamTools];
                                const tool = tools.find(t => t.id === event.toolCallId);
                                if (tool) { tool.status = 'completed'; }
                                this.$wire.streamTools = tools;
                                break;
                            }
                            case 'error':
                                console.error('Stream error:', event.errorText);
                                break;
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

                    this.$wire.dispatch('streaming-complete', { content: fullContent });
                } catch (error) {
                    console.error('Streaming error:', error);
                    this.$wire.handleStreamingError('Sorry, I could not get a response from the selected model. Verify AI_PROVIDER and AI_MODEL compatibility, then try again.');
                }
            });
        }
    });

    window.voiceRecorder = ($wire) => ({
        recording: false,
        mediaRecorder: null,
        chunks: [],

        async toggle() {
            if (this.recording) {
                this.stop();
            } else {
                await this.start();
            }
        },

        async start() {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                this.mediaRecorder = new MediaRecorder(stream, { mimeType: 'audio/webm;codecs=opus' });
                this.chunks = [];

                this.mediaRecorder.ondataavailable = (e) => {
                    if (e.data.size > 0) this.chunks.push(e.data);
                };

                this.mediaRecorder.onstop = async () => {
                    stream.getTracks().forEach(t => t.stop());
                    const blob = new Blob(this.chunks, { type: 'audio/webm' });
                    const formData = new FormData();
                    formData.append('audio', blob, 'recording.webm');

                    try {
                        const resp = await fetch('/laraclaw/voice/transcribe', {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
                            body: formData,
                        });
                        const data = await resp.json();
                        if (data.text) {
                            $wire.message = ($wire.message ? $wire.message + ' ' : '') + data.text;
                        }
                    } catch (err) {
                        console.error('Transcription failed:', err);
                    }
                };

                this.mediaRecorder.start();
                this.recording = true;
            } catch (err) {
                console.error('Microphone access denied:', err);
            }
        },

        stop() {
            if (this.mediaRecorder && this.recording) {
                this.mediaRecorder.stop();
                this.recording = false;
            }
        },
    });

    window.playTTS = async (messageId) => {
        const audio = new Audio(`/laraclaw/voice/speak/${messageId}`);
        audio.play().catch(err => console.error('TTS playback failed:', err));
    };
</script>
@endpush
    <!-- Sidebar -->
    <div class="w-64 min-h-0 bg-gray-800 border-r border-gray-700 flex flex-col">
        <div class="sticky top-0 z-10 p-4 border-b border-gray-700 bg-gray-800">
            <button wire:click="startNewConversation" class="w-full px-4 py-2 bg-indigo-600 hover:bg-indigo-700 rounded-lg font-medium transition">
                + New Chat
            </button>
        </div>

        <div class="flex-1 min-h-0 overflow-y-auto p-2">
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

        <!-- Export & Options -->
        <div class="sticky bottom-0 z-10 p-4 border-t border-gray-700 bg-gray-800 space-y-2">
            @if($conversation)
            <div class="relative" x-data="{ open: false }">
                <button @click="open = !open" class="w-full px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg text-sm font-medium transition flex items-center justify-between">
                    <span class="flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        Export
                    </span>
                    <svg class="w-4 h-4 transition" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>
                <div x-show="open" @click.away="open = false" x-transition class="absolute bottom-full left-0 right-0 mb-1 bg-gray-700 rounded-lg border border-gray-600 overflow-hidden shadow-lg">
                    <button wire:click="exportMarkdown" @click="open = false" class="w-full text-left px-3 py-2 text-sm hover:bg-gray-600 transition">Markdown (.md)</button>
                    <button wire:click="exportJson" @click="open = false" class="w-full text-left px-3 py-2 text-sm hover:bg-gray-600 transition">JSON (.json)</button>
                    <button @click="window.print(); open = false" class="w-full text-left px-3 py-2 text-sm hover:bg-gray-600 transition">Print / PDF</button>
                </div>
            </div>
            @endif
            <button wire:click="$toggle('showSettings')" class="w-full px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg text-sm font-medium transition flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
                Settings
            </button>
        </div>
    </div>

    <!-- Chat Content + Settings Wrapper -->
    <div class="flex-1 flex min-h-0 overflow-hidden">

    <!-- Main Chat Area -->
    <div class="flex-1 flex flex-col min-h-0 overflow-hidden">
        <!-- Input Area -->
        <div class="sticky top-0 z-10 p-4 bg-gray-800 border-b border-gray-700">
            <form wire:submit="sendMessage" class="flex gap-3">
                <div class="flex-1 flex flex-col gap-2">
                    <!-- Attachment preview bar -->
                    @if(count($attachments) > 0)
                        <div class="flex flex-wrap gap-2">
                            @foreach($attachments as $i => $file)
                                <div class="flex items-center gap-1.5 bg-gray-700 rounded-lg px-2.5 py-1.5 text-xs">
                                    <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                                    </svg>
                                    <span class="truncate max-w-[120px]">{{ $file->getClientOriginalName() }}</span>
                                    <button type="button" wire:click="removeAttachment({{ $i }})" class="text-gray-400 hover:text-red-400 shrink-0">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    <div class="flex gap-2 items-end">
                        <label class="cursor-pointer p-2 text-gray-400 hover:text-gray-200 transition {{ $isStreaming ? 'pointer-events-none opacity-50' : '' }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                            </svg>
                            <input type="file" wire:model="attachments" multiple class="hidden" {{ $isStreaming ? 'disabled' : '' }}>
                        </label>
                        <button type="button" x-data="window.voiceRecorder($wire)" @click="toggle()" :class="recording ? 'text-red-400 animate-pulse' : 'text-gray-400 hover:text-gray-200'" class="p-2 transition {{ $isStreaming ? 'pointer-events-none opacity-50' : '' }}" :title="recording ? 'Stop recording' : 'Voice input'">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"></path>
                            </svg>
                        </button>
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
                    </div>
                </div>
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
        <div class="flex-1 min-h-0 overflow-y-auto p-4 space-y-4" x-ref="messagesContainer">
            @if($isStreaming)
                <div class="flex justify-start">
                    <div class="max-w-[80%] bg-gray-700 rounded-xl px-4 py-2">
                        <div class="flex items-center gap-2 text-xs text-gray-400 mb-1">
                            <span class="uppercase">assistant</span>
                            <span class="animate-pulse">{{ $streamingContent ? 'responding...' : 'thinking...' }}</span>
                            @if($streamIntent)
                                <span class="px-1.5 py-0.5 rounded bg-indigo-900/50 text-indigo-300 text-[10px] normal-case tracking-normal">
                                    {{ $streamIntent }}
                                </span>
                            @endif
                        </div>
                        @if(count($streamTools) > 0)
                            <div class="mb-2 space-y-1">
                                @foreach($streamTools as $tool)
                                    <div class="flex items-center gap-2 text-xs py-1 px-2 rounded {{ $tool['status'] === 'running' ? 'bg-gray-600/50' : 'bg-gray-600/30' }}">
                                        @if($tool['status'] === 'running')
                                            <svg class="w-3 h-3 animate-spin text-indigo-400 shrink-0" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                            </svg>
                                        @else
                                            <svg class="w-3 h-3 text-green-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                        @endif
                                        <span class="text-gray-300">{{ $this->formatToolName($tool['name']) }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                        <div class="prose-chat text-sm text-gray-200" x-html="renderMd($wire.streamingContent)"></div>
                        @if(empty($streamingContent) && count($streamTools) === 0)
                            <span class="animate-pulse text-gray-400">|</span>
                        @endif
                    </div>
                </div>
            @endif

            @forelse($this->conversationMessages->reverse()->values() as $msg)
                <div class="flex {{ $msg->role === 'user' ? 'justify-end' : 'justify-start' }}">
                    <div class="max-w-[80%] {{ $msg->role === 'user' ? 'bg-indigo-600' : 'bg-gray-700' }} rounded-xl px-4 py-2">
                        <div class="flex items-center gap-2 text-xs {{ $msg->role === 'user' ? 'text-indigo-200' : 'text-gray-400' }} mb-1">
                            <span class="uppercase">{{ $msg->role }}</span>
                            <span>{{ $msg->created_at->format('M j, H:i') }}</span>
                            @if($msg->role === 'assistant' && filled($msg->metadata['response_mode'] ?? null))
                                <span class="px-1.5 py-0.5 rounded bg-gray-600 text-[10px] text-gray-200 normal-case tracking-normal">
                                    {{ ($msg->metadata['response_mode'] ?? 'single') === 'multi' ? 'Multi-Agent' : 'Single-Agent' }}
                                </span>
                            @endif
                            @if($msg->role === 'assistant' && filled($msg->metadata['intent'] ?? null))
                                <span class="px-1.5 py-0.5 rounded bg-indigo-900/50 text-indigo-300 text-[10px] normal-case tracking-normal">
                                    {{ ucfirst($msg->metadata['intent']) }}
                                </span>
                            @endif
                            @if($msg->role === 'assistant' && filled($msg->metadata['usage'] ?? null))
                                <span class="px-1.5 py-0.5 rounded bg-gray-600 text-[10px] text-gray-200 normal-case tracking-normal">
                                    {{ number_format(($msg->metadata['usage']['prompt_tokens'] ?? 0) + ($msg->metadata['usage']['completion_tokens'] ?? 0)) }} tokens
                                </span>
                            @endif
                            @if($msg->role === 'assistant')
                                <button @click="window.playTTS({{ $msg->id }})" class="ml-auto text-gray-400 hover:text-indigo-400 transition" title="Read aloud">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"></path>
                                    </svg>
                                </button>
                            @endif
                        </div>
                        @if($msg->role === 'assistant')
                            <div class="prose-chat" x-html="renderMd({{ Js::from($msg->content) }})"></div>
                        @else
                            <div class="whitespace-pre-wrap">{{ $msg->content }}</div>
                        @endif
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

        </div>
    </div>

    <!-- Settings Panel -->
    <div x-show="$wire.showSettings" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full" class="w-80 min-h-0 bg-gray-800 border-l border-gray-700 flex flex-col overflow-y-auto">
        <div class="sticky top-0 z-10 p-4 border-b border-gray-700 bg-gray-800 flex justify-between items-center">
            <h3 class="font-semibold text-gray-100">Chat Settings</h3>
            <button wire:click="$toggle('showSettings')" class="text-gray-400 hover:text-gray-300 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <div class="p-4 space-y-5">
            <!-- Provider -->
            <div>
                <label class="block text-xs font-medium text-gray-400 uppercase mb-1.5">AI Provider</label>
                <select wire:model.live="aiProvider" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    @foreach($providers as $p)
                        <option value="{{ $p['value'] }}">{{ $p['label'] }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Model -->
            <div>
                <label class="block text-xs font-medium text-gray-400 uppercase mb-1.5">Model</label>
                <select wire:model="aiModel" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    @foreach($models as $m)
                        <option value="{{ $m['value'] }}">{{ $m['label'] }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Temperature -->
            <div>
                <label class="block text-xs font-medium text-gray-400 uppercase mb-1.5">Temperature: <span x-text="Number($wire.aiTemperature).toFixed(1)"></span></label>
                <input type="range" wire:model.live="aiTemperature" min="0" max="2" step="0.1" class="w-full accent-indigo-600">
                <div class="flex justify-between text-xs text-gray-500 mt-1">
                    <span>Precise</span>
                    <span>Creative</span>
                </div>
            </div>

            <!-- Max Tokens -->
            <div>
                <label class="block text-xs font-medium text-gray-400 uppercase mb-1.5">Max Tokens</label>
                <input type="number" wire:model="aiMaxTokens" min="256" max="128000" step="256" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            </div>

            <hr class="border-gray-700">

            <!-- Streaming -->
            <label class="flex items-center gap-2 text-sm cursor-pointer">
                <input type="checkbox" wire:model="streaming" class="rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500">
                <span>Enable streaming</span>
            </label>

            <!-- Multi-Agent -->
            <label class="flex items-center gap-2 text-sm cursor-pointer">
                <input type="checkbox" wire:model="useMultiAgent" class="rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500">
                <span>Use multi-agent mode</span>
            </label>
            @if($useMultiAgent)
                <p class="text-xs text-gray-500">Multi-agent mode sends non-streaming responses for this message.</p>
            @endif

            <hr class="border-gray-700">

            <!-- Reset -->
            <button wire:click="resetSettings" class="w-full px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg text-sm font-medium transition">
                Reset to Defaults
            </button>
        </div>
    </div>

    </div><!-- end Chat Content + Settings Wrapper -->

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
