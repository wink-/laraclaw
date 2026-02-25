<?php

namespace App\Laraclaw;

use App\Jobs\ExtractMemoriesJob;
use App\Laraclaw\Agents\CoreAgent;
use App\Laraclaw\Agents\IntentRouter;
use App\Laraclaw\Agents\MultiAgentOrchestrator;
use App\Laraclaw\Memory\MemoryManager;
use App\Laraclaw\Monitoring\TokenUsageTracker;
use App\Laraclaw\Skills\MemorySkill;
use App\Laraclaw\Skills\PluginManager;
use App\Models\Conversation;

class Laraclaw
{
    public function __construct(
        protected MemoryManager $memory,
        protected CoreAgent $agent,
        protected IntentRouter $intentRouter,
        protected MultiAgentOrchestrator $orchestrator,
        protected PluginManager $plugins,
        protected TokenUsageTracker $tokenUsageTracker,
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

        app(MemorySkill::class)
            ->forUser($conversation->user_id)
            ->forConversation($conversation->id);

        $shouldUseMultiAgent = $useMultiAgent ?? config('laraclaw.multi_agent.enabled', false);
        $responseMode = $shouldUseMultiAgent ? 'multi' : 'single';
        $intent = config('laraclaw.intent_routing.enabled', true)
            ? $this->intentRouter->route($message)
            : ['intent' => 'general', 'specialist_prompt' => null];

        if ($shouldUseMultiAgent) {
            $response = $this->orchestrator->collaborate($conversation, $message);
        } else {
            // Get conversation history and user memories
            $history = $this->memory->getConversationContextWithBudget($conversation);
            $memories = $this->memory->getRelevantMemories($message, $conversation->user_id);
            $memoryContext = $this->memory->formatMemoriesForPrompt($memories);

            // Prompt the agent with context
            $response = $this->agent->promptWithContext(
                $message,
                $history,
                $memoryContext,
                $intent['specialist_prompt'],
                $intent['intent']
            );
        }

        // Store the assistant response
        $assistantMessage = $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $response,
            'metadata' => [
                'response_mode' => $responseMode,
                'intent' => $intent['intent'],
            ],
        ]);

        $this->tokenUsageTracker->record(
            $conversation,
            $assistantMessage,
            $message,
            $response,
            [
                'response_mode' => $responseMode,
                'intent' => $intent['intent'],
            ],
        );

        ExtractMemoriesJob::dispatch($conversation->id, $message, $response);

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
