<?php

namespace App\Livewire\Laraclaw;

use App\Laraclaw\Facades\Laraclaw;
use App\Laraclaw\Storage\FileStorageService;
use App\Laraclaw\Storage\VectorStoreService;
use App\Models\AgentCollaboration;
use App\Models\Conversation;
use App\Models\LaraclawDocument;
use App\Models\MemoryFragment;
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
     * @var array<int, array<string, mixed>>
     */
    public array $skills = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $scheduledTasks = [];

    public function mount(): void
    {
        $this->loadStats();
        $this->loadHealth();
        $this->loadSkills();
        $this->loadScheduledTasks();
    }

    protected function loadStats(): void
    {
        $this->stats = [
            'conversations' => Conversation::count(),
            'messages' => \App\Models\Message::count(),
            'memories' => MemoryFragment::count(),
            'today_conversations' => Conversation::whereDate('created_at', today())->count(),
            'agent_collaborations' => AgentCollaboration::count(),
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
        ])->layout('components.laraclaw.layout');
    }
}
