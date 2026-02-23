<?php

namespace App\Laraclaw;

use App\Laraclaw\Agents\CoreAgent;
use App\Laraclaw\Memory\MemoryManager;
use App\Models\Conversation;

class Laraclaw
{
    public function __construct(
        protected MemoryManager $memory,
        protected CoreAgent $agent,
    ) {}

    /**
     * Get the memory manager instance.
     */
    public function memory(): MemoryManager
    {
        return $this->memory;
    }

    /**
     * Get the core agent instance.
     */
    public function agent(): CoreAgent
    {
        return $this->agent;
    }

    /**
     * Start a new conversation.
     */
    public function startConversation(?int $userId = null, string $gateway = 'cli', ?string $title = null): Conversation
    {
        return Conversation::create([
            'user_id' => $userId,
            'gateway' => $gateway,
            'title' => $title,
        ]);
    }

    /**
     * Send a message and get a response.
     */
    public function chat(Conversation $conversation, string $message): string
    {
        // Store the user message
        $conversation->messages()->create([
            'role' => 'user',
            'content' => $message,
        ]);

        // Get conversation history and user memories
        $history = $this->memory->getConversationHistory($conversation);
        $memories = $this->memory->getRelevantMemories($message, $conversation->user_id);
        $memoryContext = $this->memory->formatMemoriesForPrompt($memories);

        // Prompt the agent with context
        $response = $this->agent->promptWithContext($message, $history, $memoryContext);

        // Store the assistant response
        $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $response,
        ]);

        return $response;
    }

    /**
     * Quick chat helper - starts a new conversation and sends a message.
     */
    public function ask(string $message, ?int $userId = null): string
    {
        $conversation = $this->startConversation($userId);

        return $this->chat($conversation, $message);
    }
}
