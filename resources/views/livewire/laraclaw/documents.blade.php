<?php

use App\Laraclaw\Storage\FileStorageService;
use App\Laraclaw\Storage\VectorStoreService;
use App\Models\LaraclawDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new class extends Component
{
    use WithFileUploads, WithPagination;

    public ?UploadedFile $document = null;

    public ?string $uploadStatus = null;

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
        } catch (Throwable $e) {
            $documentRecord->update([
                'indexed' => false,
                'error_message' => $e->getMessage(),
            ]);

            $this->uploadStatus = "Upload failed for {$originalName}: {$e->getMessage()}";
        }

        $this->reset('document');
    }

    #[Computed]
    public function documents()
    {
        return LaraclawDocument::query()
            ->latest()
            ->paginate(15);
    }

    public function deleteDocument(int $id): void
    {
        LaraclawDocument::destroy($id);
    }

    public function rendering(View $view): void
    {
        $view->layout('components.laraclaw.layout');
    }
}; ?>

<div class="space-y-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-100">Documents</h1>
            <p class="text-gray-400">Upload and index documents for AI-powered retrieval</p>
        </div>
    </div>

    <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
        <h2 class="text-lg font-semibold text-gray-100 mb-4">Upload Document</h2>
        <p class="text-sm text-gray-400 mb-4">Supported formats: PDF, TXT, DOC, DOCX, MD (max 10MB)</p>

        <form wire:submit="ingestDocument" class="space-y-3">
            <div class="flex items-center gap-4">
                <input
                    type="file"
                    wire:model="document"
                    accept=".pdf,.txt,.doc,.docx,.md"
                    class="block w-full text-sm text-gray-300 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-indigo-600 file:text-white hover:file:bg-indigo-700"
                >
                <button
                    type="submit"
                    class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 rounded-lg font-medium transition disabled:opacity-50 whitespace-nowrap"
                >
                    Upload & Index
                </button>
            </div>

            @error('document')
                <p class="text-sm text-red-400">{{ $message }}</p>
            @enderror

            @if($uploadStatus)
                <p class="text-sm {{ str_contains($uploadStatus, 'failed') || str_contains($uploadStatus, 'Failed') ? 'text-red-400' : 'text-green-400' }}">
                    {{ $uploadStatus }}
                </p>
            @endif
        </form>
    </div>

    <div class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-700">
            <h2 class="text-lg font-semibold text-gray-100">All Documents</h2>
        </div>

        @if($this->documents->count() > 0)
            <table class="w-full">
                <thead class="bg-gray-700/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Name</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Size</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Uploaded</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-400 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                    @foreach($this->documents as $doc)
                        <tr class="hover:bg-gray-700/30 transition">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <svg class="w-4 h-4 text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    <span class="text-gray-200 text-sm">{{ $doc->original_name }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-gray-400 text-sm">
                                {{ $doc->size ? number_format($doc->size / 1024, 1) . ' KB' : '-' }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 text-xs rounded-full {{ $doc->indexed ? 'bg-green-600/20 text-green-400' : ($doc->error_message ? 'bg-red-600/20 text-red-400' : 'bg-yellow-600/20 text-yellow-400') }}">
                                    {{ $doc->indexed ? 'Indexed' : ($doc->error_message ? 'Error' : 'Pending') }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-400 text-sm">
                                {{ $doc->created_at->diffForHumans() }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button
                                    wire:click="deleteDocument({{ $doc->id }})"
                                    wire:confirm="Delete this document?"
                                    class="text-gray-400 hover:text-red-400 transition"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if($this->documents->hasPages())
                <div class="px-4 py-3 border-t border-gray-700">
                    {{ $this->documents->links() }}
                </div>
            @endif
        @else
            <div class="text-center py-16">
                <svg class="w-16 h-16 mx-auto mb-4 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <h3 class="text-lg font-medium text-gray-400">No documents uploaded</h3>
                <p class="mt-2 text-gray-500">Upload a PDF, TXT, or Markdown file to get started.</p>
            </div>
        @endif
    </div>
</div>
