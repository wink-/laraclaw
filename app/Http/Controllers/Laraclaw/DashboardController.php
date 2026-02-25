<?php

namespace App\Http\Controllers\Laraclaw;

use App\Http\Controllers\Controller;
use App\Jobs\ExtractMemoriesJob;
use App\Laraclaw\Agents\CoreAgent;
use App\Laraclaw\Agents\IntentRouter;
use App\Laraclaw\Facades\Laraclaw as LaraclawFacade;
use App\Laraclaw\Monitoring\MetricsCollector;
use App\Laraclaw\Monitoring\TokenUsageTracker;
use App\Laraclaw\Skills\MemorySkill;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Laravel\Ai\Messages\Message as AiMessage;

class DashboardController extends Controller
{
    public function __construct(
        protected MetricsCollector $metrics,
        protected TokenUsageTracker $tokenUsageTracker,
    ) {}

    /**
     * Stream chat using Vercel AI SDK protocol.
     */
    public function streamVercel(Request $request)
    {
        $request->validate([
            'conversation_id' => 'required|exists:conversations,id',
            'message' => 'required|string|max:4000',
        ]);

        $conversation = Conversation::findOrFail($request->conversation_id);

        // Store user message
        $conversation->messages()->create([
            'role' => 'user',
            'content' => $request->message,
        ]);

        $agent = $this->prepareStreamingAgent($conversation, $request->message);

        // Record metrics
        $this->metrics->increment('messages_received');

        // Return Vercel AI SDK compatible stream
        return $agent->stream($request->message)
            ->usingVercelDataProtocol()
            ->then(function ($response) use ($conversation, $request) {
                // Store the complete response
                $assistantMessage = $conversation->messages()->create([
                    'role' => 'assistant',
                    'content' => (string) $response,
                    'metadata' => [
                        'response_mode' => 'single',
                    ],
                ]);

                $this->tokenUsageTracker->record(
                    $conversation,
                    $assistantMessage,
                    $request->string('message')->toString(),
                    (string) $response,
                    ['response_mode' => 'single_stream_vercel'],
                );

                ExtractMemoriesJob::dispatch(
                    $conversation->id,
                    $request->string('message')->toString(),
                    (string) $response,
                );
            });
    }

    protected function prepareStreamingAgent(Conversation $conversation, string $message): CoreAgent
    {
        app(MemorySkill::class)
            ->forUser($conversation->user_id)
            ->forConversation($conversation->id);

        $memoryManager = LaraclawFacade::memory();
        $agent = LaraclawFacade::agent();

        $history = collect($memoryManager->getConversationContextWithBudget($conversation))
            ->map(fn (array $entry) => new AiMessage($entry['role'], $entry['content']))
            ->all();

        $agent->setConversationHistory($history);

        $memories = $memoryManager->getRelevantMemories($message, $conversation->user_id);
        $memoryContext = $memoryManager->formatMemoriesForPrompt($memories);
        $agent->setMemoryContext($memoryContext !== '' ? $memoryContext : null);

        if (config('laraclaw.intent_routing.enabled', true)) {
            $intent = app(IntentRouter::class)->route($message);
            $agent->setInstructionOverride($intent['specialist_prompt']);
        } else {
            $agent->setInstructionOverride(null);
        }

        return $agent;
    }
}
