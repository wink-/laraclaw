<?php

namespace App\Laraclaw\Identity;

use App\Laraclaw\Identity\Aieos\AieosEntity;
use App\Laraclaw\Identity\Aieos\AieosParser;
use App\Laraclaw\Identity\Aieos\AieosPromptCompiler;
use Illuminate\Support\Facades\File;

class IdentityManager
{
    protected string $identityPath;

    protected ?string $identity = null;

    protected ?string $soul = null;

    protected ?AieosEntity $aieosEntity = null;

    protected bool $loaded = false;

    protected AieosParser $aieosParser;

    protected AieosPromptCompiler $aieosCompiler;

    public function __construct()
    {
        $this->identityPath = config('laraclaw.identity.path', storage_path('laraclaw'));
        $this->aieosParser = new AieosParser;
        $this->aieosCompiler = new AieosPromptCompiler;
    }

    /**
     * Load identity files from disk.
     */
    protected function load(): void
    {
        if ($this->loaded) {
            return;
        }

        // Try AIEOS first
        $aieosFile = $this->identityPath.'/'.config('laraclaw.identity.aieos_file', 'aieos.json');
        if (File::exists($aieosFile) && config('laraclaw.identity.aieos_enabled', true)) {
            try {
                $this->aieosEntity = $this->aieosParser->fromFile($aieosFile);
            } catch (\Exception $e) {
                \Log::warning('Failed to load AIEOS file', ['error' => $e->getMessage()]);
            }
        }

        // Fall back to legacy markdown files
        $identityFile = $this->identityPath.'/'.config('laraclaw.identity.identity_file', 'IDENTITY.md');
        $soulFile = $this->identityPath.'/'.config('laraclaw.identity.soul_file', 'SOUL.md');

        if (File::exists($identityFile)) {
            $this->identity = File::get($identityFile);
        }

        if (File::exists($soulFile)) {
            $this->soul = File::get($soulFile);
        }

        $this->loaded = true;
    }

    /**
     * Get the identity content.
     */
    public function getIdentity(): ?string
    {
        $this->load();

        return $this->identity;
    }

    /**
     * Get the soul content.
     */
    public function getSoul(): ?string
    {
        $this->load();

        return $this->soul;
    }

    /**
     * Get the AIEOS entity.
     */
    public function getAieosEntity(): ?AieosEntity
    {
        $this->load();

        return $this->aieosEntity;
    }

    /**
     * Check if identity file exists.
     */
    public function hasIdentity(): bool
    {
        $this->load();

        return ! empty($this->identity) || $this->aieosEntity !== null;
    }

    /**
     * Check if soul file exists.
     */
    public function hasSoul(): bool
    {
        $this->load();

        return ! empty($this->soul) || $this->aieosEntity !== null;
    }

    /**
     * Check if AIEOS is loaded.
     */
    public function hasAieos(): bool
    {
        $this->load();

        return $this->aieosEntity !== null;
    }

    /**
     * Build the full system prompt from identity files.
     */
    public function buildSystemPrompt(string $basePrompt = ''): string
    {
        $this->load();

        $parts = [];

        // Start with base prompt or default
        if ($basePrompt) {
            $parts[] = $basePrompt;
        }

        // Use AIEOS if available
        if ($this->aieosEntity) {
            $aieosPrompt = $this->aieosCompiler->compile($this->aieosEntity);
            if ($aieosPrompt) {
                $parts[] = $aieosPrompt;
            }
        } else {
            // Fall back to legacy markdown files
            if ($this->identity) {
                $parts[] = "## Identity\n\n".$this->identity;
            }

            if ($this->soul) {
                $parts[] = "## Personality\n\n".$this->soul;
            }
        }

        return implode("\n\n", $parts);
    }

    /**
     * Update the identity file.
     */
    public function setIdentity(string $content): bool
    {
        $this->ensureDirectoryExists();

        $identityFile = $this->identityPath.'/'.config('laraclaw.identity.identity_file', 'IDENTITY.md');

        $saved = File::put($identityFile, $content) !== false;

        if ($saved) {
            $this->identity = $content;
        }

        return $saved;
    }

    /**
     * Update the soul file.
     */
    public function setSoul(string $content): bool
    {
        $this->ensureDirectoryExists();

        $soulFile = $this->identityPath.'/'.config('laraclaw.identity.soul_file', 'SOUL.md');

        $saved = File::put($soulFile, $content) !== false;

        if ($saved) {
            $this->soul = $content;
        }

        return $saved;
    }

    /**
     * Set AIEOS entity.
     */
    public function setAieosEntity(AieosEntity $entity): bool
    {
        $this->ensureDirectoryExists();

        $aieosFile = $this->identityPath.'/'.config('laraclaw.identity.aieos_file', 'aieos.json');

        $saved = File::put($aieosFile, json_encode($entity->toArray(), JSON_PRETTY_PRINT)) !== false;

        if ($saved) {
            $this->aieosEntity = $entity;
        }

        return $saved;
    }

    /**
     * Get the identity file path.
     */
    public function getIdentityPath(): string
    {
        return $this->identityPath;
    }

    /**
     * Get status information about identity files.
     */
    public function getStatus(): array
    {
        $this->load();

        return [
            'path' => $this->identityPath,
            'identity_exists' => ! empty($this->identity),
            'soul_exists' => ! empty($this->soul),
            'aieos_exists' => $this->aieosEntity !== null,
            'identity_size' => $this->identity ? strlen($this->identity) : 0,
            'soul_size' => $this->soul ? strlen($this->soul) : 0,
        ];
    }

    /**
     * Reload identity files from disk.
     */
    public function reload(): self
    {
        $this->loaded = false;
        $this->identity = null;
        $this->soul = null;
        $this->aieosEntity = null;

        return $this;
    }

    /**
     * Ensure the identity directory exists.
     */
    protected function ensureDirectoryExists(): void
    {
        if (! File::isDirectory($this->identityPath)) {
            File::makeDirectory($this->identityPath, 0755, true);
        }
    }
}
