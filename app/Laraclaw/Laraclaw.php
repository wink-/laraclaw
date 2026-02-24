<?php

namespace App\Laraclaw;

use App\Laraclaw\Agents\CoreAgent;
use App\Laraclaw\Agents\MultiAgentOrchestrator;
use App\Laraclaw\Memory\MemoryManager;
use App\Laraclaw\Skills\PluginManager;
use App\Models\Conversation;

class Laraclaw
{
    public function __construct(
        protected MemoryManager $memory,
        protected CoreAgent $agent,
        protected MultiAgentOrchestrator $orchestrator,
        protected PluginManager $plugins,
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
    public function chat(Conversation $conversation, string $message, ?bool $useMultiAgent = null): string
    {
        // Store the user message
        $conversation->messages()->create([
            'role' => 'user',
            'content' => $message,
        ]);

        $shouldUseMultiAgent = $useMultiAgent ?? config('laraclaw.multi_agent.enabled', false);
        $responseMode = $shouldUseMultiAgent ? 'multi' : 'single';

        if ($shouldUseMultiAgent) {
            $response = $this->orchestrator->collaborate($conversation, $message);
        } else {
            // Get conversation history and user memories
            $history = $this->memory->getConversationHistory($conversation);
            $memories = $this->memory->getRelevantMemories($message, $conversation->user_id);
            $memoryContext = $this->memory->formatMemoriesForPrompt($memories);

            // Prompt the agent with context
            $response = $this->agent->promptWithContext($message, $history, $memoryContext);
        }

        // Store the assistant response
        $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $response,
            'metadata' => [
                'response_mode' => $responseMode,
            ],
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

    public function listSkills(): array
    {
        return $this->plugins->listSkills();
    }

    public function setSkillEnabled(string $className, bool $enabled): void
    {
        $this->plugins->setEnabled($className, $enabled);
    }
}
