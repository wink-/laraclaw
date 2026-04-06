<?php

use App\Laraclaw\Facades\Laraclaw;
use App\Laraclaw\Heartbeat\HeartbeatEngine;
use App\Laraclaw\Monitoring\MetricsCollector;
use App\Laraclaw\Tunnels\TailscaleNetworkManager;
use App\Models\AgentCollaboration;
use App\Models\Conversation;
use App\Models\HeartbeatRun;
use App\Models\LaraclawNotification;
use App\Models\MemoryFragment;
use App\Models\Message;
use App\Models\SkillPlugin;
use App\Models\TokenUsage;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Livewire\Volt\Component;

new class extends Component
{
    public string $activeTab = 'overview';

    public array $stats = [];

    public array $health = [];

    public ?string $marketplaceStatus = null;

    public ?string $schedulerStatus = null;

    public bool $showSkillEditor = false;

    public ?string $editingSkillClass = null;

    public ?string $editingSkillName = null;

    public ?string $editingSkillDescription = null;

    public ?string $editingDefaultDescription = null;

    public bool $editingSkillEnabled = false;

    public bool $editingSkillRequired = false;

    /** @var array<string, mixed> */
    public array $editingSkillMetadata = [];

    /** @var array<string, mixed> */
    public array $editingSchemaFields = [];

    public ?string $skillEditorStatus = null;

    /** @var array<string, mixed> */
    public array $opsSignals = [];

    /** @var array<string, mixed> */
    public array $tokenUsageAnalytics = [];

    /** @var array<int, array<string, mixed>> */
    public array $skills = [];

    /** @var array<int, array<string, mixed>> */
    public array $scheduledTasks = [];

    /** @var array<string, mixed> */
    public array $tailscaleStatus = [];

    /** @var array<int, array<string, mixed>> */
    public array $heartbeatItems = [];

    /** @var array<int, array<string, mixed>> */
    public array $recentHeartbeatRuns = [];

    /** @var array<int, array<string, mixed>> */
    public array $shoppingListItems = [];

    /** @var array<string, int> */
    public array $memoryCategoryCounts = [];

    public function mount(): void
    {
        $this->loadStats();
        $this->loadHealth();
        $this->loadSkills();
        $this->loadScheduledTasks();
        $this->loadOpsSignals();
        $this->loadTokenUsageAnalytics();
        $this->loadTailscaleStatus();
        $this->loadHeartbeat();
        $this->loadShoppingAndMemory();
    }

    public function refresh(): void
    {
        $this->loadStats();
        $this->loadHealth();
        $this->loadOpsSignals();
        $this->loadTokenUsageAnalytics();
        $this->loadTailscaleStatus();
        $this->loadHeartbeat();
    }

    public function setActiveTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    protected function loadStats(): void
    {
        $this->stats = [
            'conversations' => Conversation::count(),
            'messages' => Message::count(),
            'memories' => MemoryFragment::count(),
            'today_conversations' => Conversation::whereDate('created_at', today())->count(),
            'agent_collaborations' => AgentCollaboration::count(),
            'tokens_7d' => TokenUsage::query()->where('created_at', '>=', now()->subDays(7))->sum('total_tokens'),
            'cost_7d' => (float) TokenUsage::query()->where('created_at', '>=', now()->subDays(7))->sum('cost_usd'),
            'pending_notifications' => Schema::hasTable('laraclaw_notifications')
                ? LaraclawNotification::query()->where('status', 'pending')->count()
                : 0,
            'scheduled_tasks' => Schema::hasTable('laraclaw_scheduled_tasks')
                ? DB::table('laraclaw_scheduled_tasks')->count()
                : 0,
        ];
    }

    protected function loadHealth(): void
    {
        $this->health = [
            'database' => 'healthy',
            'ai_provider' => config('laraclaw.ai.provider'),
            'memory' => 'enabled',
        ];
    }

    public function recentConversations(): EloquentCollection
    {
        return Conversation::withCount('messages')
            ->latest()
            ->limit(5)
            ->get();
    }

    public function setSkillEnabled(string $className, bool $enabled): void
    {
        try {
            Laraclaw::setSkillEnabled($className, $enabled);
        } catch (RuntimeException $e) {
            $this->marketplaceStatus = $e->getMessage();

            return;
        }

        $this->loadSkills();
        $this->marketplaceStatus = $enabled
            ? 'Skill enabled successfully.'
            : 'Skill disabled successfully.';
    }

    public function selectSkill(string $className): void
    {
        $detail = Laraclaw::getSkillDetail($className);

        if (! $detail) {
            $this->marketplaceStatus = 'Skill not found.';

            return;
        }

        $this->editingSkillClass = $detail['class_name'];
        $this->editingSkillName = $detail['name'];
        $this->editingSkillDescription = $detail['description'] ?? '';
        $this->editingDefaultDescription = $detail['default_description'] ?? '';
        $this->editingSkillEnabled = $detail['enabled'];
        $this->editingSkillRequired = $detail['is_required'];
        $this->editingSkillMetadata = $detail['metadata'] ?? [];
        $this->editingSchemaFields = $detail['schema_fields'] ?? [];
        $this->skillEditorStatus = null;
        $this->showSkillEditor = true;
    }

    public function closeSkillEditor(): void
    {
        $this->showSkillEditor = false;
        $this->resetEditingSkillState();
    }

    protected function resetEditingSkillState(): void
    {
        $this->editingSkillClass = null;
        $this->editingSkillName = null;
        $this->editingSkillDescription = null;
        $this->editingDefaultDescription = null;
        $this->editingSkillEnabled = false;
        $this->editingSkillRequired = false;
        $this->editingSkillMetadata = [];
        $this->editingSchemaFields = [];
        $this->skillEditorStatus = null;
    }

    public function saveSkillDescription(): void
    {
        if (! $this->editingSkillClass) {
            return;
        }

        $this->validate([
            'editingSkillDescription' => 'nullable|string|max:500',
        ]);

        $description = $this->editingSkillDescription ?: null;

        SkillPlugin::query()
            ->where('class_name', $this->editingSkillClass)
            ->update(['description' => $description]);

        $this->loadSkills();
        $this->skillEditorStatus = 'Description saved successfully.';
    }

    public function resetSkillToDefault(): void
    {
        if (! $this->editingSkillClass) {
            return;
        }

        Laraclaw::resetSkill($this->editingSkillClass);

        $this->editingSkillDescription = null;
        $this->editingSkillMetadata = [];
        $this->loadSkills();
        $this->skillEditorStatus = 'Skill reset to defaults.';
    }

    public function toggleEditingSkillEnabled(): void
    {
        if (! $this->editingSkillClass) {
            return;
        }

        $newState = ! $this->editingSkillEnabled;

        try {
            Laraclaw::setSkillEnabled($this->editingSkillClass, $newState);
            $this->editingSkillEnabled = $newState;
            $this->loadSkills();
            $this->skillEditorStatus = $newState ? 'Skill enabled.' : 'Skill disabled.';
        } catch (RuntimeException $e) {
            $this->skillEditorStatus = $e->getMessage();
        }
    }

    protected function loadSkills(): void
    {
        if (! config('laraclaw.marketplace.enabled', true)) {
            $this->skills = [];

            return;
        }

        $this->skills = Laraclaw::listSkills();
    }

    protected function loadScheduledTasks(): void
    {
        if (! Schema::hasTable('laraclaw_scheduled_tasks')) {
            $this->scheduledTasks = [];

            return;
        }

        $this->scheduledTasks = DB::table('laraclaw_scheduled_tasks')
            ->select(['id', 'action', 'cron_expression', 'is_active', 'last_run_at', 'created_at'])
            ->latest('created_at')
            ->limit(10)
            ->get()
            ->map(fn ($task) => [
                'id' => $task->id,
                'action' => $task->action,
                'cron_expression' => $task->cron_expression,
                'is_active' => (bool) $task->is_active,
                'last_run_at' => $task->last_run_at,
                'created_at' => $task->created_at,
            ])
            ->all();
    }

    public function toggleScheduledTask(int $taskId): void
    {
        if (! Schema::hasTable('laraclaw_scheduled_tasks')) {
            $this->schedulerStatus = 'Scheduled tasks table not found.';

            return;
        }

        $task = DB::table('laraclaw_scheduled_tasks')->where('id', $taskId)->first();
        if (! $task) {
            $this->schedulerStatus = 'Scheduled task not found.';

            return;
        }

        DB::table('laraclaw_scheduled_tasks')
            ->where('id', $taskId)
            ->update([
                'is_active' => ! $task->is_active,
                'updated_at' => now(),
            ]);

        $this->schedulerStatus = $task->is_active
            ? 'Scheduled task paused.'
            : 'Scheduled task resumed.';

        $this->loadScheduledTasks();
        $this->loadStats();
    }

    public function removeScheduledTask(int $taskId): void
    {
        if (! Schema::hasTable('laraclaw_scheduled_tasks')) {
            $this->schedulerStatus = 'Scheduled tasks table not found.';

            return;
        }

        DB::table('laraclaw_scheduled_tasks')->where('id', $taskId)->delete();
        $this->schedulerStatus = 'Scheduled task removed.';

        $this->loadScheduledTasks();
        $this->loadStats();
    }

    public function with(): array
    {
        return [
            'stats' => $this->stats,
            'health' => $this->health,
            'recentConversations' => $this->recentConversations(),
            'skills' => $this->skills,
            'scheduledTasks' => $this->scheduledTasks,
            'opsSignals' => $this->opsSignals,
            'tokenUsageAnalytics' => $this->tokenUsageAnalytics,
            'tailscaleStatus' => $this->tailscaleStatus,
            'heartbeatItems' => $this->heartbeatItems,
            'recentHeartbeatRuns' => $this->recentHeartbeatRuns,
            'shoppingListItems' => $this->shoppingListItems,
            'memoryCategoryCounts' => $this->memoryCategoryCounts,
            'showSkillEditor' => $this->showSkillEditor,
            'editingSkillClass' => $this->editingSkillClass,
            'editingSkillName' => $this->editingSkillName,
            'editingSkillDescription' => $this->editingSkillDescription,
            'editingDefaultDescription' => $this->editingDefaultDescription,
            'editingSkillEnabled' => $this->editingSkillEnabled,
            'editingSkillRequired' => $this->editingSkillRequired,
            'editingSkillMetadata' => $this->editingSkillMetadata,
            'editingSchemaFields' => $this->editingSchemaFields,
            'skillEditorStatus' => $this->skillEditorStatus,
        ];
    }

    protected function loadShoppingAndMemory(): void
    {
        $this->shoppingListItems = MemoryFragment::query()
            ->where('category', 'shopping')
            ->select(['id', 'key', 'content', 'metadata', 'created_at'])
            ->latest('created_at')
            ->limit(8)
            ->get()
            ->map(fn (MemoryFragment $memory) => [
                'id' => $memory->id,
                'list_name' => $memory->key ?: 'groceries',
                'content' => $memory->content,
                'quantity' => data_get($memory->metadata, 'quantity'),
                'created_at' => $memory->created_at?->diffForHumans(),
            ])
            ->all();

        $this->memoryCategoryCounts = MemoryFragment::query()
            ->whereNotNull('category')
            ->select(['category', DB::raw('COUNT(*) as total')])
            ->groupBy('category')
            ->orderByDesc('total')
            ->limit(8)
            ->pluck('total', 'category')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    protected function loadTailscaleStatus(): void
    {
        if (! config('laraclaw.tailscale.enabled', false)) {
            $this->tailscaleStatus = ['enabled' => false];

            return;
        }

        try {
            $manager = app(TailscaleNetworkManager::class);
            $status = $manager->getNetworkStatus();
            $this->tailscaleStatus = array_merge($status, [
                'enabled' => true,
                'serve_active' => $manager->isServeActive(),
                'serve_url' => $manager->getServeUrl(),
            ]);
        } catch (Throwable) {
            $this->tailscaleStatus = ['enabled' => true, 'connected' => false];
        }
    }

    protected function loadHeartbeat(): void
    {
        if (! config('laraclaw.heartbeat.enabled', true)) {
            $this->heartbeatItems = [];
            $this->recentHeartbeatRuns = [];

            return;
        }

        try {
            $engine = app(HeartbeatEngine::class);
            $this->heartbeatItems = $engine->parseHeartbeatFile();
        } catch (Throwable) {
            $this->heartbeatItems = [];
        }

        $this->recentHeartbeatRuns = HeartbeatRun::query()
            ->latest('executed_at')
            ->limit(5)
            ->get()
            ->map(fn ($run) => [
                'heartbeat_id' => $run->heartbeat_id,
                'instruction' => $run->instruction,
                'status' => $run->status,
                'executed_at' => $run->executed_at?->diffForHumans(),
            ])
            ->all();
    }

    protected function loadOpsSignals(): void
    {
        $this->opsSignals = [
            'failed_scheduled_jobs' => $this->countLogMatches([
                'Failed to run scheduled task',
            ]),
            'webhook_failures' => $this->countLogMatches([
                'Telegram webhook error',
                'WhatsApp webhook error',
                'Invalid webhook',
                'Invalid signature',
            ]),
            'api_rate_limited' => $this->countLogMatches([
                'Too Many Requests',
            ]),
            'notifications_failed_24h' => Schema::hasTable('laraclaw_notifications')
                ? LaraclawNotification::query()
                    ->where('status', 'failed')
                    ->where('updated_at', '>=', now()->subDay())
                    ->count()
                : 0,
            'collaborations_total' => AgentCollaboration::count(),
            'collaborations_last_24h' => AgentCollaboration::query()
                ->where('created_at', '>=', now()->subDay())
                ->count(),
            'errors_metric' => (int) app(MetricsCollector::class)
                ->getMetrics()['errors'],
        ];
    }

    protected function loadTokenUsageAnalytics(): void
    {
        if (! Schema::hasTable('token_usages')) {
            $this->tokenUsageAnalytics = [
                'totals' => [
                    'tokens_7d' => 0,
                    'cost_7d' => 0.0,
                ],
                'daily' => [],
                'providers' => [],
                'conversations' => [],
            ];

            return;
        }

        $since = now()->subDays(7);

        $daily = TokenUsage::query()
            ->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as day, SUM(total_tokens) as tokens, SUM(cost_usd) as cost')
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->map(fn (TokenUsage $row) => [
                'day' => (string) $row->day,
                'tokens' => (int) $row->tokens,
                'cost' => (float) $row->cost,
            ])
            ->all();

        $providers = TokenUsage::query()
            ->where('created_at', '>=', $since)
            ->selectRaw('provider, SUM(total_tokens) as tokens, SUM(cost_usd) as cost')
            ->groupBy('provider')
            ->orderByDesc('tokens')
            ->get()
            ->map(fn (TokenUsage $row) => [
                'provider' => $row->provider,
                'tokens' => (int) $row->tokens,
                'cost' => (float) $row->cost,
            ])
            ->all();

        $conversations = TokenUsage::query()
            ->with('conversation:id,title')
            ->where('created_at', '>=', $since)
            ->selectRaw('conversation_id, SUM(total_tokens) as tokens, SUM(cost_usd) as cost')
            ->groupBy('conversation_id')
            ->orderByDesc('tokens')
            ->limit(5)
            ->get()
            ->map(fn (TokenUsage $row) => [
                'conversation_id' => $row->conversation_id,
                'title' => $row->conversation?->title ?: 'Untitled conversation',
                'tokens' => (int) $row->tokens,
                'cost' => (float) $row->cost,
            ])
            ->all();

        $this->tokenUsageAnalytics = [
            'totals' => [
                'tokens_7d' => (int) TokenUsage::query()->where('created_at', '>=', $since)->sum('total_tokens'),
                'cost_7d' => (float) TokenUsage::query()->where('created_at', '>=', $since)->sum('cost_usd'),
            ],
            'daily' => $daily,
            'providers' => $providers,
            'conversations' => $conversations,
        ];
    }

    /**
     * @param  array<int, string>  $patterns
     */
    protected function countLogMatches(array $patterns): int
    {
        $logPath = storage_path('logs/laravel.log');

        if (! is_file($logPath)) {
            return 0;
        }

        $lines = @file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if (! is_array($lines) || empty($lines)) {
            return 0;
        }

        $lines = array_slice($lines, -2000);
        $threshold = now()->subDay();
        $count = 0;

        foreach ($lines as $line) {
            $timestamp = $this->extractTimestamp($line);
            if ($timestamp && $timestamp->lt($threshold)) {
                continue;
            }

            foreach ($patterns as $pattern) {
                if (str_contains($line, $pattern)) {
                    $count++;

                    break;
                }
            }
        }

        return $count;
    }

    protected function extractTimestamp(string $line): ?Carbon
    {
        if (! preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $matches)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d H:i:s', $matches[1]);
        } catch (Throwable) {
            return null;
        }
    }

    public function rendering(View $view): void
    {
        $view->layout('components.laraclaw.layout');
    }
}; ?>

<div class="space-y-6" wire:poll.30s="refresh">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-100">Dashboard</h1>
            <p class="text-gray-400">Monitor your Laraclaw instance</p>
        </div>
        <a href="{{ route('laraclaw.chat.live') }}" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 rounded-lg font-medium transition">
            Open Chat
        </a>
    </div>

    <div class="border-b border-gray-700">
        <nav class="flex gap-6 -mb-px" role="tablist">
            @foreach([
                'overview' => 'Overview',
                'analytics' => 'Analytics',
                'infrastructure' => 'Infrastructure',
                'management' => 'Management',
            ] as $tab => $label)
                <button
                    wire:click="setActiveTab('{{ $tab }}')"
                    role="tab"
                    class="px-1 py-3 text-sm font-medium border-b-2 transition whitespace-nowrap {{ $activeTab === $tab ? 'border-indigo-500 text-indigo-400' : 'border-transparent text-gray-400 hover:text-gray-200 hover:border-gray-500' }}"
                >
                    {{ $label }}
                </button>
            @endforeach
        </nav>
    </div>

    @if($activeTab === 'overview')
        <div class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-400 text-sm">Total Conversations</p>
                            <p class="text-3xl font-bold text-gray-100">{{ $stats['conversations'] }}</p>
                        </div>
                        <div class="w-12 h-12 bg-indigo-600/20 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-400 text-sm">Total Messages</p>
                            <p class="text-3xl font-bold text-gray-100">{{ $stats['messages'] }}</p>
                        </div>
                        <div class="w-12 h-12 bg-green-600/20 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"></path>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-400 text-sm">Memory Fragments</p>
                            <p class="text-3xl font-bold text-gray-100">{{ $stats['memories'] }}</p>
                        </div>
                        <div class="w-12 h-12 bg-purple-600/20 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"></path>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-400 text-sm">Today's Chats</p>
                            <p class="text-3xl font-bold text-gray-100">{{ $stats['today_conversations'] }}</p>
                        </div>
                        <div class="w-12 h-12 bg-yellow-600/20 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-400 text-sm">Agent Collaborations</p>
                            <p class="text-3xl font-bold text-gray-100">{{ $stats['agent_collaborations'] }}</p>
                        </div>
                        <div class="w-12 h-12 bg-cyan-600/20 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5V4H2v16h5m10 0v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6m10 0H7"></path>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-400 text-sm">Scheduled Tasks</p>
                            <p class="text-3xl font-bold text-gray-100">{{ $stats['scheduled_tasks'] }}</p>
                        </div>
                        <div class="w-12 h-12 bg-emerald-600/20 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10m-11 9h12a2 2 0 002-2V7a2 2 0 00-2-2H6a2 2 0 00-2 2v11a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-400 text-sm">Tokens (7d)</p>
                            <p class="text-3xl font-bold text-gray-100">{{ number_format($stats['tokens_7d']) }}</p>
                        </div>
                        <div class="w-12 h-12 bg-blue-600/20 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6M7 4h10a2 2 0 012 2v12a2 2 0 01-2 2H7a2 2 0 01-2-2V6a2 2 0 012-2z"></path>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-400 text-sm">Estimated Cost (7d)</p>
                            <p class="text-3xl font-bold text-gray-100">${{ number_format($stats['cost_7d'], 3) }}</p>
                        </div>
                        <div class="w-12 h-12 bg-rose-600/20 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-2.21 0-4 1.79-4 4m8 0a4 4 0 00-4-4m0 0V5m0 15v-3m-7-5H3m18 0h-2"></path>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-400 text-sm">Pending Notifications</p>
                            <p class="text-3xl font-bold text-gray-100">{{ $stats['pending_notifications'] }}</p>
                        </div>
                        <div class="w-12 h-12 bg-orange-600/20 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V4a2 2 0 10-4 0v1.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0a3 3 0 11-6 0h6z"></path>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
                    <h2 class="text-lg font-semibold text-gray-100 mb-4">System Health</h2>
                    <div class="space-y-3">
                        @foreach($health as $key => $value)
                            <div class="flex items-center justify-between py-2 border-b border-gray-700 last:border-0">
                                <span class="text-gray-400 capitalize">{{ str_replace('_', ' ', $key) }}</span>
                                <span class="px-2 py-1 text-xs rounded-full {{ $value === 'healthy' || $value === 'enabled' ? 'bg-green-600/20 text-green-400' : 'bg-gray-700 text-gray-300' }}">
                                    {{ $value }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
                    <h2 class="text-lg font-semibold text-gray-100 mb-4">Recent Conversations</h2>
                    <div class="space-y-2">
                        @forelse($recentConversations as $conv)
                            <div class="flex items-center justify-between py-2 px-3 rounded-lg hover:bg-gray-700/50 transition">
                                <div>
                                    <p class="text-gray-200">{{ $conv->title }}</p>
                                    <p class="text-xs text-gray-500">{{ $conv->messages_count }} messages</p>
                                </div>
                                <span class="text-xs text-gray-500">{{ $conv->created_at->diffForHumans() }}</span>
                            </div>
                        @empty
                            <p class="text-gray-500 text-center py-4">No conversations yet</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
                    <h2 class="text-lg font-semibold text-gray-100 mb-4">Shopping List Agent</h2>
                    @if(empty($shoppingListItems))
                        <p class="text-sm text-gray-500">No shopping items yet. Ask the assistant to add items to your list.</p>
                    @else
                        <div class="space-y-2">
                            @foreach($shoppingListItems as $item)
                                <div class="flex items-center justify-between py-2 px-3 rounded-lg bg-gray-700/40 text-sm">
                                    <div class="min-w-0">
                                        <p class="text-gray-200 truncate">{{ $item['content'] }}</p>
                                        <p class="text-xs text-gray-500">List: {{ $item['list_name'] }}</p>
                                    </div>
                                    <div class="text-right shrink-0 ml-3">
                                        <p class="text-xs text-gray-400">{{ $item['quantity'] ?: '1x' }}</p>
                                        <p class="text-xs text-gray-500">{{ $item['created_at'] }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
                    <h2 class="text-lg font-semibold text-gray-100 mb-4">Memory Categories</h2>
                    @if(empty($memoryCategoryCounts))
                        <p class="text-sm text-gray-500">No categorized memories yet. Ask the assistant to remember something.</p>
                    @else
                        <div class="space-y-2">
                            @foreach($memoryCategoryCounts as $category => $count)
                                <div class="flex items-center justify-between py-2 px-3 rounded-lg bg-gray-700/40">
                                    <span class="text-sm text-gray-200 capitalize">{{ str_replace('_', ' ', $category) }}</span>
                                    <span class="px-2 py-1 text-xs rounded-full bg-indigo-600/20 text-indigo-300">{{ $count }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    @if($activeTab === 'analytics')
        <div class="space-y-6">
            <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
                <h2 class="text-lg font-semibold text-gray-100 mb-4">Token Usage Analytics (7d)</h2>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <div class="bg-gray-700/40 rounded-lg p-4">
                        <h3 class="text-sm text-gray-300 mb-3">Daily Usage</h3>
                        @forelse($tokenUsageAnalytics['daily'] ?? [] as $entry)
                            <div class="flex items-center justify-between text-xs py-1 border-b border-gray-700/60 last:border-0">
                                <span class="text-gray-400">{{ $entry['day'] }}</span>
                                <span class="text-gray-200">{{ number_format($entry['tokens']) }} &bull; ${{ number_format($entry['cost'], 4) }}</span>
                            </div>
                        @empty
                            <p class="text-xs text-gray-500">No token usage recorded in the last 7 days.</p>
                        @endforelse
                    </div>

                    <div class="bg-gray-700/40 rounded-lg p-4">
                        <h3 class="text-sm text-gray-300 mb-3">By Provider</h3>
                        @forelse($tokenUsageAnalytics['providers'] ?? [] as $entry)
                            <div class="flex items-center justify-between text-xs py-1 border-b border-gray-700/60 last:border-0">
                                <span class="text-gray-400">{{ $entry['provider'] }}</span>
                                <span class="text-gray-200">{{ number_format($entry['tokens']) }} &bull; ${{ number_format($entry['cost'], 4) }}</span>
                            </div>
                        @empty
                            <p class="text-xs text-gray-500">No provider breakdown available yet.</p>
                        @endforelse
                    </div>

                    <div class="bg-gray-700/40 rounded-lg p-4">
                        <h3 class="text-sm text-gray-300 mb-3">Top Conversations</h3>
                        @forelse($tokenUsageAnalytics['conversations'] ?? [] as $entry)
                            <div class="py-1 border-b border-gray-700/60 last:border-0">
                                <p class="text-xs text-gray-300 truncate">{{ $entry['title'] }}</p>
                                <p class="text-xs text-gray-500">{{ number_format($entry['tokens']) }} tokens &bull; ${{ number_format($entry['cost'], 4) }}</p>
                            </div>
                        @empty
                            <p class="text-xs text-gray-500">No conversation breakdown available yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
                <h2 class="text-lg font-semibold text-gray-100 mb-4">Ops Signals (24h)</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-3">
                    <div class="py-3 px-3 rounded-lg bg-gray-700/40">
                        <p class="text-xs text-gray-500">Failed Scheduled Jobs</p>
                        <p class="text-xl font-semibold {{ ($opsSignals['failed_scheduled_jobs'] ?? 0) > 0 ? 'text-red-300' : 'text-gray-100' }}">
                            {{ $opsSignals['failed_scheduled_jobs'] ?? 0 }}
                        </p>
                    </div>
                    <div class="py-3 px-3 rounded-lg bg-gray-700/40">
                        <p class="text-xs text-gray-500">Webhook Failures</p>
                        <p class="text-xl font-semibold {{ ($opsSignals['webhook_failures'] ?? 0) > 0 ? 'text-red-300' : 'text-gray-100' }}">
                            {{ $opsSignals['webhook_failures'] ?? 0 }}
                        </p>
                    </div>
                    <div class="py-3 px-3 rounded-lg bg-gray-700/40">
                        <p class="text-xs text-gray-500">Collaborations (24h)</p>
                        <p class="text-xl font-semibold text-gray-100">{{ $opsSignals['collaborations_last_24h'] ?? 0 }}</p>
                    </div>
                    <div class="py-3 px-3 rounded-lg bg-gray-700/40">
                        <p class="text-xs text-gray-500">Errors Metric</p>
                        <p class="text-xl font-semibold {{ ($opsSignals['errors_metric'] ?? 0) > 0 ? 'text-yellow-300' : 'text-gray-100' }}">
                            {{ $opsSignals['errors_metric'] ?? 0 }}
                        </p>
                    </div>
                    <div class="py-3 px-3 rounded-lg bg-gray-700/40">
                        <p class="text-xs text-gray-500">API Rate Limited</p>
                        <p class="text-xl font-semibold {{ ($opsSignals['api_rate_limited'] ?? 0) > 0 ? 'text-yellow-300' : 'text-gray-100' }}">
                            {{ $opsSignals['api_rate_limited'] ?? 0 }}
                        </p>
                    </div>
                    <div class="py-3 px-3 rounded-lg bg-gray-700/40">
                        <p class="text-xs text-gray-500">Notification Failures</p>
                        <p class="text-xl font-semibold {{ ($opsSignals['notifications_failed_24h'] ?? 0) > 0 ? 'text-red-300' : 'text-gray-100' }}">
                            {{ $opsSignals['notifications_failed_24h'] ?? 0 }}
                        </p>
                    </div>
                </div>
                <p class="mt-3 text-xs text-gray-500">Total collaborations recorded: {{ $opsSignals['collaborations_total'] ?? 0 }}</p>
            </div>
        </div>
    @endif

    @if($activeTab === 'infrastructure')
        <div class="space-y-6">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
                    <h2 class="text-lg font-semibold text-gray-100 mb-4">Tailscale Network</h2>
                    @if(!($tailscaleStatus['enabled'] ?? false))
                        <p class="text-sm text-gray-500">Tailscale networking is disabled. Set <code class="text-xs bg-gray-700 px-1.5 py-0.5 rounded">LARACLAW_TAILSCALE_ENABLED=true</code> to enable.</p>
                    @elseif(!($tailscaleStatus['connected'] ?? false))
                        <div class="flex items-center gap-2 text-yellow-400 mb-3">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span class="text-sm font-medium">Not connected to Tailnet</span>
                        </div>
                        <p class="text-xs text-gray-500">Run <code class="bg-gray-700 px-1.5 py-0.5 rounded">tailscale up</code> to connect.</p>
                    @else
                        <div class="space-y-3">
                            <div class="flex items-center gap-2 text-green-400 mb-2">
                                <div class="w-2 h-2 rounded-full bg-green-400 animate-pulse"></div>
                                <span class="text-sm font-medium">Connected to {{ $tailscaleStatus['tailnet_name'] ?? 'tailnet' }}</span>
                            </div>
                            <div class="grid grid-cols-2 gap-2 text-sm">
                                <div class="bg-gray-700/40 px-3 py-2 rounded">
                                    <p class="text-xs text-gray-500">Hostname</p>
                                    <p class="text-gray-200 truncate">{{ $tailscaleStatus['self']['hostname'] ?? '-' }}</p>
                                </div>
                                <div class="bg-gray-700/40 px-3 py-2 rounded">
                                    <p class="text-xs text-gray-500">Serve</p>
                                    <p class="text-gray-200">{{ ($tailscaleStatus['serve_active'] ?? false) ? 'Active' : 'Inactive' }}</p>
                                </div>
                            </div>
                            @if(!empty($tailscaleStatus['self']['tailscale_ips'] ?? []))
                                <div class="bg-gray-700/40 px-3 py-2 rounded text-sm">
                                    <p class="text-xs text-gray-500 mb-1">IPs</p>
                                    @foreach($tailscaleStatus['self']['tailscale_ips'] as $ip)
                                        <span class="inline-block mr-2 text-gray-300">{{ $ip }}</span>
                                    @endforeach
                                </div>
                            @endif
                            @if(count($tailscaleStatus['peers'] ?? []) > 0)
                                <div>
                                    <p class="text-xs text-gray-500 mb-1">Peers ({{ count($tailscaleStatus['peers']) }})</p>
                                    <div class="space-y-1 max-h-32 overflow-y-auto">
                                        @foreach($tailscaleStatus['peers'] as $peer)
                                            <div class="flex items-center justify-between text-xs bg-gray-700/30 px-2 py-1 rounded">
                                                <span class="text-gray-300">{{ $peer['hostname'] }}</span>
                                                <span class="{{ $peer['online'] ? 'text-green-400' : 'text-gray-500' }}">
                                                    {{ $peer['online'] ? 'online' : 'offline' }}
                                                </span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
                    <h2 class="text-lg font-semibold text-gray-100 mb-4">Heartbeat Engine</h2>
                    @if(empty($heartbeatItems))
                        <p class="text-sm text-gray-500">No heartbeat tasks found. Create <code class="text-xs bg-gray-700 px-1.5 py-0.5 rounded">storage/laraclaw/HEARTBEAT.md</code> to add autonomous tasks.</p>
                    @else
                        <div class="space-y-2 mb-4">
                            @foreach($heartbeatItems as $item)
                                <div class="flex items-center gap-3 text-sm bg-gray-700/40 px-3 py-2 rounded">
                                    <span class="{{ $item['enabled'] ? 'text-green-400' : 'text-gray-500' }}">
                                        {{ $item['enabled'] ? '&bull;' : '&cir;' }}
                                    </span>
                                    <span class="text-gray-200 flex-1 truncate">{{ $item['instruction'] }}</span>
                                    <span class="text-xs text-gray-500 whitespace-nowrap">{{ $item['schedule'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if(!empty($recentHeartbeatRuns))
                        <h3 class="text-sm font-semibold text-gray-300 mb-2 mt-4">Recent Runs</h3>
                        <div class="space-y-1">
                            @foreach($recentHeartbeatRuns as $run)
                                <div class="flex items-center justify-between text-xs bg-gray-700/30 px-2 py-1.5 rounded">
                                    <span class="text-gray-300 truncate mr-2">{{ $run['instruction'] }}</span>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <span class="{{ $run['status'] === 'success' ? 'text-green-400' : 'text-red-400' }}">
                                            {{ $run['status'] }}
                                        </span>
                                        <span class="text-gray-500">{{ $run['executed_at'] }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    @if($activeTab === 'management')
        <div class="space-y-6" x-data="{ editorOpen: false }">
            <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
                <h2 class="text-lg font-semibold text-gray-100 mb-4">Skill Marketplace</h2>

                @if($marketplaceStatus)
                    <p class="text-sm text-gray-300 mb-4">{{ $marketplaceStatus }}</p>
                @endif

                @if(empty($skills))
                    <div class="flex flex-col items-center py-8 text-gray-500">
                        <svg class="w-12 h-12 mb-3 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                        </svg>
                        <p class="text-sm">No skills registered yet.</p>
                    </div>
                @else
                    <div class="space-y-2">
                        @foreach($skills as $skill)
                            <div
                                class="flex items-center justify-between gap-4 py-3 px-4 rounded-lg bg-gray-700/40 cursor-pointer hover:bg-gray-700/70 transition-colors duration-150 group"
                                wire:click="selectSkill(@js($skill['class_name']))"
                                x-on:click="editorOpen = true"
                            >
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <p class="text-gray-200 text-sm font-medium">{{ $skill['name'] }}</p>
                                        @if(($skill['is_required'] ?? false) && $skill['enabled'])
                                            <span class="px-2 py-0.5 text-xs rounded bg-gray-600 text-gray-300">Required</span>
                                        @elseif(!$skill['enabled'])
                                            <span class="px-2 py-0.5 text-xs rounded bg-gray-700 text-gray-500">Disabled</span>
                                        @else
                                            <span class="px-2 py-0.5 text-xs rounded bg-indigo-600/20 text-indigo-400">Active</span>
                                        @endif
                                    </div>
                                    @if($skill['description'])
                                        <p class="text-xs text-gray-400 mt-0.5 truncate">{{ $skill['description'] }}</p>
                                    @endif
                                </div>
                                <svg class="w-4 h-4 text-gray-500 group-hover:text-gray-300 transition-colors shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
                <h2 class="text-lg font-semibold text-gray-100 mb-4">Scheduled Tasks</h2>

                @if($schedulerStatus)
                    <p class="text-sm text-gray-300 mb-4">{{ $schedulerStatus }}</p>
                @endif

                @if(empty($scheduledTasks))
                    <p class="text-sm text-gray-500">No scheduled tasks yet.</p>
                @else
                    <div class="space-y-2">
                        @foreach($scheduledTasks as $task)
                            <div class="py-3 px-3 rounded-lg bg-gray-700/40 space-y-2">
                                <div class="flex items-center justify-between gap-4">
                                    <div class="min-w-0">
                                        <p class="text-gray-200 text-sm truncate">{{ $task['action'] }}</p>
                                        <p class="text-xs text-gray-500">{{ $task['cron_expression'] }}</p>
                                    </div>
                                    <span class="px-2 py-1 text-xs rounded-full {{ $task['is_active'] ? 'bg-green-600/20 text-green-400' : 'bg-gray-700 text-gray-300' }}">
                                        {{ $task['is_active'] ? 'Active' : 'Paused' }}
                                    </span>
                                </div>

                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-xs text-gray-500">
                                        Last run: {{ $task['last_run_at'] ? \Illuminate\Support\Carbon::parse($task['last_run_at'])->diffForHumans() : 'Never' }}
                                    </p>

                                    <div class="flex items-center gap-2">
                                        <button
                                            wire:click="toggleScheduledTask({{ $task['id'] }})"
                                            class="px-3 py-1.5 text-xs rounded-lg {{ $task['is_active'] ? 'bg-yellow-600/20 text-yellow-300 hover:bg-yellow-600/30' : 'bg-green-600/20 text-green-300 hover:bg-green-600/30' }}"
                                        >
                                            {{ $task['is_active'] ? 'Pause' : 'Resume' }}
                                        </button>
                                        <button
                                            wire:click="removeScheduledTask({{ $task['id'] }})"
                                            class="px-3 py-1.5 text-xs rounded-lg bg-red-600/20 text-red-300 hover:bg-red-600/30"
                                        >
                                            Remove
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @endif

    <div
        x-show="$wire.showSkillEditor"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="translate-x-full opacity-0"
        x-transition:enter-end="translate-x-0 opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="translate-x-0 opacity-100"
        x-transition:leave-end="translate-x-full opacity-0"
        x-cloak
        class="fixed top-0 right-0 h-full w-96 bg-gray-800 border-l border-gray-700 shadow-2xl z-50 flex flex-col"
    >
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-700">
            <h3 class="text-base font-semibold text-gray-100 truncate">{{ $editingSkillName }}</h3>
            <button
                wire:click="closeSkillEditor"
                class="p-1.5 rounded-lg hover:bg-gray-700 text-gray-400 hover:text-gray-200 transition-colors"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        @if($skillEditorStatus)
            @php
                $editorStatusColors = match (true) {
                    str_contains($skillEditorStatus, 'success'),
                    str_contains($skillEditorStatus, 'saved'),
                    str_contains($skillEditorStatus, 'enabled') => 'bg-green-600/20 text-green-300',
                    str_contains($skillEditorStatus, 'error'),
                    str_contains($skillEditorStatus, 'cannot') => 'bg-red-600/20 text-red-300',
                    default => 'bg-indigo-600/20 text-indigo-300',
                };
            @endphp
            <div class="mx-5 mt-4 px-3 py-2 rounded-lg text-sm {{ $editorStatusColors }}">
                {{ $skillEditorStatus }}
            </div>
        @endif

        <div class="flex-1 overflow-y-auto px-5 py-4 space-y-5">
            <div>
                <label class="text-xs font-medium text-gray-500 uppercase tracking-wide">Class</label>
                <p class="text-xs text-gray-400 mt-1 font-mono break-all">{{ $editingSkillClass }}</p>
            </div>

            @if($editingDefaultDescription)
                <div>
                    <label class="text-xs font-medium text-gray-500 uppercase tracking-wide">Default Description</label>
                    <p class="text-sm text-gray-400 mt-1 bg-gray-700/30 px-3 py-2 rounded-lg">{{ $editingDefaultDescription }}</p>
                </div>
            @endif

            <div>
                <label class="text-xs font-medium text-gray-500 uppercase tracking-wide">Custom Description</label>
                <p class="text-xs text-gray-500 mt-0.5 mb-1.5">Overrides the default when set. Leave blank to use the default.</p>
                <textarea
                    wire:model="editingSkillDescription"
                    rows="3"
                    placeholder="Enter a custom description..."
                    class="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-sm text-gray-100 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent resize-none"
                ></textarea>
                @error('editingSkillDescription')
                    <p class="text-xs text-red-400 mt-1">{{ $message }}</p>
                @enderror
                <button
                    wire:click="saveSkillDescription"
                    wire:loading.attr="disabled"
                    class="mt-2 w-full px-3 py-2 text-sm font-medium rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white transition-colors disabled:opacity-50"
                >
                    <span wire:loading.remove wire:target="saveSkillDescription">Save Description</span>
                    <span wire:loading wire:target="saveSkillDescription">Saving...</span>
                </button>
            </div>

            @if(!empty($editingSchemaFields))
                <div>
                    <label class="text-xs font-medium text-gray-500 uppercase tracking-wide">Parameters</label>
                    <div class="mt-1.5 space-y-1.5">
                        @foreach($editingSchemaFields as $paramName => $paramDef)
                            <div class="flex items-center gap-2 text-xs bg-gray-700/30 px-3 py-1.5 rounded">
                                <span class="font-mono text-indigo-400">{{ $paramName }}</span>
                                @if($paramDef->type ?? null)
                                    <span class="text-gray-500">{{ $paramDef->type }}</span>
                                @endif
                                @if(method_exists($paramDef, 'isRequired') && $paramDef->isRequired())
                                    <span class="text-yellow-400 text-[10px] uppercase">required</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div>
                <label class="text-xs font-medium text-gray-500 uppercase tracking-wide">Status</label>
                <div class="flex items-center justify-between mt-1.5 bg-gray-700/30 px-3 py-2.5 rounded-lg">
                    <span class="text-sm {{ $editingSkillEnabled ? 'text-green-400' : 'text-gray-400' }}">
                        {{ $editingSkillEnabled ? 'Enabled' : 'Disabled' }}
                    </span>
                    @if($editingSkillRequired && $editingSkillEnabled)
                        <span class="px-2 py-0.5 text-xs rounded bg-gray-600 text-gray-300">Required</span>
                    @else
                        <button
                            type="button"
                            wire:click="toggleEditingSkillEnabled"
                            class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:ring-offset-gray-800 {{ $editingSkillEnabled ? 'bg-indigo-600' : 'bg-gray-600' }}"
                        >
                            <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $editingSkillEnabled ? 'translate-x-5' : 'translate-x-0' }}"></span>
                        </button>
                    @endif
                </div>
            </div>
        </div>

        <div class="px-5 py-4 border-t border-gray-700">
            <button
                wire:click="resetSkillToDefault"
                wire:confirm="Reset this skill's custom description and metadata to defaults?"
                class="w-full px-3 py-2 text-sm font-medium rounded-lg bg-gray-700 hover:bg-gray-600 text-gray-300 transition-colors"
            >
                Reset to Default
            </button>
        </div>
    </div>

    <div
        x-show="$wire.showSkillEditor"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        x-cloak
        class="fixed inset-0 bg-black/50 z-40"
        wire:click="closeSkillEditor"
    ></div>
</div>
