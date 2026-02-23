<?php

namespace App\Laraclaw\Storage;

use App\Models\MemoryFragment;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Providers\Tools\FileSearch;
use Laravel\Ai\Stores;
use Laravel\Ai\Tools\SimilaritySearch;

/**
 * Vector store service for semantic search and RAG.
 */
class VectorStoreService
{
    protected string $defaultStore = 'laraclaw-knowledge-base';

    protected int $dimensions = 1536;

    protected array $config;

    public function __construct()
    {
        $this->config = config('laraclaw.vectors', []);
        $this->dimensions = $this->config['dimensions'] ?? 1536;
    }

    /**
     * Create a vector store for knowledge storage.
     */
    public function createStore(string $name, ?string $description = null): array
    {
        try {
            $store = Stores::create(name: $name, description: $description);

            return [
                'id' => $store->id,
                'name' => $name,
                'description' => $description,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to create vector store', ['error' => $e->getMessage()]);

            throw $e;
        }
    }

    /**
     * Get or create the default knowledge base store.
     */
    public function getDefaultStore(): array
    {
        $stores = Stores::list();

        foreach ($stores as $store) {
            if ($store->name === $this->defaultStore) {
                return ['id' => $store->id, 'name' => $store->name];
            }
        }

        return $this->createStore($this->defaultStore, 'Laraclaw knowledge base for memory and documents');
    }

    /**
     * Add a document to a vector store.
     */
    public function addDocument(string $storeId, string $documentId): bool
    {
        try {
            $store = Stores::get($storeId);
            $document = $store->add($documentId);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to add document to store', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Search for similar content using vector embeddings.
     */
    public function search(string $query, int $limit = 10, float $minSimilarity = 0.7): array
    {
        try {
            $results = MemoryFragment::query()
                ->whereVectorSimilarTo('embedding', $query, minSimilarity: $minSimilarity)
                ->limit($limit)
                ->get();

            return $results->map(fn ($fragment) => [
                'id' => $fragment->id,
                'key' => $fragment->key,
                'content' => $fragment->content,
                'similarity' => $fragment->embedding_similarity ?? null,
            ])->all();
        } catch (\Exception $e) {
            Log::error('Vector search failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Generate embeddings for text.
     */
    public function generateEmbeddings(string $text): array
    {
        try {
            $response = Embeddings::for([$text])->generate();

            return $response->embeddings[0] ?? [];
        } catch (\Exception $e) {
            Log::error('Failed to generate embeddings', ['error' => $e->getMessage()]);

            throw $e;
        }
    }

    /**
     * Get a similarity search tool for agents.
     */
    public function getSimilaritySearchTool(): SimilaritySearch
    {
        return SimilaritySearch::usingModel(MemoryFragment::class, 'embedding')
            ->withDescription('Search the knowledge base for relevant memories and documents.')
            ->minSimilarity($this->config['min_similarity'] ?? 0.7)
            ->limit($this->config['search_limit'] ?? 10);
    }

    /**
     * Get a file search tool for agents.
     */
    public function getFileSearchTool(array $storeIds): FileSearch
    {
        return new FileSearch(stores: $storeIds);
    }

    /**
     * List all vector stores.
     */
    public function listStores(): array
    {
        $stores = Stores::list();

        return collect($stores)->map(fn ($store) => [
            'id' => $store->id,
            'name' => $store->name,
            'description' => $store->description ?? null,
        ])->all();
    }

    /**
     * Delete a vector store.
     */
    public function deleteStore(string $storeId): bool
    {
        try {
            $store = Stores::get($storeId);
            $store->delete();

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to delete vector store', ['error' => $e->getMessage()]);

            return false;
        }
    }
}
