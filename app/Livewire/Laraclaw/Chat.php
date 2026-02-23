<?php

namespace App\Livewire\Laraclaw;

use App\Laraclaw\Facades\Laraclaw;
use App\Models\Conversation;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Rule;
use Livewire\Attributes\Session;
use Livewire\Component;

class Chat extends Component
{
    #[Session]
    public ?int $conversationId = null;

    #[Rule('required|string|max:4000')]
    public string $message = '';

    public bool $isStreaming = false;

    public string $streamingContent = '';

    public bool $streaming = true;

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
    public function messages()
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

        // Store user message
        $conversation->messages()->create([
            'role' => 'user',
            'content' => $this->message,
        ]);

        // Update conversation title if first message
        if ($conversation->messages()->count() === 1) {
            $conversation->update([
                'title' => mb_substr($this->message, 0, 50).(mb_strlen($this->message) > 50 ? '...' : ''),
            ]);
        }

        $userMessage = $this->message;
        $this->message = '';
        $this->isStreaming = true;
        $this->streamingContent = '';

        // Get AI response
        $this->getAIResponse($userMessage);
    }

    protected function getAIResponse(string $userMessage): void
    {
        try {
            $conversation = $this->conversation;

            if ($this->streaming) {
                // For streaming, dispatch event that JS will handle
                $this->dispatch('start-streaming', conversationId: $this->conversationId, message: $userMessage);
            } else {
                // Non-streaming response
                $agent = Laraclaw::agent();
                $response = $agent($userMessage);

                $conversation->messages()->create([
                    'role' => 'assistant',
                    'content' => (string) $response,
                ]);

                $this->isStreaming = false;
            }
        } catch (\Exception $e) {
            $this->conversation->messages()->create([
                'role' => 'assistant',
                'content' => 'Sorry, an error occurred: '.$e->getMessage(),
            ]);
            $this->isStreaming = false;
        }
    }

    #[On('streaming-complete')]
    public function handleStreamingComplete(string $content): void
    {
        $this->conversation->messages()->create([
            'role' => 'assistant',
            'content' => $content,
        ]);

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

    public function conversations()
    {
        return Conversation::latest()->limit(20)->get();
    }

    public function render()
    {
        return view('livewire.laraclaw.chat', [
            'conversations' => $this->conversations(),
        ])->layout('components.laraclaw.layout');
    }
}
