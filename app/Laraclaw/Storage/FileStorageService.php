<?php

namespace App\Laraclaw\Storage;

use Illuminate\Support\Facades\Log;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Files\Document;
use Laravel\Ai\Files\Image;

/**
 * File storage service using Laravel AI SDK.
 */
class FileStorageService
{
    protected string $storagePath;

    protected array $config;

    public function __construct()
    {
        $this->storagePath = config('laraclaw.files.path', storage_path('laraclaw/files'));
        $this->config = config('laraclaw.files', []);
    }

    /**
     * Store a document file with the AI provider.
     */
    public function storeDocument(string $path, ?string $filename = null): array
    {
        if (! file_exists($path)) {
            throw new \InvalidArgumentException("File not found: {$path}");
        }

        $provider = $this->getProvider();

        try {
            $document = Document::fromPath($path)->put(provider: $provider);

            Log::info('Document stored with AI provider', [
                'id' => $document->id,
                'filename' => $filename ?? basename($path),
            ]);

            return [
                'id' => $document->id,
                'filename' => $filename ?? basename($path),
                'mime_type' => $document->mimeType(),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to store document', ['error' => $e->getMessage()]);

            throw $e;
        }
    }

    /**
     * Store document from string content.
     */
    public function storeDocumentFromString(string $content, string $filename, string $mimeType = 'text/plain'): array
    {
        $provider = $this->getProvider();

        try {
            $document = Document::fromString($content, $mimeType)->put(provider: $provider);

            return [
                'id' => $document->id,
                'filename' => $filename,
                'mime_type' => $document->mimeType(),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to store document from string', ['error' => $e->getMessage()]);

            throw $e;
        }
    }

    /**
     * Store an image file with the AI provider.
     */
    public function storeImage(string $path): array
    {
        if (! file_exists($path)) {
            throw new \InvalidArgumentException("File not found: {$path}");
        }

        $provider = $this->getProvider();

        try {
            $image = Image::fromPath($path)->put(provider: $provider);

            return [
                'id' => $image->id,
                'filename' => basename($path),
                'mime_type' => $image->mimeType(),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to store image', ['error' => $e->getMessage()]);

            throw $e;
        }
    }

    /**
     * Retrieve a stored document.
     */
    public function getDocument(string $id): ?array
    {
        try {
            $document = Document::fromId($id)->get();

            return [
                'id' => $document->id,
                'mime_type' => $document->mimeType(),
                'content' => $document->content ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve document', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Delete a stored file.
     */
    public function deleteFile(string $id): bool
    {
        try {
            Document::fromId($id)->delete();

            Log::info('File deleted', ['id' => $id]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to delete file', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Get the AI provider to use.
     */
    protected function getProvider(): Lab
    {
        $provider = $this->config['provider'] ?? 'openai';

        return match ($provider) {
            'anthropic' => Lab::Anthropic,
            'openai' => Lab::OpenAI,
            'gemini' => Lab::Gemini,
            default => Lab::OpenAI,
        };
    }
}
