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

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $skills = [];

    public function mount(): void
    {
        $this->loadStats();
        $this->loadHealth();
        $this->loadSkills();
    }

    protected function loadStats(): void
    {
        $this->stats = [
            'conversations' => Conversation::count(),
            'messages' => \App\Models\Message::count(),
            'memories' => MemoryFragment::count(),
            'today_conversations' => Conversation::whereDate('created_at', today())->count(),
            'agent_collaborations' => AgentCollaboration::count(),
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
        Laraclaw::setSkillEnabled($className, $enabled);
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

    public function render()
    {
        return view('livewire.laraclaw.dashboard', [
            'stats' => $this->stats,
            'health' => $this->health,
            'recentConversations' => $this->recentConversations(),
            'recentDocuments' => $this->recentDocuments(),
            'skills' => $this->skills,
        ])->layout('components.laraclaw.layout');
    }
}
