<?php

namespace App\Laraclaw\Agents;

use App\Laraclaw\Memory\MemoryManager;
use App\Models\AgentCollaboration;
use App\Models\Conversation;

class MultiAgentOrchestrator
{
    public function __construct(
        protected CoreAgent $agent,
        protected MemoryManager $memory,
    ) {}

    public function collaborate(Conversation $conversation, string $message): string
    {
        $history = $this->memory->getConversationHistory($conversation);
        $memories = $this->memory->getRelevantMemories($message, $conversation->user_id);
        $memoryContext = $this->memory->formatMemoriesForPrompt($memories);

        $plannerOutput = $this->agent->promptWithContext(
            "You are the planning agent. Produce a concise execution plan for this user request:\n\n{$message}",
            $history,
            $memoryContext,
            null,
            'planner'
        );

        $executorOutput = $this->agent->promptWithContext(
            "You are the execution agent. Execute this plan to answer the user.\n\nPlan:\n{$plannerOutput}\n\nUser request:\n{$message}",
            $history,
            $memoryContext,
            null,
            'executor'
        );

        $reviewerOutput = $this->agent->promptWithContext(
            "You are the review agent. Improve correctness, safety, and clarity for this draft answer:\n\n{$executorOutput}",
            $history,
            $memoryContext,
            null,
            'reviewer'
        );

        AgentCollaboration::create([
            'conversation_id' => $conversation->id,
            'user_message' => $message,
            'planner_output' => $plannerOutput,
            'executor_output' => $executorOutput,
            'reviewer_output' => $reviewerOutput,
            'final_output' => $reviewerOutput,
        ]);

        return $reviewerOutput;
    }
}
