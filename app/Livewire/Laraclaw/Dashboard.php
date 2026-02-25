<?php

namespace App\Livewire\Laraclaw;

use App\Laraclaw\Facades\Laraclaw;
use App\Laraclaw\Heartbeat\HeartbeatEngine;
use App\Laraclaw\Modules\ModuleManager;
use App\Laraclaw\Skills\AppBuilderSkill;
use App\Laraclaw\Storage\FileStorageService;
use App\Laraclaw\Storage\VectorStoreService;
use App\Laraclaw\Tunnels\TailscaleNetworkManager;
use App\Models\AgentCollaboration;
use App\Models\Conversation;
use App\Models\LaraclawDocument;
use App\Models\LaraclawNotification;
use App\Models\MemoryFragment;
use App\Models\TokenUsage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\WithFileUploads;

class Dashboard extends Component
{
    use WithFileUploads;

    public array $stats = [];

    public array $health = [];

    public $document;

    public ?string $uploadStatus = null;

    public ?string $marketplaceStatus = null;

    public ?string $schedulerStatus = null;

    public ?string $moduleStatus = null;

    public string $newModuleName = '';

    public string $newModuleDescription = '';

    /**
     * @var array<string, mixed>
     */
    public array $opsSignals = [];

    /**
     * @var array<string, mixed>
     */
    public array $tokenUsageAnalytics = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $skills = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $scheduledTasks = [];

    /**
     * @var array<string, mixed>
     */
    public array $tailscaleStatus = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $heartbeatItems = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $recentHeartbeatRuns = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $shoppingListItems = [];

    /**
     * @var array<string, int>
     */
    public array $memoryCategoryCounts = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $modules = [];

    /**
     * @var array<string, string>
     */
    public array $moduleDomainInputs = [];

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
        $this->loadModules();
    }

    protected function loadStats(): void
    {
        $this->stats = [
            'conversations' => Conversation::count(),
            'messages' => \App\Models\Message::count(),
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

    public function recentConversations()
    {
        return Conversation::withCount('messages')
            ->latest()
            ->limit(5)
            ->get();
    }

    public function recentDocuments()
    {
        return LaraclawDocument::query()
            ->latest()
            ->limit(5)
            ->get();
    }

    public function ingestDocument(FileStorageService $fileStorage, VectorStoreService $vectorStore): void
    {
        $this->validate([
            'document' => ['required', 'file', 'mimes:pdf,txt,doc,docx,md', 'max:10240'],
        ]);

        $uploadedFile = $this->document;
        $originalName = $uploadedFile->getClientOriginalName();
        $storedPath = $uploadedFile->store('laraclaw/documents', 'local');
        $absolutePath = storage_path('app/private/'.$storedPath);

        $documentRecord = LaraclawDocument::create([
            'user_id' => Auth::id(),
            'original_name' => $originalName,
            'stored_path' => $storedPath,
            'mime_type' => $uploadedFile->getClientMimeType(),
            'size' => $uploadedFile->getSize(),
            'indexed' => false,
        ]);

        try {
            $providerDocument = $fileStorage->storeDocument($absolutePath, $originalName);
            $store = $vectorStore->getDefaultStore();
            $indexed = $vectorStore->addDocument($store['id'], $providerDocument['id']);

            $documentRecord->update([
                'provider_file_id' => $providerDocument['id'],
                'vector_store_id' => $store['id'],
                'indexed' => $indexed,
                'error_message' => $indexed ? null : 'Document uploaded but indexing failed.',
            ]);

            $this->uploadStatus = $indexed
                ? "Indexed document: {$originalName}"
                : "Uploaded document: {$originalName}, but indexing failed.";
        } catch (\Throwable $e) {
            $documentRecord->update([
                'indexed' => false,
                'error_message' => $e->getMessage(),
            ]);

            $this->uploadStatus = "Upload failed for {$originalName}: {$e->getMessage()}";
        }

        $this->reset('document');
    }

    public function setSkillEnabled(string $className, bool $enabled): void
    {
        try {
            Laraclaw::setSkillEnabled($className, $enabled);
        } catch (\RuntimeException $e) {
            $this->marketplaceStatus = $e->getMessage();

            return;
        }

        $this->loadSkills();
        $this->marketplaceStatus = $enabled
            ? 'Skill enabled successfully.'
            : 'Skill disabled successfully.';
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

    public function render()
    {
        return view('livewire.laraclaw.dashboard', [
            'stats' => $this->stats,
            'health' => $this->health,
            'recentConversations' => $this->recentConversations(),
            'recentDocuments' => $this->recentDocuments(),
            'skills' => $this->skills,
            'scheduledTasks' => $this->scheduledTasks,
            'opsSignals' => $this->opsSignals,
            'tokenUsageAnalytics' => $this->tokenUsageAnalytics,
            'tailscaleStatus' => $this->tailscaleStatus,
            'heartbeatItems' => $this->heartbeatItems,
            'recentHeartbeatRuns' => $this->recentHeartbeatRuns,
            'shoppingListItems' => $this->shoppingListItems,
            'memoryCategoryCounts' => $this->memoryCategoryCounts,
            'modules' => $this->modules,
            'moduleStatus' => $this->moduleStatus,
            'moduleDomainInputs' => $this->moduleDomainInputs,
        ])->layout('components.laraclaw.layout');
    }

    public function createModuleApp(): void
    {
        $this->validate([
            'newModuleName' => ['required', 'string', 'max:120'],
            'newModuleDescription' => ['nullable', 'string', 'max:255'],
        ]);

        $builder = app(AppBuilderSkill::class);

        $this->moduleStatus = $builder->execute([
            'action' => 'create_app',
            'name' => $this->newModuleName,
            'description' => $this->newModuleDescription,
            'type' => 'blog',
        ]);

        $this->newModuleName = '';
        $this->newModuleDescription = '';

        $this->loadModules();
    }

    public function bindModuleDomain(string $slug): void
    {
        $builder = app(AppBuilderSkill::class);
        $domain = trim($this->moduleDomainInputs[$slug] ?? '');

        $this->moduleStatus = $builder->execute([
            'action' => 'set_domain',
            'slug' => $slug,
            'domain' => $domain,
        ]);

        $this->loadModules();
    }

    protected function loadModules(): void
    {
        if (! config('laraclaw.modules.enabled', true)) {
            $this->modules = [];

            return;
        }

        $manager = app(ModuleManager::class);

        $this->modules = $manager->allModules();

        foreach ($this->modules as $module) {
            $this->moduleDomainInputs[$module['slug']] = (string) ($module['domain'] ?? '');
        }
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
        } catch (\Throwable) {
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
        } catch (\Throwable) {
            $this->heartbeatItems = [];
        }

        $this->recentHeartbeatRuns = \App\Models\HeartbeatRun::query()
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
            'errors_metric' => (int) app(\App\Laraclaw\Monitoring\MetricsCollector::class)
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
        } catch (\Throwable) {
            return null;
        }
    }
}
