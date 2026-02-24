<?php

namespace App\Livewire\Laraclaw;

use App\Laraclaw\Facades\Laraclaw;
use App\Laraclaw\Heartbeat\HeartbeatEngine;
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

    /**
     * @var array<string, mixed>
     */
    public array $opsSignals = [];

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

    public function mount(): void
    {
        $this->loadStats();
        $this->loadHealth();
        $this->loadSkills();
        $this->loadScheduledTasks();
        $this->loadOpsSignals();
        $this->loadTailscaleStatus();
        $this->loadHeartbeat();
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
            'tailscaleStatus' => $this->tailscaleStatus,
            'heartbeatItems' => $this->heartbeatItems,
            'recentHeartbeatRuns' => $this->recentHeartbeatRuns,
        ])->layout('components.laraclaw.layout');
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
