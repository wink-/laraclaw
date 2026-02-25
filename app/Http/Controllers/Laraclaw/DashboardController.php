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
use App\Models\MemoryFragment;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Messages\Message as AiMessage;

class DashboardController extends Controller
{
    public function __construct(
        protected MetricsCollector $metrics,
        protected TokenUsageTracker $tokenUsageTracker,
    ) {}

    /**
     * Display the dashboard.
     */
    public function index()
    {
        $stats = $this->getStats();
        $metrics = $this->metrics->getMetrics();
        $recentConversations = $this->getRecentConversations();
        $health = $this->getHealthStatus();

        return view('laraclaw::dashboard', compact('stats', 'metrics', 'recentConversations', 'health'));
    }

    /**
     * Display conversations list.
     */
    public function conversations(Request $request)
    {
        $query = Conversation::query()
            ->with('user')
            ->orderBy('updated_at', 'desc');

        if ($request->gateway) {
            $query->where('gateway', $request->gateway);
        }

        $conversations = $query->paginate(20);

        return view('laraclaw::conversations', compact('conversations'));
    }

    /**
     * Display a single conversation.
     */
    public function showConversation(Conversation $conversation)
    {
        $conversation->load('messages');

        return view('laraclaw::conversation', compact('conversation'));
    }

    /**
     * Display memory fragments.
     */
    public function memories(Request $request)
    {
        $query = MemoryFragment::query()
            ->with('user')
            ->orderBy('created_at', 'desc');

        if ($request->user_id) {
            $query->where('user_id', $request->user_id);
        }

        $memories = $query->paginate(20);

        return view('laraclaw::memories', compact('memories'));
    }

    /**
     * Display metrics.
     */
    public function metrics()
    {
        $metrics = $this->metrics->getMetrics();
        $prometheus = $this->metrics->getPrometheusFormat();

        return view('laraclaw::metrics', compact('metrics', 'prometheus'));
    }

    /**
     * Display chat interface.
     */
    public function chat(Request $request)
    {
        $conversation = null;
        $messages = [];

        if ($request->conversation_id) {
            $conversation = Conversation::with('messages')->find($request->conversation_id);
            if ($conversation) {
                $messages = $conversation->messages;
            }
        }

        if (! $conversation) {
            $conversation = Conversation::create([
                'gateway' => 'web',
                'title' => 'Web Chat',
            ]);
        }

        return view('laraclaw::chat', compact('conversation', 'messages'));
    }

    /**
     * Send a message in the chat.
     */
    public function sendMessage(Request $request)
    {
        $request->validate([
            'conversation_id' => 'required|exists:conversations,id',
            'message' => 'required|string|max:4000',
        ]);

        $conversation = Conversation::findOrFail($request->conversation_id);

        // Get response from Laraclaw
        try {
            $response = \App\Laraclaw\Facades\Laraclaw::chat($conversation, $request->message);

            // Record metrics
            $this->metrics->increment('messages_received');
        } catch (\Exception $e) {
            $conversation->messages()->create([
                'role' => 'assistant',
                'content' => 'Sorry, I encountered an error: '.$e->getMessage(),
            ]);

            $this->metrics->increment('errors');
        }

        return redirect()->route('laraclaw.chat', ['conversation_id' => $conversation->id]);
    }

    /**
     * Stream a chat response (SSE endpoint).
     */
    public function streamMessage(Request $request)
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

        // Return streaming response
        return $agent->stream($request->message)
            ->then(function ($response) use ($conversation, $request) {
                // Store the complete response
                $assistantMessage = $conversation->messages()->create([
                    'role' => 'assistant',
                    'content' => (string) $response,
                ]);

                $this->tokenUsageTracker->record(
                    $conversation,
                    $assistantMessage,
                    $request->string('message')->toString(),
                    (string) $response,
                    ['response_mode' => 'single_stream'],
                );

                ExtractMemoriesJob::dispatch(
                    $conversation->id,
                    $request->string('message')->toString(),
                    (string) $response,
                );
            });
    }

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
            $agent->setAgentKey($intent['intent']);
        } else {
            $agent->setInstructionOverride(null);
            $agent->setAgentKey(null);
        }

        return $agent;
    }

    /**
     * Start a new chat.
     */
    public function newChat()
    {
        return redirect()->route('laraclaw.chat');
    }

    /**
     * Get system statistics.
     */
    protected function getStats(): array
    {
        return [
            'conversations' => Conversation::count(),
            'messages' => Message::count(),
            'memories' => MemoryFragment::count(),
            'users' => DB::table('users')->count(),
            'gateways' => [
                'telegram' => filled(config('laraclaw.gateways.telegram.token')),
                'discord' => filled(config('laraclaw.gateways.discord.token')),
                'whatsapp' => filled(config('laraclaw.gateways.whatsapp.token')),
                'cli' => true,
            ],
        ];
    }

    /**
     * Get recent conversations.
     */
    protected function getRecentConversations(): array
    {
        return Conversation::query()
            ->with('user')
            ->orderBy('updated_at', 'desc')
            ->limit(5)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'title' => $c->title ?? 'Untitled',
                'gateway' => $c->gateway,
                'user' => $c->user?->name ?? 'Anonymous',
                'updated' => $c->updated_at->diffForHumans(),
            ])
            ->all();
    }

    /**
     * Get health status.
     */
    protected function getHealthStatus(): array
    {
        $checks = [];

        // Database check
        try {
            DB::connection()->getPdo();
            $checks['database'] = ['status' => 'healthy', 'message' => 'Connected'];
        } catch (\Throwable $e) {
            $checks['database'] = ['status' => 'unhealthy', 'message' => $e->getMessage()];
        }

        // Cache check
        try {
            Cache::put('laraclaw.health.check', 'ok', 10);
            $value = Cache::get('laraclaw.health.check');
            $checks['cache'] = [
                'status' => $value === 'ok' ? 'healthy' : 'unhealthy',
                'message' => $value === 'ok' ? 'Working' : 'Cache read failed',
            ];
        } catch (\Throwable $e) {
            $checks['cache'] = ['status' => 'unhealthy', 'message' => $e->getMessage()];
        }

        // AI Provider check
        $provider = config('laraclaw.ai.provider', 'openai');
        $hasKey = filled($provider);

        $checks['ai_provider'] = [
            'status' => $hasKey ? 'healthy' : 'degraded',
            'message' => $hasKey ? "Provider: {$provider}" : "Provider: {$provider} (not configured)",
        ];

        // Overall status
        $overallStatus = collect($checks)->every(fn ($c) => $c['status'] === 'healthy')
            ? 'healthy'
            : (collect($checks)->contains('status', 'unhealthy') ? 'unhealthy' : 'degraded');

        $issues = collect($checks)
            ->filter(fn (array $check) => $check['status'] !== 'healthy')
            ->map(fn (array $check, string $name) => ucfirst(str_replace('_', ' ', $name)).': '.$check['message'])
            ->values()
            ->all();

        $details = empty($issues)
            ? 'All checks passed.'
            : implode(' | ', $issues);

        return [
            'status' => $overallStatus,
            'details' => $details,
            'checks' => $checks,
        ];
    }
}
